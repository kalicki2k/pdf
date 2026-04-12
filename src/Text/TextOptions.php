<?php

declare(strict_types=1);

namespace Kalle\Pdf\Text;

use Kalle\Pdf\Color\Color;
use Kalle\Pdf\Font\EmbeddedFontSource;
use Kalle\Pdf\Font\StandardFont;
use Kalle\Pdf\Font\StandardFontEncoding;
use Kalle\Pdf\Page\LinkTarget;

final readonly class TextOptions
{
    public function __construct(
        public ?float $x = null,
        public ?float $y = null,
        public ?float $width = null,
        public ?float $maxWidth = null,
        public float $fontSize = 18.0,
        public ?float $lineHeight = null,
        public ?float $spacingBefore = null,
        public ?float $spacingAfter = null,
        public string $fontName = StandardFont::HELVETICA->value,
        public ?EmbeddedFontSource $embeddedFont = null,
        public ?StandardFontEncoding $fontEncoding = null,
        public ?Color $color = null,
        public bool $kerning = true,
        public TextDirection $baseDirection = TextDirection::LTR,
        public TextAlign $align = TextAlign::LEFT,
        public float $firstLineIndent = 0.0,
        public float $hangingIndent = 0.0,
        public LinkTarget | TextLink | null $link = null,
    ) {
    }
}
