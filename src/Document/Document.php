<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use InvalidArgumentException;
use Kalle\Pdf\Document\Form\AcroForm;
use Kalle\Pdf\Document\Geometry\Position;
use Kalle\Pdf\Document\Text\ParagraphOptions;
use Kalle\Pdf\Document\Text\StructureTag;
use Kalle\Pdf\Document\Text\TextFrame;
use Kalle\Pdf\Document\Text\TextOptions;
use Kalle\Pdf\Encryption\EncryptionAlgorithm;
use Kalle\Pdf\Encryption\EncryptionOptions;
use Kalle\Pdf\Encryption\EncryptionProfile;
use Kalle\Pdf\Encryption\EncryptionVersionResolver;
use Kalle\Pdf\Encryption\StandardSecurityHandler;
use Kalle\Pdf\Encryption\StandardSecurityHandlerData;
use Kalle\Pdf\Font\CidFont;
use Kalle\Pdf\Font\CidToGidMap;
use Kalle\Pdf\Font\EncodingDictionary;
use Kalle\Pdf\Font\FontDefinition;
use Kalle\Pdf\Font\FontDescriptor;
use Kalle\Pdf\Font\FontFileStream;
use Kalle\Pdf\Font\FontRegistry;
use Kalle\Pdf\Font\OpenTypeFontParser;
use Kalle\Pdf\Font\StandardFont;
use Kalle\Pdf\Font\ToUnicodeCMap;
use Kalle\Pdf\Font\UnicodeFont;
use Kalle\Pdf\Font\UnicodeGlyphMap;
use Kalle\Pdf\Layout\PageSize;
use Kalle\Pdf\Layout\TableOfContentsPosition;
use Kalle\Pdf\Object\IndirectObject;
use Kalle\Pdf\Render\PdfRenderer;
use Kalle\Pdf\Structure\ParentTree;
use Kalle\Pdf\Structure\StructElem;
use Kalle\Pdf\Structure\StructTreeRoot;
use Kalle\Pdf\Utilities\StringListNormalizer;
use Random\RandomException;

final class Document
{
    private int $objectId = 0;
    private int $structParentId = -1;
    private ?EncryptionProfile $encryptionProfile = null;
    private ?EncryptionOptions $encryptionOptions = null;
    private ?StandardSecurityHandlerData $securityHandlerData = null;
    /** @var list<callable(Page, int): void> */
    private array $headerRenderers = [];
    /** @var list<callable(Page, int): void> */
    private array $footerRenderers = [];
    /** @var list<callable(Page, int, int): void> */
    private array $deferredHeaderRenderers = [];
    /** @var list<callable(Page, int, int): void> */
    private array $deferredFooterRenderers = [];

    /** @var array<int, FontDefinition&IndirectObject> */
    public array $fonts = [];

    /** @var string[] */
    public array $keywords = [];
    /** @var array<string, Page> */
    private array $destinations = [];
    /** @var array<string, OptionalContentGroup> */
    private array $optionalContentGroups = [];
    /** @var list<FileSpecification> */
    private array $attachments = [];
    /** @var array{string, string} */
    private array $documentId;

    /** @var StructElem[]  */
    private array $structElems = [];
    public Catalog $catalog;
    public ?AcroForm $acroForm = null;
    public ?EncryptDictionary $encryptDictionary = null;
    public Info $info;
    public ?OutlineRoot $outlineRoot = null;
    public ?ParentTree $parentTree = null;
    public Pages $pages;
    public ?StructTreeRoot $structTreeRoot = null;

    /**
     * @param list<array{
     *     baseFont: string,
     *     path: string,
     *     unicode: bool,
     *     subtype?: string,
     *     encoding?: string
     * }>|null $fontConfig
     */
    public function __construct(
        public readonly float   $version = 1.0,
        public readonly ?string $title = null,
        public readonly ?string $author = null,
        public readonly ?string $subject = null,
        public readonly ?string $language = null,
        private readonly ?array $fontConfig = null,
    ) {
        $this->catalog = new Catalog(++$this->objectId, $this);
        $this->pages = new Pages(++$this->objectId, $this);

        $this->info = new Info(++$this->objectId, $this);
        $this->documentId = $this->generateDocumentId();
    }

