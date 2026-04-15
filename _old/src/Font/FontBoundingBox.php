<?php

declare(strict_types=1);

namespace Kalle\Pdf\Font;

final readonly class FontBoundingBox
{
    public function __construct(
        public int $left,
        public int $bottom,
        public int $right,
        public int $top,
    ) {
    }
}
