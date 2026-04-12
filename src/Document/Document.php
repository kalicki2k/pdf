<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use Kalle\Pdf\Document\Metadata\PdfAOutputIntent;
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

    /**
     * @param list<Page>|null $pages
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
    }

    public function version(): float
    {
        return $this->profile->version();
    }
}
