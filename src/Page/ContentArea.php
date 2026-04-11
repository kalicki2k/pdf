<?php

declare(strict_types=1);

namespace Kalle\Pdf\Page;

final readonly class ContentArea
{
    public function __construct(
        public float $left,
        public float $right,
        public float $top,
        public float $bottom,
    ) {
    }

    public function width(): float
    {
        return $this->right - $this->left;
    }

    public function height(): float
    {
        return $this->top - $this->bottom;
    }
}
