<?php

declare(strict_types=1);

namespace Kalle\Pdf\Text;

use Kalle\Pdf\Font\StandardFont;
use Kalle\Pdf\Font\StandardFontDefinition;

final readonly class TextMeasurer
{
    public function measureTextWidth(string $text, float $fontSize, string | StandardFont $font = StandardFont::HELVETICA): float
    {
        return StandardFontDefinition::from($font)->measureTextWidth($text, $fontSize);
    }
}
