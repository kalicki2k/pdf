<?php

declare(strict_types=1);

namespace Kalle\Pdf;

use InvalidArgumentException;

final readonly class TextMeasurer
{
    public function measureTextWidth(string $text, float $fontSize, string|StandardFont $font = StandardFont::HELVETICA): float
    {
        $fontName = $font instanceof StandardFont
            ? $font->value
            : $font;

        if (!StandardFont::isValid($fontName)) {
            throw new InvalidArgumentException(sprintf(
                "Font '%s' is not a valid PDF standard font.",
                $fontName,
            ));
        }

        $width = StandardFontMetrics::measureTextWidth($fontName, $text, $fontSize);

        if ($width === null) {
            throw new InvalidArgumentException(sprintf(
                "Unable to measure text width for font '%s'.",
                $fontName,
            ));
        }

        return $width;
    }
}