    /**
     * @return int
     */
    public function getUniqObjectId(): int
    {
        return ++$this->objectId;
    }

    /**
     * @return list<IndirectObject>
     */
    public function getDocumentObjects(): array
    {
        /** @var list<IndirectObject> $objects */
        $objects = [];

        $objects[] = $this->catalog;
        $objects[] = $this->pages;

        if ($this->outlineRoot !== null) {
            $objects[] = $this->outlineRoot;

            foreach ($this->outlineRoot->getItems() as $outlineItem) {
                $objects[] = $outlineItem;
            }
        }

        if ($this->acroForm !== null) {
            $objects[] = $this->acroForm;

            foreach ($this->acroForm->getFieldObjectsForRender() as $fieldObject) {
                $objects[] = $fieldObject;
            }
        }

        if ($this->structTreeRoot !== null) {
            $objects[] = $this->structTreeRoot;
        }

        if ($this->parentTree !== null) {
            $objects[] = $this->parentTree;
        }

        foreach ($this->structElems as $structElem) {
            $objects[] = $structElem;
        }

        if ($this->encryptDictionary !== null) {
            $objects[] = $this->encryptDictionary;
        }

        foreach ($this->optionalContentGroups as $optionalContentGroup) {
            $objects[] = $optionalContentGroup;
        }

        foreach ($this->attachments as $attachment) {
            $objects[] = $attachment;
            $objects[] = $attachment->getEmbeddedFile();
        }

        $objects[] = $this->info;

        foreach ($this->fonts as $font) {
            if ($font instanceof UnicodeFont) {
                if ($font->descendantFont->fontDescriptor !== null) {
                    $objects[] = $font->descendantFont->fontDescriptor->fontFile;
                    $objects[] = $font->descendantFont->fontDescriptor;
                }

                if ($font->descendantFont->cidToGidMap !== null) {
                    $objects[] = $font->descendantFont->cidToGidMap;
                }

                $objects[] = $font->descendantFont;
                $objects[] = $font->toUnicode;
            }

            if ($font instanceof StandardFont && $font->encodingDictionary !== null) {
                $objects[] = $font->encodingDictionary;
            }

            $objects[] = $font;
        }
        foreach ($this->pages->pages as $page) {
            $objects[] = $page;
            foreach ($page->getAnnotations() as $annotation) {
                $objects[] = $annotation;

                foreach ($annotation->getRelatedObjects() as $relatedObject) {
                    $objects[] = $relatedObject;
                }
            }
            foreach ($page->resources->getImages() as $image) {
                $objects[] = $image;
            }
            $objects[] = $page->resources;
            $objects[] = $page->contents;
        }

        return $objects;
    }

    public function addPage(PageSize | float $width = 595.2755905511812, ?float $height = null): Page
    {
        if ($width instanceof PageSize) {
            if ($height !== null) {
                throw new InvalidArgumentException('Height must not be provided when using a PageSize.');
            }

            $height = $width->height();
            $width = $width->width();
        }

        $height ??= 841.8897637795277;

        $page = $this->pages->addPage(++$this->objectId, ++$this->objectId, ++$this->objectId, ++$this->structParentId, $width, $height);
        $this->applyPageDecorators($page);

        return $page;
    }

    public function addOutline(string $title, Page $page): self
    {
        if ($title === '') {
            throw new InvalidArgumentException('Outline title must not be empty.');
        }

        $this->outlineRoot ??= new OutlineRoot(++$this->objectId);
        $this->outlineRoot->addItem(new OutlineItem(++$this->objectId, $this->outlineRoot, $title, $page));

        return $this;
    }

