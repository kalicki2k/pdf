<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use Kalle\Pdf\Debug\Debugger;
use Kalle\Pdf\Document\Attachment\FileAttachment;
use Kalle\Pdf\Document\Form\AcroForm;
use Kalle\Pdf\Document\Metadata\PdfAOutputIntent;
use Kalle\Pdf\Document\TaggedPdf\TaggedFigure;
use Kalle\Pdf\Document\TaggedPdf\TaggedList;
use Kalle\Pdf\Document\TaggedPdf\TaggedStructureElement;
use Kalle\Pdf\Document\TaggedPdf\TaggedTable;
use Kalle\Pdf\Document\TaggedPdf\TaggedTextBlock;
use Kalle\Pdf\Encryption\Encryption;
use Kalle\Pdf\Page\Page;
use Kalle\Pdf\Page\PageSize;

final readonly class Document
{
    public Profile $profile;
    /** @var list<Page> */
    public array $pages;
    /** @var list<TaggedFigure> */
    public array $taggedFigures;
    /** @var list<TaggedTable> */
    public array $taggedTables;
    /** @var list<TaggedTextBlock> */
    public array $taggedTextBlocks;
    /** @var list<FileAttachment> */
    public array $attachments;
    /** @var list<Outline> */
    public array $outlines;
    /** @var list<TaggedList> */
    public array $taggedLists;
    /** @var list<TaggedStructureElement> */
    public array $taggedStructureElements;
    /** @var list<string> */
    public array $taggedDocumentChildKeys;
    /** @var list<OptionalContentConfiguration> */
    public array $optionalContentConfigurations;
    public Debugger $debugger;

    public static function make(): DocumentBuilder
    {
        return DefaultDocumentBuilder::make();
    }

    /**
     * @param list<Page>|null $pages
     * @param list<TaggedFigure>|null $taggedFigures
     * @param list<TaggedTable>|null $taggedTables
     * @param list<TaggedTextBlock>|null $taggedTextBlocks
     * @param list<FileAttachment>|null $attachments
     * @param list<Outline>|null $outlines
     * @param AcroForm|null $acroForm
     * @param list<TaggedList>|null $taggedLists
     * @param list<TaggedStructureElement>|null $taggedStructureElements
     * @param list<string>|null $taggedDocumentChildKeys
     * @param list<OptionalContentConfiguration>|null $optionalContentConfigurations
     */
    public function __construct(
        ?Profile $profile = null,
        ?array $pages = null,
        public ?string $title = null,
        public ?string $author = null,
        public ?string $subject = null,
        public ?string $keywords = null,
        public ?string $language = null,
        public ?string $creator = null,
        public ?string $creatorTool = null,
        public ?PdfAOutputIntent $pdfaOutputIntent = null,
        public ?Encryption $encryption = null,
        ?array $taggedFigures = null,
        ?array $taggedTables = null,
        ?array $taggedTextBlocks = null,
        ?array $attachments = null,
        ?array $outlines = null,
        public ?AcroForm $acroForm = null,
        ?array $taggedLists = null,
        ?array $taggedStructureElements = null,
        ?array $taggedDocumentChildKeys = null,
        ?Debugger $debugger = null,
        ?array $optionalContentConfigurations = null,
    ) {
        $this->profile = $profile ?? Profile::standard();
        $this->pages = $pages ?? [new Page(PageSize::A4())];
        $this->taggedFigures = $taggedFigures ?? [];
        $this->taggedTables = $taggedTables ?? [];
        $this->taggedTextBlocks = $taggedTextBlocks ?? [];
        $this->attachments = $attachments ?? [];
        $this->outlines = $outlines ?? [];
        $this->taggedLists = $taggedLists ?? [];
        $this->taggedStructureElements = $taggedStructureElements ?? [];
        $this->taggedDocumentChildKeys = $taggedDocumentChildKeys ?? [];
        $this->optionalContentConfigurations = $optionalContentConfigurations ?? [];
        $this->debugger = $debugger ?? Debugger::disabled();
    }

    public function version(): float
    {
        return $this->profile->version();
    }
}
