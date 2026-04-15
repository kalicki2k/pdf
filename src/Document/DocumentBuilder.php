<?php

namespace Kalle\Pdf\Document;

use Kalle\Pdf\Page\PageContent;
use Kalle\Pdf\Page\Page;
use Kalle\Pdf\Page\PageSize;
use Kalle\Pdf\Text\Text;

/**
 * Immutable builder for assembling a document from page content and metadata.
 */
class DocumentBuilder
{
    private ?string $title = null;
    private ?string $author = null;
    private ?string $subject = null;
    private ?string $keywords = null;
    private ?string $language = null;
    private ?string $creator = null;
    private ?string $creatorTool = null;
    /** @var list<Page> */
    private array $pages = [];
    private ?PageSize $pageSize = null;
    private ?PageSize $defaultPageSize = null;
    /** @var list<PageContent> */
    private array $pageContents = [];

    /**
     * Creates a new document builder with default page settings.
     */
    public static function make(): self
    {
        return new self();
    }

    public function withTitle(string $title): self
    {
        return clone($this, [
            'title' => $title,
        ]);
    }

    public function withAuthor(string $author): self
    {
        return clone($this, [
            'author' => $author,
        ]);
    }

    public function withSubject(string $subject): self
    {
        return clone($this, [
            'subject' => $subject,
        ]);
    }

    public function withKeywords(string $keywords): self
    {
        return clone($this, [
            'keywords' => $keywords,
        ]);
    }

    public function withLanguage(string $language): self
    {
        return clone($this, [
            'language' => $language,
        ]);
    }

    public function withCreator(string $creator): self
    {
        return clone($this, [
            'creator' => $creator,
        ]);
    }

    public function withCreatorTool(string $creatorTool): self
    {
        return clone($this, [
            'creatorTool' => $creatorTool,
        ]);
    }

    /**
     * Sets the current and future default page size for the document.
     */
    public function withPageSize(PageSize $pageSize): self
    {
        return clone ($this, [
            'pageSize' => $pageSize,
            'defaultPageSize' => $pageSize,
        ]);
    }

    /**
     * Appends a positioned text entry to the current open page.
     */
    public function writeText(
        string $value,
        float $x,
        float $y,
    ): self {
        return clone($this, [
            'pageContents' => [
                ...$this->pageContents,
                Text::make($value, $x, $y),
            ],
        ]);
    }

    /**
     * Finalizes the current page and starts a new open page.
     */
    public function startNewPage(?PageSize $pageSize = null): self
    {
        $clone = clone($this, [
            'pages' => [...$this->pages, $this->createPage()],
        ]);
        $clone->resetPage($pageSize);

        return $clone;
    }

    /**
     * Builds the immutable document snapshot from all collected pages and metadata.
     */
    public function build(): Document
    {
        return Document::make(
            pages: [...$this->pages, $this->createPage()],
            title: $this->title,
            author: $this->author,
            subject: $this->subject,
            keywords: $this->keywords,
            language: $this->language,
            creator: $this->creator,
            creatorTool: $this->creatorTool,
        );
    }

    private function __construct()
    {
        $this->defaultPageSize ??= PageSize::A4();
    }

    /**
     * Creates the current page snapshot from the open page state.
     */
    private function createPage(): Page
    {
        return Page::make(
            pageSize: $this->pageSize ?? $this->defaultPageSize,
            contents: $this->pageContents,
        );
    }

    /**
     * Resets the open page state for the next page.
     */
    private function resetPage(?PageSize $pageSize): void
    {
        $this->pageSize = $pageSize ?? $this->defaultPageSize;
        $this->pageContents = [];
    }
}
