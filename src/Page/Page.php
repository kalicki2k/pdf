<?php

namespace Kalle\Pdf\Page;

use Kalle\Pdf\Layout\Margin;
use Kalle\Pdf\Page\PageContent;

/**
 * Immutable page model used during document assembly and rendering.
 */
class Page
{
    /**
     * Creates a page snapshot from resolved page settings and collected content.
     *
     * @param list<PageContent> $contents Ordered page content entries to be rendered later.
     */
    public static function make(
        PageSize $pageSize,
        ?PageBox $cropBox = null,
        ?PageBox $bleedBox = null,
        ?PageBox $trimBox = null,
        ?PageBox $artBox = null,
        ?Margin  $margin = null,
        array $contents = [],
    ): self {
        return new self(
            pageSize: $pageSize,
            cropBox: $cropBox,
            bleedBox: $bleedBox,
            trimBox: $trimBox,
            artBox: $artBox,
            margin: $margin,
            contents: $contents,
        );
    }

    /**
     * Resolves the usable content area from the page size and margins.
     */
    public function contentArea(): PageContentArea
    {
        $margin = $this->margin ?? Margin::all(0);

        return PageContentArea::make(
            left: $margin->left,
            right: $this->pageSize->width() - $margin->right,
            top: $this->pageSize->height() - $margin->top,
            bottom: $margin->bottom,
        );
    }

    /**
     * @param list<PageContent> $contents Ordered page content entries to be rendered later.
     */
    private function __construct(
        public PageSize $pageSize,
        public ?PageBox $cropBox = null,
        public ?PageBox $bleedBox = null,
        public ?PageBox $trimBox = null,
        public ?PageBox $artBox = null,
        public ?Margin  $margin = null,
        public array $contents,
    ) {
        $this->cropBox?->assertFitsWithin($this->pageSize);
        $this->bleedBox?->assertFitsWithin($this->pageSize);
        $this->trimBox?->assertFitsWithin($this->pageSize);
        $this->artBox?->assertFitsWithin($this->pageSize);
    }
}
