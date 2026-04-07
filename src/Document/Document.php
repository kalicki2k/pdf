<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use Composer\InstalledVersions;
use DateTimeImmutable;
use InvalidArgumentException;
use Kalle\Pdf\Document\Form\AcroForm;
use Kalle\Pdf\Document\Geometry\Position;
use Kalle\Pdf\Document\Outline\OutlineItem;
use Kalle\Pdf\Document\Outline\OutlineRoot;
use Kalle\Pdf\Document\Text\ParagraphOptions;
use Kalle\Pdf\Document\Text\StructureTag;
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
use Kalle\Pdf\Font\StandardFontName;
use Kalle\Pdf\Font\ToUnicodeCMap;
use Kalle\Pdf\Font\UnicodeFont;
use Kalle\Pdf\Font\UnicodeGlyphMap;
use Kalle\Pdf\Layout\PageSize;
use Kalle\Pdf\Layout\TableOfContentsLeaderStyle;
use Kalle\Pdf\Layout\TableOfContentsOptions;
use Kalle\Pdf\Layout\TableOfContentsPlacement;
use Kalle\Pdf\Layout\TableOfContentsStyle;
use Kalle\Pdf\Object\IndirectObject;
use Kalle\Pdf\PdfVersion;
use Kalle\Pdf\Profile;
use Kalle\Pdf\Render\PdfRenderer;
use Kalle\Pdf\Structure\ParentTree;
use Kalle\Pdf\Structure\StructElem;
use Kalle\Pdf\Structure\StructTreeRoot;
use Kalle\Pdf\Utilities\StringListNormalizer;
use Random\RandomException;

final class Document
{
    private const string PACKAGE_NAME = 'kalle/pdf';
    private const string PDFA_SRGB_ICC_PROFILE_PATH = __DIR__ . '/../../assets/color-srgb.icc';
    private const string PDFA1_SRGB_ICC_PROFILE_PATH = __DIR__ . '/../../assets/color-srgb-pdfa1.icc';
    private int $objectId = 0;
    private int $structParentId = -1;
    private readonly DateTimeImmutable $creationDate;
    private readonly DateTimeImmutable $modificationDate;
    private string $creator;
    private string $creatorTool;
    private string $producer;
    private ?EncryptionProfile $encryptionProfile = null;
    private ?EncryptionOptions $encryptionOptions = null;
    private ?StandardSecurityHandlerData $securityHandlerData = null;
    private ?IccProfileStream $pdfaOutputIntentProfile = null;
    private ?XmpMetadata $xmpMetadata = null;
    /** @var list<callable(Page, int, int): void> */
    private array $deferredHeaderRenderers = [];
    /** @var list<callable(Page, int, int): void> */
    private array $deferredFooterRenderers = [];

    /** @var array<int, FontDefinition&IndirectObject> */
    private array $fonts = [];

