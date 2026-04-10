<?php

declare(strict_types=1);

namespace Kalle\Pdf\Internal\Document;

use DateTimeImmutable;
use InvalidArgumentException;
use Kalle\Pdf\Feature\Form\AcroForm;
use Kalle\Pdf\Feature\OptionalContent\OptionalContentGroup;
use Kalle\Pdf\Feature\Outline\OutlineRoot;
use Kalle\Pdf\Feature\Text\StructureTag;
use Kalle\Pdf\Font\FontDefinition;
use Kalle\Pdf\Geometry\Position;
use Kalle\Pdf\Internal\Document\DocumentAcroFormManager;
use Kalle\Pdf\Internal\Document\DocumentAttachmentManager;
use Kalle\Pdf\Internal\Document\DocumentEncryptionManager;
use Kalle\Pdf\Internal\Document\DocumentFontFactory;
use Kalle\Pdf\Internal\Document\DocumentFontRegistry;
use Kalle\Pdf\Internal\Document\DocumentMetadataManager;
use Kalle\Pdf\Internal\Document\DocumentNavigationManager;
use Kalle\Pdf\Internal\Document\DocumentOptionalContentManager;
use Kalle\Pdf\Internal\Document\DocumentStructureManager;
use Kalle\Pdf\Internal\Document\Preparation\DocumentDeferredRendering;
use Kalle\Pdf\Internal\Document\Preparation\DocumentPageDecoratorManager;
use Kalle\Pdf\Internal\Document\Preparation\DocumentProfileGuard;
use Kalle\Pdf\Internal\Document\Preparation\DocumentTableOfContentsBuilder;
use Kalle\Pdf\Internal\Document\Serialization\DocumentObjectCollector;
use Kalle\Pdf\Internal\Document\Serialization\DocumentPdfWriter;
use Kalle\Pdf\Internal\Encryption\Profile\EncryptionProfile;
use Kalle\Pdf\Internal\Encryption\Standard\StandardSecurityHandlerData;
use Kalle\Pdf\Internal\Page\Page;
use Kalle\Pdf\Internal\Security\EncryptionAlgorithm;
use Kalle\Pdf\Internal\Security\EncryptionOptions;
use Kalle\Pdf\Layout\PageSize;
use Kalle\Pdf\Layout\TableOfContentsOptions;
use Kalle\Pdf\Model\Document\AssociatedFileRelationship;
use Kalle\Pdf\Model\Document\Catalog;
use Kalle\Pdf\Model\Document\EncryptDictionary;
use Kalle\Pdf\Model\Document\FileSpecification;
use Kalle\Pdf\Model\Document\IccProfileStream;
use Kalle\Pdf\Model\Document\Info;
use Kalle\Pdf\Model\Document\Pages;
use Kalle\Pdf\Model\Document\XmpMetadata;
use Kalle\Pdf\Object\IndirectObject;
use Kalle\Pdf\Profile;
use Kalle\Pdf\Render\AtomicFilePdfOutput;
use Kalle\Pdf\Render\PdfOutput;
use Kalle\Pdf\Render\StreamPdfOutput;
use Kalle\Pdf\Render\StringPdfOutput;
use Kalle\Pdf\Structure\ParentTree;
use Kalle\Pdf\Structure\StructElem;
use Kalle\Pdf\Structure\StructTreeRoot;
use Random\RandomException;
use RuntimeException;
use Throwable;

class Document
{
    private const string PACKAGE_NAME = 'kalle/pdf';
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
    private DocumentDeferredRendering $deferredRendering;

    /** @var array<int, FontDefinition&IndirectObject> */
    private array $fonts = [];

    /** @var list<string> */
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
    private ?DocumentMetadataManager $documentMetadataManager = null;
    private ?DocumentNavigationManager $documentNavigationManager = null;
    private ?DocumentOptionalContentManager $documentOptionalContentManager = null;
    private ?DocumentAttachmentManager $documentAttachmentManager = null;
    private ?DocumentEncryptionManager $documentEncryptionManager = null;
    private ?DocumentPageDecoratorManager $documentPageDecoratorManager = null;
    private ?DocumentStructureManager $documentStructureManager = null;
    private ?DocumentProfileGuard $documentProfileGuard = null;
    private ?DocumentFontFactory $documentFontFactory = null;
    private ?DocumentFontRegistry $documentFontRegistry = null;
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
        $this->deferredRendering = new DocumentDeferredRendering();

        $this->info = new Info(++$this->objectId, $this);
        $this->creationDate = new DateTimeImmutable();
        $this->modificationDate = $this->creationDate;
        $this->creator = $creator !== null && $creator !== '' ? $creator : self::PACKAGE_NAME;
        $this->creatorTool = $creatorTool !== null && $creatorTool !== '' ? $creatorTool : self::PACKAGE_NAME;
        $this->producer = DocumentMetadataManager::resolveDefaultProducer(self::PACKAGE_NAME);
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

