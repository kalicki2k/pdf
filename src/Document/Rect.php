<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

final readonly class Rect
{
    public function __construct(
        public float $x,
        public float $y,
        public float $width,
        public float $height,
    ) {
    }
}
