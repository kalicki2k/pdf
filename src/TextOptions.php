<?php

declare(strict_types=1);

namespace Kalle\Pdf;

final readonly class TextOptions
{
    public function __construct(
        public float $x = 72.0,
        public float $y = 720.0,
        public float $fontSize = 18.0,
        public string $fontName = StandardFont::HELVETICA->value,
        public ?Color $color = null,
    ) {
    }
}
