<?php

declare(strict_types=1);

namespace Kalle\Pdf\Font;

final readonly class StandardFontGlyphRun
{
    private function __construct(
        public string $fontName,
        public string $bytes,
    ) {
    }

    /**
     * @param list<string> $glyphNames
     */
    public static function fromGlyphNames(string | StandardFont $font, array $glyphNames): self
    {
        $fontName = $font instanceof StandardFont
            ? $font->value
            : $font;

        return new self(
            fontName: $fontName,
            bytes: StandardFontGlyphMap::encodeGlyphNames($fontName, $glyphNames),
        );
    }

    /**
     * @param list<int> $glyphCodes
     */
    public static function fromGlyphCodes(string | StandardFont $font, array $glyphCodes): self
    {
        $fontName = $font instanceof StandardFont
            ? $font->value
            : $font;

        return new self(
            fontName: $fontName,
            bytes: StandardFontGlyphMap::encodeGlyphCodes($fontName, $glyphCodes),
        );
    }
}
