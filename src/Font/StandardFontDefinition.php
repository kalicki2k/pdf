<?php

declare(strict_types=1);

namespace Kalle\Pdf\Font;

use InvalidArgumentException;

use function ord;

final readonly class StandardFontDefinition
{
    private function __construct(
        public string $name,
    ) {
    }

    public static function from(string | StandardFont $font): self
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

        return new self($fontName);
    }

    public function resolveEncoding(float $pdfVersion, ?StandardFontEncoding $preferredEncoding = null): StandardFontEncoding
    {
        return StandardFontEncoding::forFont($this->name, $pdfVersion, $preferredEncoding);
    }

    public function supportsText(string $text, float $pdfVersion, ?StandardFontEncoding $preferredEncoding = null): bool
    {
        return $this->resolveEncoding($pdfVersion, $preferredEncoding)->supportsText($text);
    }

    public function encodeText(string $text, float $pdfVersion, ?StandardFontEncoding $preferredEncoding = null): string
    {
        return $this->resolveEncoding($pdfVersion, $preferredEncoding)->encodeText($text);
    }

    public function measureTextWidth(string $text, float $fontSize): float
    {
        $width = StandardFontMetrics::measureTextWidth($this->name, $text, $fontSize);

        if ($width === null) {
            throw new InvalidArgumentException(sprintf(
                "Unable to measure text width for font '%s'.",
                $this->name,
            ));
        }

        return $width;
    }

    public function ascent(float $fontSize): float
    {
        return StandardFontMetrics::ascent($this->name, $fontSize);
    }

    public function pdfEncodingObjectValue(StandardFontEncoding $encoding): string
    {
        return $encoding->pdfObjectValue($this->name);
    }

    /**
     * @return list<?string>
     */
    public function glyphNamesForText(
        string $text,
        float $pdfVersion,
        ?StandardFontEncoding $preferredEncoding = null,
    ): array {
        if (!isset(StandardFontCoreGlyphMap::NAME_TO_CODE[$this->name])) {
            return [];
        }

        $encoding = $this->resolveEncoding($pdfVersion, $preferredEncoding);
        $glyphNames = [];

        foreach ($this->characters($text) as $character) {
            if ($character === "\t" || $character === "\n" || $character === "\r") {
                $glyphNames[] = null;

                continue;
            }

            if ($character === ' ') {
                $glyphNames[] = 'space';

                continue;
            }

            if ($this->isAsciiPrintable($character)) {
                $glyphNames[] = StandardFontCoreGlyphMap::glyphNameForCode($this->name, ord($character));

                continue;
            }

            $glyphNames[] = StandardFontWesternGlyphMap::glyphName($character);
        }

        return $glyphNames;
    }

    public function kerningValue(string $leftGlyph, string $rightGlyph): int
    {
        return StandardFontCoreKerning::value($this->name, $leftGlyph, $rightGlyph);
    }

    /**
     * @return list<string>
     */
    private function characters(string $text): array
    {
        return preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: str_split($text);
    }

    private function isAsciiPrintable(string $character): bool
    {
        return preg_match('/^[\x21-\x7E]$/', $character) === 1;
    }
}
