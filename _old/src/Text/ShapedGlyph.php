<?php

declare(strict_types=1);

namespace Kalle\Pdf\Text;

final readonly class ShapedGlyph
{
    public function __construct(
        public string $character,
        public int $cluster,
        public float $xAdvance = 0.0,
        public float $xOffset = 0.0,
        public float $yOffset = 0.0,
        public ?string $form = null,
        public ?string $glyphName = null,
        public ?int $glyphId = null,
        public ?int $unicodeCodePoint = null,
        public ?string $unicodeText = null,
    ) {
    }
}