    /**
     * @return iterable<IndirectObject>
     */
    public function iterateDocumentObjects(): iterable
    {
        return new DocumentObjectCollector($this, array_values($this->structElems));
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
        return $this->documentMetadataManager()->getPdfAOutputIntentProfile();
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

    /**
     * @return list<Page>
     */
    public function getPages(): array
    {
        return array_values($this->pages->pages);
    }

    public function getDeferredRendering(): DocumentDeferredRendering
    {
        return $this->deferredRendering;
    }

    public function getModificationDate(): DateTimeImmutable
    {
        return $this->modificationDate;
    }

    public function getCreator(): string
    {
        return $this->documentMetadataManager()->getCreator();
    }

    public function setCreator(string $creator): self
    {
        $this->documentMetadataManager()->setCreator($creator);

        return $this;
    }

    public function getProducer(): string
    {
        return $this->documentMetadataManager()->getProducer();
    }

    public function setProducer(string $producer): self
    {
        $this->documentMetadataManager()->setProducer($producer);

        return $this;
    }

    public function getCreatorTool(): string
    {
        return $this->documentMetadataManager()->getCreatorTool();
    }

    public function setCreatorTool(string $creatorTool): self
    {
        $this->documentMetadataManager()->setCreatorTool($creatorTool);

        return $this;
    }

    public function getXmpMetadata(): ?XmpMetadata
    {
        return $this->documentMetadataManager()->getXmpMetadata();
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

        return $this->pages->addPage(
            ++$this->objectId,
            ++$this->objectId,
            ++$this->objectId,
            $this->getNextStructParentId(),
            $width,
            $height,
        );
    }

    public function getNextStructParentId(): int
    {
        return ++$this->structParentId;
    }

    public function addOutline(string $title, Page $page): self
    {
        $this->documentNavigationManager()->addOutline($title, $page);

        return $this;
    }

    public function addDestination(string $name, Page $page): self
    {
        $this->documentNavigationManager()->addDestination($name, $page);

        return $this;
    }

    public function encrypt(EncryptionOptions $options): self
    {
        $this->documentEncryptionManager()->encrypt($options);

        return $this;
    }

    public function getEncryptionProfile(): ?EncryptionProfile
    {
        return $this->documentEncryptionManager()->getEncryptionProfile();
    }

    public function getEncryptionOptions(): ?EncryptionOptions
    {
        return $this->documentEncryptionManager()->getEncryptionOptions();
    }

    public function getSecurityHandlerData(): ?StandardSecurityHandlerData
    {
        return $this->documentEncryptionManager()->getSecurityHandlerData();
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
        return $this->documentNavigationManager()->getDestinations();
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
        $this->documentFontRegistry()->registerFont(
            $fontName,
            $subtype,
            $encoding,
            $unicode,
            $fontFilePath,
        );

        return $this;
    }

    public function getFontByBaseFont(string $baseFont): ?FontDefinition
    {
        return $this->documentFontRegistry()->getFontByBaseFont($baseFont);
    }

    /**
     * @return array<int, FontDefinition&IndirectObject>
     */
    public function getFonts(): array
    {
        return $this->documentFontRegistry()->getFonts();
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
        $this->deferredRendering->registerRenderFinalizer($finalizer);
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

    public function addTableOfContents(
        ?PageSize $size = null,
        ?TableOfContentsOptions $options = null,
    ): Page {
        return (new DocumentTableOfContentsBuilder($this, $this->excludedPageIdsFromNumbering))
            ->addTableOfContents($size, $options);
    }

    public function addKeyword(string $keyword): self
    {
        $this->documentMetadataManager()->addKeyword($keyword);

        return $this;
    }

    /**
     * @return string[]
     */
    public function getKeywords(): array
    {
        return $this->documentMetadataManager()->getKeywords();
    }

    public function shouldWriteInfoDictionary(): bool
    {
        return $this->profile->writesInfoDictionary();
    }

    public function render(): string
    {
        $output = new StringPdfOutput();
        $this->writeToOutput($output);

        return $output->contents();
    }

    /**
     * @param resource $stream
     */
    public function writeToStream($stream): void
    {
        $this->writeToOutput(new StreamPdfOutput($stream));
    }

    public function writeToFile(string $path): void
    {
        $output = new AtomicFilePdfOutput($path);

        try {
            $this->writeToOutput($output);
            $output->commit();
        } catch (Throwable $exception) {
            $output->discard();

            throw $exception;
        }
    }

    private function documentProfileGuard(): DocumentProfileGuard
    {
        return $this->documentProfileGuard ??= new DocumentProfileGuard($this);
    }

    private function documentAcroFormManager(): DocumentAcroFormManager
    {
        return $this->documentAcroFormManager ??= new DocumentAcroFormManager(
            $this,
            $this->documentProfileGuard(),
        );
    }

    private function documentMetadataManager(): DocumentMetadataManager
    {
        return $this->documentMetadataManager ??= new DocumentMetadataManager(
            $this,
            $this->creator,
            $this->creatorTool,
            $this->producer,
            $this->pdfaOutputIntentProfile,
            $this->xmpMetadata,
            $this->keywords,
        );
    }

    private function documentNavigationManager(): DocumentNavigationManager
    {
        return $this->documentNavigationManager ??= new DocumentNavigationManager($this, $this->destinations);
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
        return $this->documentAttachmentManager ??= new DocumentAttachmentManager(
            $this,
            $this->attachments,
            $this->documentProfileGuard(),
        );
    }

    private function documentEncryptionManager(): DocumentEncryptionManager
    {
        return $this->documentEncryptionManager ??= new DocumentEncryptionManager(
            $this,
            $this->encryptionProfile,
            $this->encryptionOptions,
            $this->securityHandlerData,
        );
    }

    private function documentPageDecoratorManager(): DocumentPageDecoratorManager
    {
        return $this->documentPageDecoratorManager ??= new DocumentPageDecoratorManager(
            $this,
            $this->deferredRendering,
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

    private function documentFontRegistry(): DocumentFontRegistry
    {
        return $this->documentFontRegistry ??= new DocumentFontRegistry(
            $this->fonts,
            $this->documentFontFactory(),
            $this->documentProfileGuard(),
        );
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

    private function writeToOutput(PdfOutput $output): void
    {
        (new DocumentPdfWriter())->write($this, $output);
    }
}
