<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use Kalle\Pdf\Debug\Debugger;
use Kalle\Pdf\Document\Attachment\FileAttachment;
use Kalle\Pdf\Document\Form\AcroForm;
use Kalle\Pdf\Document\Metadata\PdfAOutputIntent;
use Kalle\Pdf\Document\TaggedPdf\TaggedList;
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
    public ?string $title;
    public ?string $author;
    public ?string $subject;
    public ?string $language;
    public ?string $creator;
    public ?string $creatorTool;
    public ?PdfAOutputIntent $pdfaOutputIntent;
    public ?Encryption $encryption;
    /** @var list<TaggedTable> */
    public array $taggedTables;
    /** @var list<TaggedTextBlock> */
    public array $taggedTextBlocks;
    /** @var list<FileAttachment> */
    public array $attachments;
    public ?AcroForm $acroForm;
    /** @var list<TaggedList> */
    public array $taggedLists;
    public Debugger $debugger;

    public static function make(): DocumentBuilder
    {
        return DefaultDocumentBuilder::make();
    }

    /**
     * @param list<Page>|null $pages
     * @param list<TaggedTable>|null $taggedTables
     * @param list<TaggedTextBlock>|null $taggedTextBlocks
     * @param list<FileAttachment>|null $attachments
     * @param AcroForm|null $acroForm
     * @param list<TaggedList>|null $taggedLists
     */
    public function __construct(
        ?Profile $profile = null,
        ?array $pages = null,
        ?string $title = null,
        ?string $author = null,
        ?string $subject = null,
        ?string $language = null,
        ?string $creator = null,
        ?string $creatorTool = null,
        ?PdfAOutputIntent $pdfaOutputIntent = null,
        ?Encryption $encryption = null,
        ?array $taggedTables = null,
        ?array $taggedTextBlocks = null,
        ?array $attachments = null,
        ?AcroForm $acroForm = null,
        ?array $taggedLists = null,
        ?Debugger $debugger = null,
    ) {
        $this->profile = $profile ?? Profile::standard();
        $this->pages = $pages ?? [new Page(PageSize::A4())];
        $this->title = $title;
        $this->author = $author;
        $this->subject = $subject;
        $this->language = $language;
        $this->creator = $creator;
        $this->creatorTool = $creatorTool;
        $this->pdfaOutputIntent = $pdfaOutputIntent;
        $this->encryption = $encryption;
        $this->taggedTables = $taggedTables ?? [];
        $this->taggedTextBlocks = $taggedTextBlocks ?? [];
        $this->attachments = $attachments ?? [];
        $this->acroForm = $acroForm;
        $this->taggedLists = $taggedLists ?? [];
        $this->debugger = $debugger ?? Debugger::disabled();
    }

    public function version(): float
    {
        return $this->profile->version();
    }
}
