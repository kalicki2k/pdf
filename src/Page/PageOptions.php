<?php

declare(strict_types=1);

namespace Kalle\Pdf\Page;

use Kalle\Pdf\Color\Color;

/**
 * Describes optional per-page overrides for page geometry and PDF page metadata.
 */
final readonly class PageOptions
{
    /**
     * Creates a page option object for explicit page transitions.
     *
     * @param ?PageSize $pageSize Explicit page size override for the target page.
     * @param ?PageOrientation $orientation Optional orientation applied to the resolved page size.
     * @param ?Margin $margin Page margin override used to derive the content area.
     * @param ?Color $backgroundColor Optional page background fill.
     * @param ?string $label Optional logical page label.
     * @param ?string $name Optional page name for downstream page references.
     * @param ?PageBox $cropBox Optional crop box override.
     * @param ?PageBox $bleedBox Optional bleed box override.
     * @param ?PageBox $trimBox Optional trim box override.
     * @param ?PageBox $artBox Optional art box override.
     */
    public static function make(
        ?PageSize $pageSize = null,
        ?PageOrientation $orientation = null,
        ?Margin $margin = null,
        ?Color $backgroundColor = null,
        ?string $label = null,
        ?string $name = null,
        ?PageBox $cropBox = null,
        ?PageBox $bleedBox = null,
        ?PageBox $trimBox = null,
        ?PageBox $artBox = null,
    ): PageOptions {
        return new self(
            pageSize: $pageSize,
            orientation: $orientation,
            margin: $margin,
            backgroundColor: $backgroundColor,
            label: $label,
            name: $name,
            cropBox: $cropBox,
            bleedBox: $bleedBox,
            trimBox: $trimBox,
            artBox: $artBox,
        );
    }

    private function __construct(
        public ?PageSize $pageSize = null,
        public ?PageOrientation $orientation = null,
        public ?Margin $margin = null,
        public ?Color $backgroundColor = null,
        public ?string $label = null,
        public ?string $name = null,
        public ?PageBox $cropBox = null,
        public ?PageBox $bleedBox = null,
        public ?PageBox $trimBox = null,
        public ?PageBox $artBox = null,
    ) {
    }
}