    public function addDestination(string $name, Page $page): self
    {
        if ($name === '') {
            throw new InvalidArgumentException('Destination name must not be empty.');
        }

        if (!preg_match('/^[A-Za-z0-9._-]+$/', $name)) {
            throw new InvalidArgumentException('Destination name may contain only letters, numbers, dots, underscores and hyphens.');
        }

        $this->destinations[$name] = $page;

        return $this;
    }

    public function encrypt(EncryptionOptions $options): self
    {
        $resolver = new EncryptionVersionResolver();
        $this->encryptionOptions = $options;
        $this->encryptionProfile = $resolver->resolve($this->version, $options->algorithm);
        $this->securityHandlerData = null;
        $this->encryptDictionary = new EncryptDictionary(++$this->objectId, $this, $this->encryptionProfile);

        return $this;
    }

    public function getEncryptionProfile(): ?EncryptionProfile
    {
        return $this->encryptionProfile;
    }

    public function getEncryptionOptions(): ?EncryptionOptions
    {
        return $this->encryptionOptions;
    }

    public function getSecurityHandlerData(): ?StandardSecurityHandlerData
    {
        if ($this->encryptionProfile === null || $this->encryptionOptions === null) {
            return null;
        }

        if ($this->securityHandlerData === null) {
            $this->securityHandlerData = (new StandardSecurityHandler())->build(
                $this->encryptionOptions,
                $this->encryptionProfile,
                $this->documentId[0],
            );
        }

        return $this->securityHandlerData;
    }

    /**
     * @return array{string, string}
     */
    public function getDocumentId(): array
    {
        return $this->documentId;
    }

    /**
     * @return array<string, Page>
     */
    public function getDestinations(): array
    {
        return $this->destinations;
    }

    public function ensureOptionalContentGroup(string $name, bool $visibleByDefault = true): OptionalContentGroup
    {
        if ($name === '') {
            throw new InvalidArgumentException('Optional content group name must not be empty.');
        }

        if (isset($this->optionalContentGroups[$name])) {
            return $this->optionalContentGroups[$name];
        }

        $group = new OptionalContentGroup($this->getUniqObjectId(), $name, $visibleByDefault);
        $this->optionalContentGroups[$name] = $group;

        return $group;
    }

    public function addLayer(string $name, bool $visibleByDefault = true): OptionalContentGroup
    {
        return $this->ensureOptionalContentGroup($name, $visibleByDefault);
    }

    public function addAttachment(
        string $filename,
        string $contents,
        ?string $description = null,
        ?string $mimeType = null,
    ): self {
        if ($filename === '') {
            throw new InvalidArgumentException('Attachment filename must not be empty.');
        }

        $embeddedFile = new EmbeddedFileStream($this->getUniqObjectId(), $contents, $mimeType);
        $this->attachments[] = new FileSpecification($this->getUniqObjectId(), $filename, $embeddedFile, $description);

        return $this;
    }

    public function addAttachmentFromFile(
        string $path,
        ?string $filename = null,
        ?string $description = null,
        ?string $mimeType = null,
    ): self {
        if (!is_file($path)) {
            throw new InvalidArgumentException("Attachment file '$path' does not exist.");
        }

        $filename ??= basename($path);

        /** @var string|false $contents */
        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new InvalidArgumentException("Attachment file '$path' could not be read.");
        }

