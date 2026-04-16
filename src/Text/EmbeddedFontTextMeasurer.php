<?php

declare(strict_types=1);

namespace Kalle\Pdf\Text;

use function abs;
use function mb_ord;
use function preg_split;

use Kalle\Pdf\Font\EmbeddedFont\EmbeddedFont;

final readonly class EmbeddedFontTextMeasurer implements TextMeasurer
{
    public function __construct(
        private EmbeddedFont $font,
    ) {
    }

    public function width(string $text, float $fontSize = 12.0): float
    {
        if ($text === '') {
            return 0.0;
        }

        $metrics = $this->font->parser->metrics();
        $width = 0;

        foreach (preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $character) {
            $glyphId = $this->font->parser->glyphIdForCodePoint(mb_ord($character, 'UTF-8'));
            $width += $this->font->parser->advanceWidthForGlyphId($glyphId);
        }

        return ($width / $metrics->unitsPerEm) * $fontSize;
    }

    public function ascent(float $fontSize = 12.0): float
    {
        $metrics = $this->font->parser->metrics();

        return ($metrics->ascent / $metrics->unitsPerEm) * $fontSize;
    }

    public function descent(float $fontSize = 12.0): float
    {
        $metrics = $this->font->parser->metrics();

        return abs($metrics->descent / $metrics->unitsPerEm) * $fontSize;
    }
}
