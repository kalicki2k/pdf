<?php

declare(strict_types=1);

namespace Kalle\Pdf\Text;

use Kalle\Pdf\Color\Color;
use Kalle\Pdf\Font\StandardFont;
use Kalle\Pdf\Font\StandardFontEncoding;

final readonly class TextOptions
{
    public function __construct(
        public float $x = 72.0,
        public float $y = 720.0,
        public float $fontSize = 18.0,
        public string $fontName = StandardFont::HELVETICA->value,
        public ?StandardFontEncoding $fontEncoding = null,
        public ?Color $color = null,
        public bool $kerning = true,
    ) {
    }
}
