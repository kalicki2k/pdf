<?php

declare(strict_types=1);

namespace Kalle\Pdf\Page;

use InvalidArgumentException;

/**
 * Immutable rectangular page box defined in PDF point coordinates.
 */
readonly class PageBox
{
    /**
     * Creates a page box from absolute page coordinates.
     */
    public static function make(
        float $left,
        float $bottom,
        float $right,
        float $top,
    ): self {
        return new self($left, $bottom, $right, $top);
    }

    /**
     * Returns the box width in PDF points.
     */
    public function width(): float
    {
        return $this->right - $this->left;
    }

    /**
     * Returns the box height in PDF points.
     */
    public function height(): float
    {
        return $this->top - $this->bottom;
    }

    public function assertFitsWithin(PageSize $pageSize): void
    {
        if ($this->fitsWithin($pageSize)) {
            return;
        }

        throw new InvalidArgumentException('Page box must lie within the page MediaBox.');
    }

    /**
     * @param float $left Left edge in PDF points.
     * @param float $bottom Bottom edge in PDF points.
     * @param float $right Right edge in PDF points.
     * @param float $top Top edge in PDF points.
     */
    private function __construct(
        public float $left,
        public float $bottom,
        public float $right,
        public float $top,
    ) {
        if ($this->right <= $this->left || $this->top <= $this->bottom) {
            throw new InvalidArgumentException('Page box must have positive width and height.');
        }
    }

    /**
     * Checks whether the box lies fully within the page MediaBox.
     */
    public function fitsWithin(PageSize $pageSize): bool
    {
        return $this->left >= 0.0
            && $this->bottom >= 0.0
            && $this->right <= $pageSize->width()
            && $this->top <= $pageSize->height();
    }
}
