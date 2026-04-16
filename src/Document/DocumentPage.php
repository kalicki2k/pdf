<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use Kalle\Pdf\Layout\Margin;
use Kalle\Pdf\Page\PageBox;
use Kalle\Pdf\Page\PageContent;
use Kalle\Pdf\Page\PageContentArea;
use Kalle\Pdf\Page\PageSize;

/**
 * Immutable page model used during document assembly and rendering.
 */
final readonly class DocumentPage
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
        ?string $name = null,
        ?string $label = null,
        array $contents = [],
    ): self {
        return new self(
            pageSize: $pageSize,
            cropBox: $cropBox,
            bleedBox: $bleedBox,
            trimBox: $trimBox,
            artBox: $artBox,
            margin: $margin,
            name: $name,
            label: $label,
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
        public ?string $name = null,
        public ?string $label = null,
        public array $contents = [],
    ) {
        $this->cropBox?->assertFitsWithin($this->pageSize);
        $this->bleedBox?->assertFitsWithin($this->pageSize);
        $this->trimBox?->assertFitsWithin($this->pageSize);
        $this->artBox?->assertFitsWithin($this->pageSize);
    }
}
