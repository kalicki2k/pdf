<?php

declare(strict_types=1);

namespace Kalle\Pdf\Text;

use InvalidArgumentException;
use Kalle\Pdf\Font\StandardFont\StandardFont;
use Kalle\Pdf\Font\StandardFont\StandardFontMetrics;

/**
 * Measures basic text metrics for standard PDF fonts in PDF points.
 */
final readonly class StandardFontTextMeasurer implements TextMeasurer
{
    public function __construct(
        private StandardFont $font = StandardFont::HELVETICA,
    ) {
    }

    public function width(string $text, float $fontSize = 12.0): float
    {
        $resolvedFontName = $this->font->value;
        $width = StandardFontMetrics::measureTextWidth($resolvedFontName, $text, $fontSize);

        if ($width === null) {
            throw new InvalidArgumentException(sprintf('Unsupported font "%s".', $resolvedFontName));
        }

        return $width;
    }

    public function ascent(float $fontSize = 12.0): float
    {
        return StandardFontMetrics::ascent($this->font->value, $fontSize);
    }

    public function descent(float $fontSize = 12.0): float
    {
        return StandardFontMetrics::descent($this->font->value, $fontSize);
    }
}
