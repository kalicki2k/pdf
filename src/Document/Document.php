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
use Kalle\Pdf\Font\FontDefinition;
use Kalle\Pdf\Layout\PageSize;
use Kalle\Pdf\Layout\TableOfContentsOptions;
use Kalle\Pdf\Object\IndirectObject;
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
    /** @var list<callable(): void> */
    private array $deferredRenderFinalizers = [];

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
    private ?DocumentAcroFormManager $documentAcroFormManager = null;
    private ?DocumentOptionalContentManager $documentOptionalContentManager = null;
    private ?DocumentAttachmentManager $documentAttachmentManager = null;
    private ?DocumentPageDecoratorManager $documentPageDecoratorManager = null;
    private ?DocumentStructureManager $documentStructureManager = null;
    private ?DocumentProfileGuard $documentProfileGuard = null;
    private ?DocumentFontFactory $documentFontFactory = null;
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
        return new DocumentObjectCollector($this, array_values($this->structElems))->collect();
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
        return $this->documentOptionalContentManager()->ensureOptionalContentGroup($name, $visibleByDefault);
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
        $this->documentAttachmentManager()->addAttachment(
            $filename,
            $contents,
            $description,
            $mimeType,
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
        $this->documentAttachmentManager()->addAttachmentFromFile(
            $path,
            $filename,
            $description,
            $mimeType,
            $afRelationship,
        );

        return $this;
    }

    /**
     * @return list<FileSpecification>
     */
    public function getAttachments(): array
    {
        return $this->documentAttachmentManager()->getAttachments();
    }

    public function getAttachment(string $filename): ?FileSpecification
    {
        return $this->documentAttachmentManager()->getAttachment($filename);
    }

    /**
     * @return list<OptionalContentGroup>
     */
    public function getOptionalContentGroups(): array
    {
        return $this->documentOptionalContentManager()->getOptionalContentGroups();
    }

    /**
     * @param callable(Page, int): void $renderer
     */
    public function addHeader(callable $renderer): self
    {
        $this->documentPageDecoratorManager()->addHeader($renderer);

        return $this;
    }

    /**
     * @param callable(Page, int): void $renderer
     */
    public function addFooter(callable $renderer): self
    {
        $this->documentPageDecoratorManager()->addFooter($renderer);

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
        $this->documentPageDecoratorManager()->addPageNumbers(
            $position,
            $baseFont,
            $size,
            $template,
            $footer,
            $useLogicalPageNumbers,
        );

        return $this;
    }

    public function excludePageFromNumbering(Page $page): self
    {
        $this->documentPageDecoratorManager()->excludePageFromNumbering($page);

        return $this;
    }

    public function registerFont(
        string $fontName,
        string $subtype = 'Type1',
        ?string $encoding = null,
        bool $unicode = false,
        ?string $fontFilePath = null,
    ): self {
        $options = $this->documentFontFactory()->resolveRegistrationOptions(
            $fontName,
            $subtype,
            $encoding,
            $unicode,
            $fontFilePath,
        );
        $this->assertAllowsFontRegistration($options);
        $this->fonts = [
            ...$this->fonts,
            $this->documentFontFactory()->createFont($options),
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
        return $this->documentAcroFormManager()->ensureAcroForm();
    }

    public function ensureTextFieldAcroForm(): AcroForm
    {
        return $this->documentAcroFormManager()->ensureTextFieldAcroForm();
    }

    public function ensureCheckboxAcroForm(): AcroForm
    {
        return $this->documentAcroFormManager()->ensureCheckboxAcroForm();
    }

    public function ensurePushButtonAcroForm(): AcroForm
    {
        return $this->documentAcroFormManager()->ensurePushButtonAcroForm();
    }

    public function ensureRadioButtonAcroForm(): AcroForm
    {
        return $this->documentAcroFormManager()->ensureRadioButtonAcroForm();
    }

    public function ensureComboBoxAcroForm(): AcroForm
    {
        return $this->documentAcroFormManager()->ensureComboBoxAcroForm();
    }

    public function ensureListBoxAcroForm(): AcroForm
    {
        return $this->documentAcroFormManager()->ensureListBoxAcroForm();
    }

    public function ensureSignatureFieldAcroForm(): AcroForm
    {
        return $this->documentAcroFormManager()->ensureSignatureFieldAcroForm();
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
        $this->documentStructureManager()->addStructElem($tag, $markedContentId, $page);

        return $this;
    }

    public function createStructElem(
        StructureTag $tag,
        ?int $markedContentId = null,
        ?Page $page = null,
        ?StructElem $parent = null,
    ): StructElem {
        return $this->documentStructureManager()->createStructElem($tag, $markedContentId, $page, $parent);
    }

    public function registerObjectStructElem(int $structParentId, StructElem $structElem): void
    {
        $this->documentStructureManager()->registerObjectStructElem($structParentId, $structElem);
    }

    public function registerMarkedContentStructElem(int $structParentId, StructElem $structElem): void
    {
        $this->documentStructureManager()->registerMarkedContentStructElem($structParentId, $structElem);
    }

    public function registerDeferredRenderFinalizer(callable $finalizer): void
    {
        $this->deferredRenderFinalizers[] = $finalizer;
    }

    public function ensureStructureEnabled(): void
    {
        $this->documentStructureManager()->ensureStructureEnabled();
    }

    public function hasStructure(): bool
    {
        return $this->structTreeRoot !== null;
    }

    public function isRenderingArtifactContext(): bool
    {
        return $this->renderingArtifactContext;
    }

    /**
     * @param callable(): void $renderer
     */
    public function renderInArtifactContext(callable $renderer): void
    {
        $previousArtifactContext = $this->renderingArtifactContext;
        $this->renderingArtifactContext = true;

        try {
            $renderer();
        } finally {
            $this->renderingArtifactContext = $previousArtifactContext;
        }
    }

    public function assertAllowsEncryptionAlgorithm(EncryptionAlgorithm $algorithm): void
    {
        $this->documentProfileGuard()->assertAllowsEncryptionAlgorithm($algorithm);
    }

    public function assertAllowsAttachments(): void
    {
        $this->documentProfileGuard()->assertAllowsAttachments();
    }

    public function assertAllowsOptionalContentGroups(): void
    {
        $this->documentProfileGuard()->assertAllowsOptionalContentGroups();
    }

    public function assertAllowsTransparency(): void
    {
        $this->documentProfileGuard()->assertAllowsTransparency();
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
        $this->documentProfileGuard()->assertAllowsFontRegistration($options);
    }

    public function addTableOfContents(
        ?PageSize $size = null,
        ?TableOfContentsOptions $options = null,
    ): Page {
        return (new DocumentTableOfContentsBuilder($this, $this->excludedPageIdsFromNumbering))
            ->addTableOfContents($size, $options);
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
        $renderLifecycle = new DocumentRenderLifecycle();
        $renderLifecycle->applyDeferredRenderFinalizers($this->deferredRenderFinalizers);
        $renderLifecycle->applyDeferredPageDecorators(
            $this->deferredHeaderRenderers,
            $this->deferredFooterRenderers,
            array_values($this->pages->pages),
            function (callable $renderer): void {
                $this->renderInArtifactContext($renderer);
            },
        );
        $renderLifecycle->assertRenderRequirements(
            $this->profile,
            $this->title,
            $this->language,
            $this->structTreeRoot !== null,
        );

        $renderer = new PdfRenderer();

        return $renderer->render($this);
    }

    private function documentProfileGuard(): DocumentProfileGuard
    {
        return $this->documentProfileGuard ??= new DocumentProfileGuard($this);
    }

    private function documentAcroFormManager(): DocumentAcroFormManager
    {
        return $this->documentAcroFormManager ??= new DocumentAcroFormManager($this);
    }

    private function documentOptionalContentManager(): DocumentOptionalContentManager
    {
        return $this->documentOptionalContentManager ??= new DocumentOptionalContentManager(
            $this,
            $this->optionalContentGroups,
        );
    }

    private function documentAttachmentManager(): DocumentAttachmentManager
    {
        return $this->documentAttachmentManager ??= new DocumentAttachmentManager($this, $this->attachments);
    }

    private function documentPageDecoratorManager(): DocumentPageDecoratorManager
    {
        return $this->documentPageDecoratorManager ??= new DocumentPageDecoratorManager(
            $this,
            $this->deferredHeaderRenderers,
            $this->deferredFooterRenderers,
            $this->excludedPageIdsFromNumbering,
        );
    }

    private function documentStructureManager(): DocumentStructureManager
    {
        return $this->documentStructureManager ??= new DocumentStructureManager($this, $this->structElems);
    }

    private function documentFontFactory(): DocumentFontFactory
    {
        return $this->documentFontFactory ??= new DocumentFontFactory($this);
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
