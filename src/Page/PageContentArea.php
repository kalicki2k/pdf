<?php

declare(strict_types=1);

namespace Kalle\Pdf\Page;

/**
 * Immutable usable page area derived from the page size and margins.
 */
readonly class PageContentArea
{
    /**
     * Creates a content area from absolute page coordinates.
     */
    public static function make(
        float $left,
        float $right,
        float $top,
        float $bottom,
    ): self {
        return new self(
            left: $left,
            right: $right,
            top: $top,
            bottom: $bottom,
        );
    }

    /**
     * Returns the usable width in PDF points.
     */
    public function width(): float
    {
        return $this->right - $this->left;
    }

    /**
     * Returns the usable height in PDF points.
     */
    public function height(): float
    {
        return $this->top - $this->bottom;
    }

    /**
     * @param float $left Left content boundary in PDF points.
     * @param float $right Right content boundary in PDF points.
     * @param float $top Top content boundary in PDF points.
     * @param float $bottom Bottom content boundary in PDF points.
     */
    private function __construct(
        public float $left,
        public float $right,
        public float $top,
        public float $bottom,
    ) {
    }
}
