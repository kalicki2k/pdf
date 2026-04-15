<?php

declare(strict_types=1);

namespace Kalle\Pdf\Page;

use InvalidArgumentException;

final readonly class PageBox
{
    public function __construct(
        public float $left,
        public float $bottom,
        public float $right,
        public float $top,
    ) {
        if ($this->right <= $this->left || $this->top <= $this->bottom) {
            throw new InvalidArgumentException('Page box must have positive width and height.');
        }
    }

    public static function fromPoints(float $left, float $bottom, float $right, float $top): self
    {
        return new self($left, $bottom, $right, $top);
    }

    public function width(): float
    {
        return $this->right - $this->left;
    }

    public function height(): float
    {
        return $this->top - $this->bottom;
    }

    public function fitsWithin(PageSize $pageSize): bool
    {
        return $this->left >= 0.0
            && $this->bottom >= 0.0
            && $this->right <= $pageSize->width()
            && $this->top <= $pageSize->height();
    }

    public function assertFitsWithin(PageSize $pageSize): void
    {
        if ($this->fitsWithin($pageSize)) {
            return;
        }

        throw new InvalidArgumentException('Page box must lie within the page MediaBox.');
    }
}
