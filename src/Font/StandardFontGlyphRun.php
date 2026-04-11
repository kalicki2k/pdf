<?php

declare(strict_types=1);

namespace Kalle\Pdf\Font;

final readonly class StandardFontGlyphRun
{
    /**
     * @param array<int, string> $differences
     * @param list<?string> $glyphNames
     */
    private function __construct(
        public string $fontName,
        public string $bytes,
        public array $differences = [],
        public bool $useHexString = false,
        public array $glyphNames = [],
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
        $glyphEncoding = StandardFontGlyphMap::encodeGlyphNames($fontName, $glyphNames);

        return new self(
            fontName: $fontName,
            bytes: $glyphEncoding['bytes'],
            differences: $glyphEncoding['differences'],
            useHexString: $glyphEncoding['useHexString'],
            glyphNames: $glyphNames,
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
        $glyphEncoding = StandardFontGlyphMap::encodeGlyphCodes($fontName, $glyphCodes);
        /** @var list<?string> $glyphNames */
        $glyphNames = [];

        if (isset(StandardFontCoreGlyphMap::CODE_TO_NAME[$fontName])) {
            foreach ($glyphCodes as $glyphCode) {
                $glyphNames[] = StandardFontCoreGlyphMap::glyphNameForCode($fontName, $glyphCode);
            }
        }

        return new self(
            fontName: $fontName,
            bytes: $glyphEncoding['bytes'],
            differences: $glyphEncoding['differences'],
            useHexString: $glyphEncoding['useHexString'],
            glyphNames: $glyphNames,
        );
    }
}