        return $this->addAttachment($filename, $contents, $description, $mimeType);
    }

    /**
     * @return list<FileSpecification>
     */
    public function getAttachments(): array
    {
        return $this->attachments;
    }

    public function getAttachment(string $filename): ?FileSpecification
    {
        foreach ($this->attachments as $attachment) {
            if ($attachment->getFilename() === $filename) {
                return $attachment;
            }
        }

        return null;
    }

    /**
     * @return list<OptionalContentGroup>
     */
    public function getOptionalContentGroups(): array
    {
        return array_values($this->optionalContentGroups);
    }

    /**
     * @param callable(Page, int): void $renderer
     */
    public function addHeader(callable $renderer): self
    {
        $this->headerRenderers[] = $renderer;

        return $this;
    }

    /**
     * @param callable(Page, int): void $renderer
     */
    public function addFooter(callable $renderer): self
    {
        $this->footerRenderers[] = $renderer;

        return $this;
    }

    public function addPageNumbers(
        Position $position,
        string $baseFont = 'Helvetica',
        int $size = 10,
        string $template = 'Seite {{page}} von {{pages}}',
        bool $footer = true,
    ): self {
        if ($template === '') {
            throw new InvalidArgumentException('Page number template must not be empty.');
        }

        if ($size <= 0) {
            throw new InvalidArgumentException('Page number size must be greater than zero.');
        }

        $renderer = static function (Page $page, int $pageNumber, int $totalPages) use ($position, $baseFont, $size, $template): void {
            $page->addText(
                str_replace(
                    ['{{page}}', '{{pages}}'],
                    [(string) $pageNumber, (string) $totalPages],
                    $template,
                ),
                $position,
                $baseFont,
                $size,
            );
        };

        if ($footer) {
            $this->deferredFooterRenderers[] = $renderer;
        } else {
            $this->deferredHeaderRenderers[] = $renderer;
        }

        return $this;
    }

    public function registerFont(
        string $fontName,
        string $subtype = 'Type1',
        ?string $encoding = null,
        bool $unicode = false,
        ?string $fontFilePath = null,
    ): self {
        $options = $this->resolveFontRegistrationOptions($fontName, $subtype, $encoding, $unicode, $fontFilePath);
        $this->fonts = [
            ...$this->fonts,
            $options['unicode']
                ? $this->createUnicodeFont($options['baseFont'], $options['subtype'], $options['fontFilePath'])
                : $this->createStandardFont($options['baseFont'], $options['subtype'], $options['encoding'], $options['fontFilePath']),
        ];

        return $this;
    }

    public function getFontByBaseFont(string $baseFont): ?FontDefinition
    {
        foreach ($this->fonts as $font) {
            if ($font->getBaseFont() === $baseFont) {
                return $font;
            }
        }

        return null;
    }

    public function ensureAcroForm(): AcroForm
    {
        if ($this->acroForm === null) {
            $this->acroForm = new AcroForm(++$this->objectId);
        }

        return $this->acroForm;
    }

    /**
     * @return list<array{
     *     baseFont: string,
     *     path: string,
     *     unicode: bool,
     *     subtype?: string,
     *     encoding?: string
     * }>|null
     */
    public function getFontConfig(): ?array
    {
        return $this->fontConfig;
    }

    /**
     * @param int $markedContentId
     * @param Page|null $page
     * @return $this
     */
    public function addStructElem(StructureTag $tag, int $markedContentId, ?Page $page = null): self
    {
        $this->ensureStructureEnabled();

        $structElem = new StructElem(++$this->objectId, $tag->value);
        $this->structElems['document']->addKid($structElem);

        $this->structElems[] = $structElem;

        if ($page !== null) {
            $structElem->setMarkedContent($markedContentId, $page);
        }

        if ($page !== null && $this->parentTree !== null) {
            $this->parentTree->add($page->structParentId, $structElem);
        }

        return $this;
    }

    private function applyPageDecorators(Page $page): void
    {
        $pageNumber = count($this->pages->pages);
        $this->runPageDecorators($this->headerRenderers, $page, $pageNumber);
        $this->runPageDecorators($this->footerRenderers, $page, $pageNumber);
    }

    private function applyDeferredPageDecorators(): void
    {
        if ($this->deferredHeaderRenderers === [] && $this->deferredFooterRenderers === []) {
            return;
        }

        $totalPages = count($this->pages->pages);

        foreach ($this->pages->pages as $index => $page) {
            $pageNumber = $index + 1;
            $this->runDeferredPageDecorators($this->deferredHeaderRenderers, $page, $pageNumber, $totalPages);
            $this->runDeferredPageDecorators($this->deferredFooterRenderers, $page, $pageNumber, $totalPages);
        }

        $this->deferredHeaderRenderers = [];
        $this->deferredFooterRenderers = [];
    }

    /**
     * @param list<callable(Page, int): void> $renderers
     */
    private function runPageDecorators(array $renderers, Page $page, int $pageNumber): void
    {
        foreach ($renderers as $renderer) {
            $renderer($page, $pageNumber);
        }
    }

    /**
     * @param list<callable(Page, int, int): void> $renderers
     */
    private function runDeferredPageDecorators(array $renderers, Page $page, int $pageNumber, int $totalPages): void
    {
        foreach ($renderers as $renderer) {
            $renderer($page, $pageNumber, $totalPages);
        }
    }

    public function ensureStructureEnabled(): void
    {
        if ($this->version < 1.4) {
            throw new InvalidArgumentException('Structured content requires PDF version 1.4 or higher.');
        }

        if ($this->structTreeRoot !== null) {
            return;
        }

        $this->structTreeRoot = new StructTreeRoot(++$this->objectId);
        $this->parentTree = new ParentTree(++$this->objectId);
        $this->structTreeRoot->parentTree = $this->parentTree;
        $structElem = new StructElem(++$this->objectId, StructureTag::Document->value);
        $this->structTreeRoot->addKid($structElem->id);
        $this->structElems['document'] = $structElem;
    }

    public function hasStructure(): bool
    {
        return $this->structTreeRoot !== null;
    }

    public function addTableOfContents(
        PageSize | float $width = 595.2755905511812,
        ?float $height = null,
        string $title = 'Inhaltsverzeichnis',
        string $baseFont = 'Helvetica',
        int $titleSize = 18,
        int $entrySize = 12,
        float $margin = 20.0,
        TableOfContentsPosition $position = TableOfContentsPosition::END,
    ): Page {
        if ($titleSize <= 0) {
            throw new InvalidArgumentException('Table of contents title size must be greater than zero.');
        }

        if ($entrySize <= 0) {
            throw new InvalidArgumentException('Table of contents entry size must be greater than zero.');
        }

        if ($margin < 0) {
            throw new InvalidArgumentException('Table of contents margin must be zero or greater.');
        }

        $firstTocPageIndex = count($this->pages->pages);
        $page = $this->addPage($width, $height);
        $contentWidth = $page->getWidth() - ($margin * 2);

        if ($contentWidth <= 0) {
            throw new InvalidArgumentException('Table of contents content width must be greater than zero.');
        }

        $frame = $page->createTextFrame(new Position($margin, $page->getHeight() - $margin), $contentWidth, $margin);
        $frame->addHeading($title, $baseFont, $titleSize, new ParagraphOptions(structureTag: StructureTag::Heading1));

        if ($this->outlineRoot === null || $this->outlineRoot->getItems() === []) {
            $frame->addParagraph('Keine Eintraege vorhanden.', $baseFont, $entrySize, new ParagraphOptions(structureTag: StructureTag::Paragraph));

            return $page;
        }

        $entryLineHeight = $entrySize * 1.35;
        $currentPage = $page;
        $currentY = $frame->getCursorY();
        $pageNumbersByObjectId = $this->buildTableOfContentsPageNumbers(
            $firstTocPageIndex,
            $this->estimateTableOfContentsPageCount(
                count($this->outlineRoot->getItems()),
                $currentY,
                $page->getHeight(),
                $margin,
                $entryLineHeight,
            ),
            $position,
        );

        foreach ($this->outlineRoot->getItems() as $outlineItem) {
            if ($currentY < $margin + $entryLineHeight) {
                $currentPage = $this->addPage($page->getWidth(), $page->getHeight());
                $currentY = $currentPage->getHeight() - $margin;
            }

            $targetPage = $outlineItem->getPage();
            $pageNumber = $pageNumbersByObjectId[$targetPage->id] ?? null;

            if ($pageNumber === null) {
                continue;
            }

            $destinationName = 'toc-page-' . $targetPage->id;
            $this->addDestination($destinationName, $targetPage);

            $pageNumberText = (string) $pageNumber;
            $pageNumberWidth = $currentPage->measureTextWidth($pageNumberText, $baseFont, $entrySize);
            $entryWidth = $contentWidth - $pageNumberWidth - 8.0;
            $entryTitle = $this->fitTextToWidth($currentPage, $outlineItem->getTitle(), $baseFont, $entrySize, $entryWidth);
            $entryTitleWidth = $currentPage->measureTextWidth($entryTitle, $baseFont, $entrySize);
            $leaderWidth = max(0.0, $contentWidth - $entryTitleWidth - $pageNumberWidth - 8.0);
            $dotWidth = max(0.0001, $currentPage->measureTextWidth('.', $baseFont, $entrySize));
            $dotCount = max(3, (int) floor($leaderWidth / $dotWidth));
            $leaders = str_repeat('.', $dotCount);

            $currentPage->addText(
                $entryTitle,
                new Position($margin, $currentY),
                $baseFont,
                $entrySize,
                new TextOptions(link: LinkTarget::namedDestination($destinationName)),
            );
            $currentPage->addText(
                $leaders,
                new Position($margin + $entryTitleWidth + 4.0, $currentY),
                $baseFont,
                $entrySize,
            );
            $currentPage->addText(
                $pageNumberText,
                new Position($page->getWidth() - $margin - $pageNumberWidth, $currentY),
                $baseFont,
                $entrySize,
                new TextOptions(link: LinkTarget::namedDestination($destinationName)),
            );

            $currentY -= $entryLineHeight;
        }

        if ($position === TableOfContentsPosition::START) {
            $tocPages = array_values(array_slice($this->pages->pages, $firstTocPageIndex));
            $this->pages->prependPages($tocPages);
        }

        return $page;
    }

    public function addKeyword(string $keyword): self
    {
        $this->keywords = StringListNormalizer::unique([...$this->keywords, $keyword]);

        return $this;
    }

    public function render(): string
    {
        $this->applyDeferredPageDecorators();

        $renderer = new PdfRenderer();

        return $renderer->render($this);
    }

    /**
     * @return array{
     *     baseFont: string,
     *     subtype: string,
     *     encoding: string,
     *     unicode: bool,
     *     fontFilePath: ?string
     * }
     */
    private function resolveFontRegistrationOptions(
        string $fontName,
        string $subtype,
        ?string $encoding,
        bool $unicode,
        ?string $fontFilePath,
    ): array {
        if (!FontRegistry::has($fontName, $this->fontConfig)) {
            return [
                'baseFont' => $fontName,
                'subtype' => $subtype,
                'encoding' => $encoding ?? $this->resolveDefaultStandardFontEncoding(),
                'unicode' => $unicode,
                'fontFilePath' => $fontFilePath,
            ];
        }

        $preset = FontRegistry::get($fontName, $this->fontConfig);

        return [
            'baseFont' => $preset->baseFont,
            'subtype' => $preset->subtype,
            'encoding' => $encoding ?? $preset->encoding,
            'unicode' => $preset->unicode,
            'fontFilePath' => $preset->path,
        ];
    }

    private function resolveDefaultStandardFontEncoding(): string
    {
        if ($this->version <= 1.0) {
            return 'StandardEncoding';
        }

        return 'WinAnsiEncoding';
    }

    private function createUnicodeFont(string $baseFont, string $subtype, ?string $fontFilePath): UnicodeFont
    {
        $glyphMap = new UnicodeGlyphMap();
        $fontDescriptor = null;
        $fontParser = null;

        if ($fontFilePath !== null) {
            $fontFile = FontFileStream::fromPath(++$this->objectId, $fontFilePath);
            $fontDescriptor = new FontDescriptor(++$this->objectId, $baseFont, $fontFile);
            $fontParser = new OpenTypeFontParser($fontFile->data);

            if ($fontParser->hasCffOutlines()) {
                $subtype = 'CIDFontType0';
            }
        }

        $cidToGidMap = $fontParser !== null ? new CidToGidMap(++$this->objectId, $glyphMap, $fontParser) : null;
        $descendantFont = new CidFont(
            ++$this->objectId,
            $baseFont,
            $subtype,
            fontDescriptor: $fontDescriptor,
            cidToGidMap: $cidToGidMap,
            defaultWidth: 1000,
            widths: [],
        );
        $toUnicode = new ToUnicodeCMap(++$this->objectId, $glyphMap);

        return new UnicodeFont(
            ++$this->objectId,
            $descendantFont,
            $toUnicode,
            $glyphMap,
        );
    }

    private function createStandardFont(
        string $baseFont,
        string $subtype,
        string $encoding,
        ?string $fontFilePath,
    ): StandardFont {
        $encodingDictionary = null;
        $byteMap = [];

        if ($fontFilePath === null && $encoding === 'StandardEncoding' && $this->supportsWesternStandardEncodingDifferences($baseFont)) {
            $encodingDictionary = new EncodingDictionary(
                ++$this->objectId,
                'StandardEncoding',
                $this->westernStandardEncodingDifferences(),
            );
            $byteMap = $this->westernStandardEncodingByteMap();
        }

        return new StandardFont(
            ++$this->objectId,
            $baseFont,
            $subtype,
            $encoding,
            $this->version,
            $this->createOptionalFontParser($fontFilePath),
            $encodingDictionary,
            $byteMap,
        );
    }

    private function createOptionalFontParser(?string $fontFilePath): ?OpenTypeFontParser
    {
        if ($fontFilePath === null) {
            return null;
        }

        /** @var string|false $fontData */
        $fontData = file_get_contents($fontFilePath);

        if ($fontData === false) {
            throw new InvalidArgumentException("Unable to read font file '$fontFilePath'.");
        }

        return new OpenTypeFontParser($fontData);
    }

    private function supportsWesternStandardEncodingDifferences(string $baseFont): bool
    {
        return !in_array($baseFont, ['Symbol', 'ZapfDingbats'], true);
    }

    /**
     * @return array<int, string>
     */
    private function westernStandardEncodingDifferences(): array
    {
        return [
            128 => 'Adieresis',
            129 => 'Aring',
            130 => 'Ccedilla',
            131 => 'Eacute',
            132 => 'Ntilde',
            133 => 'Odieresis',
            134 => 'Udieresis',
            135 => 'aacute',
            136 => 'agrave',
            137 => 'acircumflex',
            138 => 'adieresis',
            139 => 'atilde',
            140 => 'aring',
            141 => 'ccedilla',
            142 => 'eacute',
            143 => 'egrave',
            144 => 'ecircumflex',
            145 => 'edieresis',
            146 => 'iacute',
            147 => 'igrave',
            148 => 'icircumflex',
            149 => 'idieresis',
            150 => 'ntilde',
            151 => 'oacute',
            152 => 'ograve',
            153 => 'ocircumflex',
            154 => 'odieresis',
            155 => 'otilde',
            156 => 'uacute',
            157 => 'ugrave',
            158 => 'ucircumflex',
            159 => 'udieresis',
            160 => 'dagger',
            161 => 'degree',
            162 => 'cent',
            163 => 'sterling',
            164 => 'section',
            165 => 'bullet',
            166 => 'paragraph',
            167 => 'germandbls',
            168 => 'registered',
            169 => 'copyright',
            170 => 'trademark',
            171 => 'acute',
            172 => 'dieresis',
            174 => 'AE',
            175 => 'Oslash',
            177 => 'plusminus',
            180 => 'yen',
            181 => 'mu',
            187 => 'ordfeminine',
            188 => 'ordmasculine',
            190 => 'ae',
            191 => 'oslash',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function westernStandardEncodingByteMap(): array
    {
        return [
            'Ä' => "\x80",
            'Å' => "\x81",
            'Ç' => "\x82",
            'É' => "\x83",
            'Ñ' => "\x84",
            'Ö' => "\x85",
            'Ü' => "\x86",
            'á' => "\x87",
            'à' => "\x88",
            'â' => "\x89",
            'ä' => "\x8A",
            'ã' => "\x8B",
            'å' => "\x8C",
            'ç' => "\x8D",
            'é' => "\x8E",
            'è' => "\x8F",
            'ê' => "\x90",
            'ë' => "\x91",
            'í' => "\x92",
            'ì' => "\x93",
            'î' => "\x94",
            'ï' => "\x95",
            'ñ' => "\x96",
            'ó' => "\x97",
            'ò' => "\x98",
            'ô' => "\x99",
            'ö' => "\x9A",
            'õ' => "\x9B",
            'ú' => "\x9C",
            'ù' => "\x9D",
            'û' => "\x9E",
            'ü' => "\x9F",
            '†' => "\xA0",
            '°' => "\xA1",
            '¢' => "\xA2",
            '£' => "\xA3",
            '§' => "\xA4",
            '•' => "\xA5",
            '¶' => "\xA6",
            'ß' => "\xA7",
            '®' => "\xA8",
            '©' => "\xA9",
            '™' => "\xAA",
            '´' => "\xAB",
            '¨' => "\xAC",
            'Æ' => "\xAE",
            'Ø' => "\xAF",
            '±' => "\xB1",
            '¥' => "\xB4",
            'µ' => "\xB5",
            'ª' => "\xBB",
            'º' => "\xBC",
            'æ' => "\xBE",
            'ø' => "\xBF",
        ];
    }

    /**
     * @return array<int, int>
     */
    private function buildTableOfContentsPageNumbers(
        int $firstTocPageIndex,
        int $tocPageCount,
        TableOfContentsPosition $position,
    ): array {
        $pageNumbersByObjectId = [];
        $pageNumberOffset = $position === TableOfContentsPosition::START ? $tocPageCount : 0;

        foreach (array_slice($this->pages->pages, 0, $firstTocPageIndex) as $index => $documentPage) {
            $pageNumbersByObjectId[$documentPage->id] = $index + 1 + $pageNumberOffset;
        }

        return $pageNumbersByObjectId;
    }

    private function estimateTableOfContentsPageCount(
        int $entryCount,
        float $initialY,
        float $pageHeight,
        float $margin,
        float $entryLineHeight,
    ): int {
        $pageCount = 1;
        $currentY = $initialY;

        for ($index = 0; $index < $entryCount; $index++) {
            if ($currentY < $margin + $entryLineHeight) {
                $pageCount++;
                $currentY = $pageHeight - $margin;
            }

            $currentY -= $entryLineHeight;
        }

        return $pageCount;
    }

    private function fitTextToWidth(Page $page, string $text, string $baseFont, int $size, float $maxWidth): string
    {
        if ($page->measureTextWidth($text, $baseFont, $size) <= $maxWidth) {
            return $text;
        }

        $ellipsis = '...';
        $ellipsisWidth = $page->measureTextWidth($ellipsis, $baseFont, $size);

        if ($ellipsisWidth > $maxWidth) {
            return $ellipsis;
        }

        $characters = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $current = '';

        foreach ($characters as $character) {
            $candidate = $current . $character;

            if ($page->measureTextWidth($candidate . $ellipsis, $baseFont, $size) > $maxWidth) {
                break;
            }

            $current = $candidate;
        }

        return rtrim($current) . $ellipsis;
    }

    /**
     * @return array{string, string}
     */
    private function generateDocumentId(): array
    {
        try {
            $value = bin2hex(random_bytes(16));
        } catch (RandomException) {
            $value = md5(uniqid((string) mt_rand(), true));
        }

        return [$value, $value];
    }
}
