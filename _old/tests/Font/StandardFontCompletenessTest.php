<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Font;

use Kalle\Pdf\Font\StandardFont;
use Kalle\Pdf\Font\StandardFontGlyphMap;
use Kalle\Pdf\Font\StandardFontMetrics;
use PHPUnit\Framework\TestCase;

final class StandardFontCompletenessTest extends TestCase
{
    public function testEveryAddressableStandardFontGlyphIsMeasurable(): void
    {
        foreach (StandardFont::cases() as $font) {
            foreach (StandardFontGlyphMap::glyphNames($font) as $glyphName) {
                self::assertNotNull(
                    StandardFontMetrics::glyphWidth($font->value, $glyphName),
                    sprintf("Expected glyph '%s' in font '%s' to be measurable.", $glyphName, $font->value),
                );

                self::assertNotNull(
                    StandardFontMetrics::measureGlyphNamesWidth($font->value, [$glyphName], 10),
                    sprintf("Expected glyph '%s' in font '%s' to produce a measurable glyph-run width.", $glyphName, $font->value),
                );
            }
        }
    }

    public function testEveryAddressableStandardFontGlyphIsIndividuallyEncodable(): void
    {
        foreach (StandardFont::cases() as $font) {
            foreach (StandardFontGlyphMap::glyphNames($font) as $glyphName) {
                $glyphEncoding = StandardFontGlyphMap::encodeGlyphNames($font, [$glyphName]);

                self::assertSame(
                    1,
                    strlen($glyphEncoding['bytes']),
                    sprintf("Expected glyph '%s' in font '%s' to encode to a single byte.", $glyphName, $font->value),
                );
            }
        }
    }

    public function testEveryExplicitlyAddressableGlyphCodeRoundTrips(): void
    {
        foreach (StandardFont::cases() as $font) {
            foreach (StandardFontGlyphMap::glyphNames($font) as $glyphName) {
                $glyphCode = StandardFontGlyphMap::glyphCodeForName($font, $glyphName);

                if ($glyphCode === null) {
                    continue;
                }

                $glyphEncoding = StandardFontGlyphMap::encodeGlyphCodes($font, [$glyphCode]);

                self::assertSame(
                    1,
                    strlen($glyphEncoding['bytes']),
                    sprintf("Expected glyph code '%d' in font '%s' to encode to a single byte.", $glyphCode, $font->value),
                );
            }
        }
    }
}
