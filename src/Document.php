<?php

declare(strict_types=1);

namespace Kalle\Pdf;

use DateTimeImmutable;
use Kalle\Pdf\Internal\Document\Attachment\AssociatedFileRelationship;
use Kalle\Pdf\Internal\Document\Attachment\FileSpecification;
use Kalle\Pdf\Internal\Document\Document as InternalDocument;
use Kalle\Pdf\Internal\Document\OptionalContent\OptionalContentGroup;
use Kalle\Pdf\Internal\Document\TableOfContents\TableOfContentsOptions;
use Kalle\Pdf\Internal\Layout\Geometry\Position;
use Kalle\Pdf\Internal\Layout\Page\PageSize;
use Kalle\Pdf\Internal\Page\Page as InternalPage;
use Kalle\Pdf\Internal\Security\EncryptionOptions;

/**
 * Public entry point for building and rendering PDF documents.
 */
final readonly class Document
{
    private InternalDocument $document;

    /**
     * Creates a new PDF document with metadata and optional font presets.
     *
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
        ?string $title = null,
        ?string $author = null,
        ?string $subject = null,
        ?string $language = null,
        ?string $creator = null,
        ?string $creatorTool = null,
        ?array $fontConfig = null,
    ) {
        $this->document = new InternalDocument(
            profile: $profile,
            title: $title,
            author: $author,
            subject: $subject,
            language: $language,
            creator: $creator,
            creatorTool: $creatorTool,
            fontConfig: $fontConfig,
        );
    }

    public function getProfile(): Profile
    {
        return $this->document->getProfile();
    }

    public function getCreationDate(): DateTimeImmutable
    {
        return $this->document->getCreationDate();
    }

    public function getModificationDate(): DateTimeImmutable
    {
        return $this->document->getModificationDate();
    }

    public function getCreator(): string
    {
        return $this->document->getCreator();
    }

    public function setCreator(string $creator): self
    {
        $this->document->setCreator($creator);

        return $this;
    }

    public function getProducer(): string
    {
        return $this->document->getProducer();
    }

    public function setProducer(string $producer): self
    {
        $this->document->setProducer($producer);

        return $this;
    }

    public function getCreatorTool(): string
    {
        return $this->document->getCreatorTool();
    }

    public function setCreatorTool(string $creatorTool): self
    {
        $this->document->setCreatorTool($creatorTool);

        return $this;
    }

    /**
     * Adds a page and returns the public page facade.
     */
    public function addPage(?PageSize $size = null): Page
    {
        return $this->toPublicPage($this->document->addPage($size ?? PageSize::A4()));
    }

    /**
     * Adds an outline entry that points to the given page.
     */
    public function addOutline(string $title, Page $page): self
    {
        $this->document->addOutline($title, $this->toInternalPage($page));

        return $this;
    }

    /**
     * Registers a named destination for the given page.
     */
    public function addDestination(string $name, Page $page): self
    {
        $this->document->addDestination($name, $this->toInternalPage($page));

        return $this;
    }

    /**
     * Enables PDF encryption for the rendered output.
     */
    public function encrypt(EncryptionOptions $options): self
    {
        $this->document->encrypt($options);

        return $this;
    }

    /**
     * Registers an optional content layer.
     */
    public function addLayer(string $name, bool $visibleByDefault = true): OptionalContentGroup
    {
        return $this->document->addLayer($name, $visibleByDefault);
    }

    /**
     * Adds an embedded file attachment.
     */
    public function addAttachment(
        string $filename,
        string $contents,
        ?string $description = null,
        ?string $mimeType = null,
        ?AssociatedFileRelationship $afRelationship = null,
    ): self {
        $this->document->addAttachment($filename, $contents, $description, $mimeType, $afRelationship);

        return $this;
    }

    /**
     * Adds an embedded file attachment from the filesystem.
     */
    public function addAttachmentFromFile(
        string $path,
        ?string $filename = null,
        ?string $description = null,
        ?string $mimeType = null,
        ?AssociatedFileRelationship $afRelationship = null,
    ): self {
        $this->document->addAttachmentFromFile($path, $filename, $description, $mimeType, $afRelationship);

        return $this;
    }

    /**
     * Returns a previously added attachment by filename.
     */
    public function getAttachment(string $filename): ?FileSpecification
    {
        return $this->document->getAttachment($filename);
    }

    /**
     * Adds a header renderer that runs for newly created pages.
     *
     * @param callable(Page, int): void $renderer
     */
    public function addHeader(callable $renderer): self
    {
        $this->document->addHeader(
            function (InternalPage $page, int $pageNumber) use ($renderer): void {
                $renderer($this->toPublicPage($page), $pageNumber);
            },
        );

        return $this;
    }

    /**
     * Adds a footer renderer that runs for newly created pages.
     *
     * @param callable(Page, int): void $renderer
     */
    public function addFooter(callable $renderer): self
    {
        $this->document->addFooter(
            function (InternalPage $page, int $pageNumber) use ($renderer): void {
                $renderer($this->toPublicPage($page), $pageNumber);
            },
        );

        return $this;
    }

    /**
     * Adds automatic page numbers to all pages.
     */
    public function addPageNumbers(
        Position $position,
        string $baseFont = 'Helvetica',
        int $size = 10,
        string $template = 'Seite {{page}} von {{pages}}',
        bool $footer = true,
        bool $useLogicalPageNumbers = false,
    ): self {
        $this->document->addPageNumbers($position, $baseFont, $size, $template, $footer, $useLogicalPageNumbers);

        return $this;
    }

    public function excludePageFromNumbering(Page $page): self
    {
        $this->document->excludePageFromNumbering($this->toInternalPage($page));

        return $this;
    }

    /**
     * Registers a font for subsequent page operations.
     */
    public function registerFont(
        string $fontName,
        string $subtype = 'Type1',
        ?string $encoding = null,
        bool $unicode = false,
        ?string $fontFilePath = null,
    ): self {
        $this->document->registerFont($fontName, $subtype, $encoding, $unicode, $fontFilePath);

        return $this;
    }

    /**
     * Generates a table of contents page and returns its public page facade.
     */
    public function addTableOfContents(
        ?PageSize $size = null,
        ?TableOfContentsOptions $options = null,
    ): Page {
        return $this->toPublicPage($this->document->addTableOfContents(
            $size,
            $options,
        ));
    }

    /**
     * Adds a keyword to the document metadata.
     */
    public function addKeyword(string $keyword): self
    {
        $this->document->addKeyword($keyword);

        return $this;
    }

    /**
     * @param resource $stream
     */
    public function writeToStream($stream): void
    {
        $this->document->writeToStream($stream);
    }

    public function writeToFile(string $path): void
    {
        $this->document->writeToFile($path);
    }

    private function toInternalPage(Page $page): InternalPage
    {
        return $page->toInternalPage();
    }

    private function toPublicPage(InternalPage $page): Page
    {
        return new Page($page);
    }
}
