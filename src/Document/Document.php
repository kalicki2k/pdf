<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use InvalidArgumentException;
use Kalle\Pdf\Encryption\EncryptionAlgorithm;
use Kalle\Pdf\Encryption\EncryptionOptions;
use Kalle\Pdf\Encryption\EncryptionProfile;
use Kalle\Pdf\Encryption\StandardSecurityHandler;
use Kalle\Pdf\Encryption\StandardSecurityHandlerData;
use Kalle\Pdf\Encryption\EncryptionVersionResolver;
use Kalle\Pdf\Font\CidFont;
use Kalle\Pdf\Font\CidToGidMap;
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
    /** @var array{string, string} */
    private array $documentId;

    /** @var StructElem[]  */
    private array $structElems = [];
    public Catalog $catalog;
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

        if ($this->structTreeRoot !== null) {
            $objects[] = $this->structTreeRoot;
        }

        if ($this->parentTree !== null) {
            $objects[] = $this->parentTree;
        }

        foreach ($this->structElems as $key => $structElem) {
            if ($key === 'document') {
                $objects[] = $structElem;
                continue;
            }

            $objects[] = $structElem;
        }

        if ($this->encryptDictionary !== null) {
            $objects[] = $this->encryptDictionary;
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

            $objects[] = $font;
        }
        foreach ($this->pages->pages as $page) {
            $objects[] = $page;
            foreach ($page->getAnnotations() as $annotation) {
                $objects[] = $annotation;
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

    public function useEncryptionAlgorithm(EncryptionAlgorithm $algorithm = EncryptionAlgorithm::AUTO): self
    {
        $resolver = new EncryptionVersionResolver();
        $this->encryptionProfile = $resolver->resolve($this->version, $algorithm);

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
        float $x,
        float $y,
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

        $renderer = static function (Page $page, int $pageNumber, int $totalPages) use ($x, $y, $baseFont, $size, $template): void {
            $page->addText(
                str_replace(
                    ['{{page}}', '{{pages}}'],
                    [(string) $pageNumber, (string) $totalPages],
                    $template,
                ),
                $x,
                $y,
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

    public function addFont(
        string $baseFont,
        string $subtype = 'Type1',
        string $encoding = 'WinAnsiEncoding',
        bool $unicode = false,
        ?string $fontFilePath = null,
    ): self {
        if (FontRegistry::has($baseFont, $this->fontConfig)) {
            $preset = FontRegistry::get($baseFont, $this->fontConfig);
            $baseFont = $preset->baseFont;
            $subtype = $preset->subtype;
            $encoding = $preset->encoding;
            $unicode = $preset->unicode;
            $fontFilePath = $preset->path;
        }

        if ($unicode) {
            $fontDescriptor = null;
            $glyphMap = new UnicodeGlyphMap();
            $fontParser = null;
            $defaultWidth = 1000;
            $widths = [];

            if ($fontFilePath !== null) {
                $fontFile = FontFileStream::fromPath(++$this->objectId, $fontFilePath);
                $fontDescriptor = new FontDescriptor(++$this->objectId, $baseFont, $fontFile);
                $fontParser = new OpenTypeFontParser($fontFile->data);

                if ($fontParser->hasCffOutlines()) {
                    $subtype = 'CIDFontType0';
                }
            }

            $cidToGidMap = $fontParser !== null
                ? new CidToGidMap(++$this->objectId, $glyphMap, $fontParser)
                : null;

            $descendantFont = new CidFont(
                ++$this->objectId,
                $baseFont,
                $subtype,
                fontDescriptor: $fontDescriptor,
                cidToGidMap: $cidToGidMap,
                defaultWidth: $defaultWidth,
                widths: $widths,
            );
            $toUnicode = new ToUnicodeCMap(++$this->objectId, $glyphMap);
            $font = new UnicodeFont(++$this->objectId, $descendantFont, $toUnicode, $glyphMap);
        } else {
            $fontParser = null;

            if ($fontFilePath !== null) {
                $fontData = file_get_contents($fontFilePath);

                if ($fontData === false) {
                    throw new \InvalidArgumentException("Unable to read font file '$fontFilePath'.");
                }

                $fontParser = new OpenTypeFontParser($fontData);
            }

            $font = new StandardFont(++$this->objectId, $baseFont, $subtype, $encoding, $this->version, $fontParser);
        }

        $this->fonts = [...$this->fonts, $font];

        return $this;
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
     * @param string $tag
     * @param int $markedContentId
     * @param Page|null $page
     * @return $this
     */
    public function addStructElem(string $tag, int $markedContentId, ?Page $page = null): self
    {
        $this->ensureStructureEnabled();

        $structElem = new StructElem(++$this->objectId, $tag);
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

        foreach ($this->headerRenderers as $headerRenderer) {
            $headerRenderer($page, $pageNumber);
        }

        foreach ($this->footerRenderers as $footerRenderer) {
            $footerRenderer($page, $pageNumber);
        }
    }

    private function applyDeferredPageDecorators(): void
    {
        if ($this->deferredHeaderRenderers === [] && $this->deferredFooterRenderers === []) {
            return;
        }

        $totalPages = count($this->pages->pages);

        foreach ($this->pages->pages as $index => $page) {
            $pageNumber = $index + 1;

            foreach ($this->deferredHeaderRenderers as $renderer) {
                $renderer($page, $pageNumber, $totalPages);
            }

            foreach ($this->deferredFooterRenderers as $renderer) {
                $renderer($page, $pageNumber, $totalPages);
            }
        }

        $this->deferredHeaderRenderers = [];
        $this->deferredFooterRenderers = [];
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
        $structElem = new StructElem(++$this->objectId, 'Document');
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

        $frame = $page->textFrame($margin, $page->getHeight() - $margin, $contentWidth, $margin);
        $frame->heading($title, $baseFont, $titleSize, 'H1');

        if ($this->outlineRoot === null || $this->outlineRoot->getItems() === []) {
            $frame->paragraph('Keine Eintraege vorhanden.', $baseFont, $entrySize, 'P');

            return $page;
        }

        $entryLineHeight = $entrySize * 1.35;
        $currentPage = $page;
        $currentY = $frame->getCursorY();
        $pageNumbersByObjectId = [];

        foreach ($this->pages->pages as $index => $documentPage) {
            if ($documentPage === $page) {
                continue;
            }

            $pageNumbersByObjectId[$documentPage->id] = $index + 1;
        }

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
                $margin,
                $currentY,
                $baseFont,
                $entrySize,
                null,
                link: '#' . $destinationName,
            );
            $currentPage->addText(
                $leaders,
                $margin + $entryTitleWidth + 4.0,
                $currentY,
                $baseFont,
                $entrySize,
            );
            $currentPage->addText(
                $pageNumberText,
                $page->getWidth() - $margin - $pageNumberWidth,
                $currentY,
                $baseFont,
                $entrySize,
                null,
                link: '#' . $destinationName,
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