    /** @var string[] */
    private array $keywords = [];
    /** @var array<int, true> */
    private array $excludedPageIdsFromNumbering = [];
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
    private bool $renderingArtifactContext = false;
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
        Profile $profile,
        private readonly ?string $title = null,
        private readonly ?string $author = null,
        private readonly ?string $subject = null,
        private readonly ?string $language = null,
        ?string $creator = null,
        ?string $creatorTool = null,
        private readonly ?array $fontConfig = null,
    ) {
        $this->profile = $profile;
        $this->catalog = new Catalog(++$this->objectId, $this);
        $this->pages = new Pages(++$this->objectId, $this);

        $this->info = new Info(++$this->objectId, $this);
        $this->creationDate = new DateTimeImmutable();
        $this->modificationDate = $this->creationDate;
        $this->creator = $creator !== null && $creator !== '' ? $creator : self::PACKAGE_NAME;
        $this->creatorTool = $creatorTool !== null && $creatorTool !== '' ? $creatorTool : self::PACKAGE_NAME;
        $this->producer = self::resolveDefaultProducer();
        $this->documentId = $this->generateDocumentId();
    }

    private readonly Profile $profile;

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
        $xmpMetadata = $this->getXmpMetadata();
        $pdfaOutputIntentProfile = $this->getPdfAOutputIntentProfile();

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

        if ($pdfaOutputIntentProfile !== null) {
            $objects[] = $pdfaOutputIntentProfile;
        }

        foreach ($this->optionalContentGroups as $optionalContentGroup) {
            $objects[] = $optionalContentGroup;
        }

        foreach ($this->attachments as $attachment) {
            $objects[] = $attachment;
            $objects[] = $attachment->getEmbeddedFile();
        }

        if ($this->profile->writesInfoDictionary()) {
            $objects[] = $this->info;
        }

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

        if ($xmpMetadata !== null) {
            $objects[] = $xmpMetadata;
        }

        return $objects;
    }

    public function getCreationDate(): DateTimeImmutable
    {
        return $this->creationDate;
    }

    public function getVersion(): float
    {
        return $this->profile->version();
    }

    public function getProfile(): Profile
    {
        return $this->profile;
    }

    public function getPdfAOutputIntentProfile(): ?IccProfileStream
    {
        if (!$this->profile->usesPdfAOutputIntent()) {
            return null;
        }

        if ($this->pdfaOutputIntentProfile === null) {
            $this->pdfaOutputIntentProfile = IccProfileStream::fromPath(
                $this->getUniqObjectId(),
                $this->profile->pdfaPart() === 1
                    ? self::PDFA1_SRGB_ICC_PROFILE_PATH
                    : self::PDFA_SRGB_ICC_PROFILE_PATH,
            );
        }

        return $this->pdfaOutputIntentProfile;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function getAuthor(): ?string
    {
        return $this->author;
    }

    public function getSubject(): ?string
    {
        return $this->subject;
    }

    public function getLanguage(): ?string
    {
        return $this->language;
    }

    public function getModificationDate(): DateTimeImmutable
    {
        return $this->modificationDate;
    }

    public function getCreator(): string
    {
        return $this->creator;
    }

    public function setCreator(string $creator): self
    {
        if ($creator === '') {
            throw new InvalidArgumentException('Creator must not be empty.');
        }

        $this->creator = $creator;

        return $this;
    }

    public function getProducer(): string
    {
        return $this->producer;
    }

    public function setProducer(string $producer): self
    {
        if ($producer === '') {
            throw new InvalidArgumentException('Producer must not be empty.');
        }

        $this->producer = $producer;

        return $this;
    }

    public function getCreatorTool(): string
    {
        return $this->creatorTool;
    }

    public function setCreatorTool(string $creatorTool): self
    {
        if ($creatorTool === '') {
            throw new InvalidArgumentException('Creator tool must not be empty.');
        }

        $this->creatorTool = $creatorTool;

        return $this;
    }

    private static function resolveDefaultProducer(): string
    {
        $version = InstalledVersions::getPrettyVersion(self::PACKAGE_NAME);

        return is_string($version) && $version !== ''
            ? self::PACKAGE_NAME . ' ' . $version
            : self::PACKAGE_NAME;
    }

    public function getXmpMetadata(): ?XmpMetadata
    {
        if (!$this->profile->supportsXmpMetadata()) {
            return null;
        }

        if ($this->xmpMetadata === null) {
            $this->xmpMetadata = new XmpMetadata($this->getUniqObjectId(), $this);
        }

        return $this->xmpMetadata;
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

        return $this->pages->addPage(++$this->objectId, ++$this->objectId, ++$this->objectId, $this->getNextStructParentId(), $width, $height);
    }

    public function getNextStructParentId(): int
    {
        return ++$this->structParentId;
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
        $this->assertAllowsEncryptionAlgorithm($options->algorithm);

        $resolver = new EncryptionVersionResolver();
        $this->encryptionOptions = $options;
        $this->encryptionProfile = $resolver->resolve($this->getVersion(), $options->algorithm);
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
            $this->securityHandlerData = new StandardSecurityHandler()->build(
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
        $this->assertAllowsOptionalContentGroups();

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
        ?AssociatedFileRelationship $afRelationship = null,
    ): self {
        $this->assertAllowsAttachments();

        if ($filename === '') {
            throw new InvalidArgumentException('Attachment filename must not be empty.');
        }

        $afRelationship ??= $this->profile->defaultsAttachmentRelationshipToData()
            ? AssociatedFileRelationship::DATA
            : null;

        if ($afRelationship !== null) {
            $this->assertAllowsAssociatedFiles();
        }

        $embeddedFile = new EmbeddedFileStream($this->getUniqObjectId(), $contents, $mimeType);
        $this->attachments[] = new FileSpecification(
            $this->getUniqObjectId(),
            $filename,
            $embeddedFile,
            $description,
            $afRelationship,
        );

        return $this;
    }

    public function addAttachmentFromFile(
        string $path,
        ?string $filename = null,
        ?string $description = null,
        ?string $mimeType = null,
        ?AssociatedFileRelationship $afRelationship = null,
    ): self {
        if (!is_file($path)) {
            throw new InvalidArgumentException("Attachment file '$path' does not exist.");
        }

        $filename ??= basename($path);

        /** @var string|false $contents */
        $contents = @file_get_contents($path);

        if ($contents === false) {
            throw new InvalidArgumentException("Attachment file '$path' could not be read.");
        }

        return $this->addAttachment($filename, $contents, $description, $mimeType, $afRelationship);
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
        return array_find(
            $this->attachments,
            static fn (FileSpecification $attachment): bool => $attachment->getFilename() === $filename,
        );
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
        $this->deferredHeaderRenderers[] = static function (Page $page, int $pageNumber) use ($renderer): void {
            $renderer($page, $pageNumber);
        };

        return $this;
    }

    /**
     * @param callable(Page, int): void $renderer
     */
    public function addFooter(callable $renderer): self
    {
        $this->deferredFooterRenderers[] = static function (Page $page, int $pageNumber) use ($renderer): void {
            $renderer($page, $pageNumber);
        };

        return $this;
    }

    public function addPageNumbers(
        Position $position,
        string $baseFont = 'Helvetica',
        int $size = 10,
        string $template = 'Seite {{page}} von {{pages}}',
        bool $footer = true,
        bool $useLogicalPageNumbers = false,
    ): self {
        if ($template === '') {
            throw new InvalidArgumentException('Page number template must not be empty.');
        }

        if ($size <= 0) {
            throw new InvalidArgumentException('Page number size must be greater than zero.');
        }

        $renderer = function (Page $page, int $pageNumber, int $totalPages) use (
            $position,
            $baseFont,
            $size,
            $template,
            $useLogicalPageNumbers,
        ): void {
            if ($useLogicalPageNumbers) {
                $pageNumber = null;
                $logicalPageNumber = 0;

                foreach ($this->pages->pages as $documentPage) {
                    if (isset($this->excludedPageIdsFromNumbering[$documentPage->id])) {
                        if ($documentPage === $page) {
                            return;
                        }

                        continue;
                    }

                    $logicalPageNumber++;

                    if ($documentPage === $page) {
                        $pageNumber = $logicalPageNumber;
                        break;
                    }
                }

                $totalPages = $this->countLogicalPages();
            }

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

    public function excludePageFromNumbering(Page $page): self
    {
        $this->excludedPageIdsFromNumbering[$page->id] = true;

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
        $this->assertAllowsFontRegistration($options);
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
        return array_find(
            $this->fonts,
            static fn (FontDefinition $font): bool => $font->getBaseFont() === $baseFont,
        );
    }

    /**
     * @return array<int, FontDefinition&IndirectObject>
     */
    public function getFonts(): array
    {
        return $this->fonts;
    }

    public function ensureAcroForm(): AcroForm
    {
        $this->assertAllowsForms();

        if ($this->acroForm === null) {
            $this->acroForm = new AcroForm(++$this->objectId);
        }

        return $this->acroForm;
    }

    public function ensureTextFieldAcroForm(): AcroForm
    {
        if (!$this->profile->supportsCurrentTextFieldImplementation()) {
            throw new InvalidArgumentException(sprintf(
                'Profile %s does not allow AcroForm fields in the current implementation.',
                $this->profile->name(),
            ));
        }

        if ($this->acroForm === null) {
            $this->acroForm = new AcroForm(++$this->objectId);
        }

        return $this->acroForm;
    }

    public function ensureCheckboxAcroForm(): AcroForm
    {
        if (!$this->profile->supportsCurrentCheckboxImplementation()) {
            throw new InvalidArgumentException(sprintf(
                'Profile %s does not allow AcroForm fields in the current implementation.',
                $this->profile->name(),
            ));
        }

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
     * @param StructureTag $tag
     * @param int $markedContentId
     * @param Page|null $page
     * @return $this
     */
    public function addStructElem(StructureTag $tag, int $markedContentId, ?Page $page = null): self
    {
        $this->createStructElem($tag, $markedContentId, $page);

        return $this;
    }

    public function createStructElem(
        StructureTag $tag,
        ?int $markedContentId = null,
        ?Page $page = null,
        ?StructElem $parent = null,
    ): StructElem {
        $this->ensureStructureEnabled();

        $structElem = new StructElem(++$this->objectId, $tag->value);
        ($parent ?? $this->structElems['document'])->addKid($structElem);

        $this->structElems[] = $structElem;

        if ($markedContentId !== null && $page !== null) {
            $structElem->setMarkedContent($markedContentId, $page);
        }

        if ($markedContentId !== null && $page !== null && $this->parentTree !== null) {
            $this->parentTree->add($page->structParentId, $structElem);
        }

        return $structElem;
    }

    public function registerObjectStructElem(int $structParentId, StructElem $structElem): void
    {
        if ($this->parentTree === null) {
            return;
        }

        $this->parentTree->addObject($structParentId, $structElem);
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
     * @param list<callable(Page, int, int): void> $renderers
     */
    private function runDeferredPageDecorators(array $renderers, Page $page, int $pageNumber, int $totalPages): void
    {
        $previousArtifactContext = $this->renderingArtifactContext;
        $this->renderingArtifactContext = true;

        try {
            foreach ($renderers as $renderer) {
                $renderer($page, $pageNumber, $totalPages);
            }
        } finally {
            $this->renderingArtifactContext = $previousArtifactContext;
        }
    }

    public function ensureStructureEnabled(): void
    {
        if (!$this->profile->supportsStructure()) {
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

    public function isRenderingArtifactContext(): bool
    {
        return $this->renderingArtifactContext;
    }

    private function assertAllowsEncryption(): void
    {
        if (!$this->profile->supportsEncryption()) {
            throw new InvalidArgumentException(sprintf('Profile %s does not allow encryption.', $this->profile->name()));
        }
    }

    public function assertAllowsEncryptionAlgorithm(EncryptionAlgorithm $algorithm): void
    {
        $this->assertAllowsEncryption();

        if ($algorithm === EncryptionAlgorithm::RC4_40 && !$this->profile->supportsRc440Encryption()) {
            throw new InvalidArgumentException(sprintf(
                'PDF version %s does not allow RC4 40-bit encryption. PDF 1.3 or higher is required.',
                PdfVersion::format($this->getVersion()),
            ));
        }

        if ($algorithm === EncryptionAlgorithm::AES_128 && !$this->profile->supportsAes128Encryption()) {
            throw new InvalidArgumentException(sprintf(
                'PDF version %s does not allow AES-128 encryption. PDF 1.6 or higher is required.',
                PdfVersion::format($this->getVersion()),
            ));
        }

        if ($algorithm === EncryptionAlgorithm::AES_256 && !$this->profile->supportsAes256Encryption()) {
            throw new InvalidArgumentException(sprintf(
                'PDF version %s does not allow AES-256 encryption. PDF 1.7 or higher is required.',
                PdfVersion::format($this->getVersion()),
            ));
        }
    }

    public function assertAllowsAttachments(): void
    {
        if ($this->profile->supportsEmbeddedFileAttachments()) {
            return;
        }

        throw new InvalidArgumentException(sprintf('Profile %s does not allow embedded file attachments.', $this->profile->name()));
    }

    private function assertAllowsAssociatedFiles(): void
    {
        if ($this->profile->supportsAssociatedFiles()) {
            return;
        }

        throw new InvalidArgumentException(sprintf(
            'PDF version %s does not allow associated files. PDF 2.0 or a supporting archival profile is required.',
            PdfVersion::format($this->getVersion()),
        ));
    }

    public function assertAllowsOptionalContentGroups(): void
    {
        if (!$this->profile->supportsCurrentOptionalContentGroupImplementation()) {
            throw new InvalidArgumentException(sprintf('Profile %s does not allow optional content groups (layers).', $this->profile->name()));
        }

        if (!$this->profile->supportsOptionalContentGroups()) {
            throw new InvalidArgumentException(sprintf(
                'PDF version %s does not allow optional content groups (layers). PDF 1.5 or higher is required.',
                PdfVersion::format($this->getVersion()),
            ));
        }
    }

    public function assertAllowsTransparency(): void
    {
        if (!$this->profile->supportsCurrentTransparencyImplementation()) {
            throw new InvalidArgumentException(sprintf(
                'Profile %s does not allow transparency in the current implementation.',
                $this->profile->name(),
            ));
        }

        if (!$this->profile->supportsTransparency()) {
            throw new InvalidArgumentException(sprintf(
                'PDF version %s does not allow transparency. PDF 1.4 or higher is required.',
                PdfVersion::format($this->getVersion()),
            ));
        }
    }

    private function assertAllowsForms(): void
    {
        if (!$this->profile->supportsAcroForms()) {
            throw new InvalidArgumentException(sprintf(
                'Profile %s does not allow AcroForm fields in the current implementation.',
                $this->profile->name(),
            ));
        }
    }

    /**
     * @param array{
     *     baseFont: string,
     *     subtype: string,
     *     encoding: string,
     *     unicode: bool,
     *     fontFilePath: ?string
     * } $options
     */
    private function assertAllowsFontRegistration(array $options): void
    {
        if (!$options['unicode'] && $options['encoding'] === 'WinAnsiEncoding' && !$this->profile->supportsWinAnsiEncoding()) {
            throw new InvalidArgumentException(sprintf(
                'PDF version %s does not allow WinAnsiEncoding for standard fonts. PDF 1.1 or higher is required.',
                PdfVersion::format($this->getVersion()),
            ));
        }

        if (!$this->profile->requiresEmbeddedUnicodeFonts()) {
            return;
        }

        if (StandardFontName::isValid($options['baseFont']) && $options['fontFilePath'] === null) {
            throw new InvalidArgumentException(sprintf(
                "Profile %s does not allow PDF standard fonts like '%s'. Register an embedded Unicode font instead.",
                $this->profile->name(),
                $options['baseFont'],
            ));
        }

        if ($options['fontFilePath'] === null || !$options['unicode']) {
            throw new InvalidArgumentException(sprintf(
                "Profile %s requires embedded Unicode fonts in the current implementation. Font '%s' must provide an embedded font file and Unicode mapping.",
                $this->profile->name(),
                $options['baseFont'],
            ));
        }
    }

    private function assertRenderRequirements(): void
    {
        $this->assertRequiredDocumentTitle();
        $this->assertRequiredDocumentLanguage();
        $this->assertRequiredDocumentStructure();
    }

    private function assertRequiredDocumentTitle(): void
    {
        if (!$this->profile->requiresDocumentTitle()) {
            return;
        }

        if ($this->title !== null && $this->title !== '') {
            return;
        }

        throw new InvalidArgumentException(sprintf('Profile %s requires a document title.', $this->profile->name()));
    }

    private function assertRequiredDocumentLanguage(): void
    {
        if (!$this->profile->requiresDocumentLanguage()) {
            return;
        }

        if ($this->language !== null && $this->language !== '') {
            return;
        }

        throw new InvalidArgumentException(sprintf('Profile %s requires a document language.', $this->profile->name()));
    }

    private function assertRequiredDocumentStructure(): void
    {
        if (!$this->profile->requiresDocumentStructure()) {
            return;
        }

        if ($this->structTreeRoot !== null) {
            return;
        }

        throw new InvalidArgumentException(sprintf(
            'Profile %s requires tagged content in the current implementation.',
            $this->profile->name(),
        ));
    }

    public function addTableOfContents(
        ?PageSize $size = null,
        ?TableOfContentsOptions $options = null,
    ): Page {
        $options ??= new TableOfContentsOptions();
        $firstTocPageIndex = count($this->pages->pages);
        $insertionIndex = $options->placement->insertionIndex($firstTocPageIndex);
        $page = $this->addPage($size ?? PageSize::A4());
        $contentWidth = $page->getWidth() - ($options->margin * 2);

        if ($contentWidth <= 0) {
            throw new InvalidArgumentException('Table of contents content width must be greater than zero.');
        }

        $frame = $page->createTextFrame(
            new Position($options->margin, $page->getHeight() - $options->margin),
            $contentWidth,
            $options->margin,
        );
        $frame->addHeading(
            $options->title,
            $options->baseFont,
            $options->titleSize,
            new ParagraphOptions(structureTag: StructureTag::Heading1),
        );

        if ($this->outlineRoot === null || $this->outlineRoot->getItems() === []) {
            throw new InvalidArgumentException('Table of contents requires at least one outline entry.');
        }

        $entryLineHeight = ($options->entrySize * 1.35) + $options->style->entrySpacing;
        $currentPage = $page;
        $currentY = $frame->getCursorY();
        $pageNumbersByObjectId = $this->buildTableOfContentsPageNumbers(
            $firstTocPageIndex,
            $this->estimateTableOfContentsPageCount(
                count($this->outlineRoot->getItems()),
                $currentY,
                $page->getHeight(),
                $options->margin,
                $entryLineHeight,
            ),
            $insertionIndex,
            $options->useLogicalPageNumbers,
        );

        foreach ($this->outlineRoot->getItems() as $outlineItem) {
            if ($currentY < $options->margin + $entryLineHeight) {
                $currentPage = $this->addPage($page->getWidth(), $page->getHeight());
                $currentY = $currentPage->getHeight() - $options->margin;
            }

            $targetPage = $outlineItem->getPage();
            $pageNumber = $pageNumbersByObjectId[$targetPage->id] ?? null;

            if ($pageNumber === null) {
                continue;
            }

            $destinationName = 'toc-page-' . $targetPage->id;
            $this->addDestination($destinationName, $targetPage);

            $pageNumberText = (string) $pageNumber;
            $pageNumberWidth = $currentPage->measureTextWidth($pageNumberText, $options->baseFont, $options->entrySize);
            $entryWidth = $contentWidth - $pageNumberWidth - $options->style->pageNumberGap;
            $entryTitle = $this->fitTextToWidth(
                $currentPage,
                $outlineItem->getTitle(),
                $options->baseFont,
                $options->entrySize,
                $entryWidth,
            );
            $entryTitleWidth = $currentPage->measureTextWidth($entryTitle, $options->baseFont, $options->entrySize);
            $leaderText = $this->buildTableOfContentsLeaderText(
                $currentPage,
                $options->baseFont,
                $options->entrySize,
                max(0.0, $contentWidth - $entryTitleWidth - $pageNumberWidth - $options->style->pageNumberGap),
                $options->style,
            );

            $currentPage->addText(
                $entryTitle,
                new Position($options->margin, $currentY),
                $options->baseFont,
                $options->entrySize,
                new TextOptions(link: LinkTarget::namedDestination($destinationName)),
            );
            if ($leaderText !== '') {
                $currentPage->addText(
                    $leaderText,
                    new Position($options->margin + $entryTitleWidth + ($options->style->pageNumberGap / 2), $currentY),
                    $options->baseFont,
                    $options->entrySize,
                );
            }
            $currentPage->addText(
                $pageNumberText,
                new Position($page->getWidth() - $options->margin - $pageNumberWidth, $currentY),
                $options->baseFont,
                $options->entrySize,
                new TextOptions(link: LinkTarget::namedDestination($destinationName)),
            );

            $currentY -= $entryLineHeight;
        }

        if ($insertionIndex !== $firstTocPageIndex) {
            $tocPages = array_values(array_slice($this->pages->pages, $firstTocPageIndex));
            $this->pages->insertPagesAt($tocPages, $insertionIndex);
        }

        return $page;
    }

    private function buildTableOfContentsLeaderText(
        Page $page,
        string $baseFont,
        int $entrySize,
        float $leaderWidth,
        TableOfContentsStyle $style,
    ): string {
        if ($style->leaderStyle === TableOfContentsLeaderStyle::NONE || $leaderWidth <= 0.0) {
            return '';
        }

        $leaderCharacter = match ($style->leaderStyle) {
            TableOfContentsLeaderStyle::DOTS => '.',
            TableOfContentsLeaderStyle::DASHES => '-',
        };

        $characterWidth = max(0.0001, $page->measureTextWidth($leaderCharacter, $baseFont, $entrySize));
        $characterCount = max(3, (int) floor($leaderWidth / $characterWidth));

        return str_repeat($leaderCharacter, $characterCount);
    }

    public function addKeyword(string $keyword): self
    {
        $this->keywords = StringListNormalizer::unique([...$this->keywords, $keyword]);

        return $this;
    }

    /**
     * @return string[]
     */
    public function getKeywords(): array
    {
        return $this->keywords;
    }

    public function shouldWriteInfoDictionary(): bool
    {
        return $this->profile->writesInfoDictionary();
    }

    public function render(): string
    {
        $this->applyDeferredPageDecorators();
        $this->assertRenderRequirements();

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
        if (!$this->profile->supportsWinAnsiEncoding()) {
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
            $this->getVersion(),
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
        $fontData = @file_get_contents($fontFilePath);

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
        int $insertionIndex,
        bool $useLogicalPageNumbers,
    ): array {
        if ($useLogicalPageNumbers) {
            return $this->buildLogicalTableOfContentsPageNumbers($firstTocPageIndex, $tocPageCount, $insertionIndex);
        }

        $pageNumbersByObjectId = [];

        foreach (array_slice($this->pages->pages, 0, $firstTocPageIndex) as $index => $documentPage) {
            $pageNumbersByObjectId[$documentPage->id] = $index + 1 + ($index >= $insertionIndex ? $tocPageCount : 0);
        }

        return $pageNumbersByObjectId;
    }

    /**
     * @return array<int, int>
     */
    private function buildLogicalTableOfContentsPageNumbers(
        int $firstTocPageIndex,
        int $tocPageCount,
        int $insertionIndex,
    ): array {
        $pageNumbersByObjectId = [];
        $contentPages = array_values(array_slice($this->pages->pages, 0, $firstTocPageIndex));
        $logicalPageNumber = 0;
        $pageIndex = 0;
        $totalPageCount = $firstTocPageIndex + $tocPageCount;

        foreach ($contentPages as $documentPage) {
            while ($pageIndex >= $insertionIndex && $pageIndex < $insertionIndex + $tocPageCount) {
                $logicalPageNumber++;
                $pageIndex++;
            }

            $pageIndex++;

            if (isset($this->excludedPageIdsFromNumbering[$documentPage->id])) {
                continue;
            }

            $logicalPageNumber++;
            $pageNumbersByObjectId[$documentPage->id] = $logicalPageNumber;
        }

        while ($pageIndex < $totalPageCount) {
            $pageIndex++;
        }

        return $pageNumbersByObjectId;
    }

    private function countLogicalPages(): int
    {
        $logicalPageCount = 0;

        foreach ($this->pages->pages as $page) {
            if (isset($this->excludedPageIdsFromNumbering[$page->id])) {
                continue;
            }

            $logicalPageCount++;
        }

        return $logicalPageCount;
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
