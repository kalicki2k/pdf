<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Font;

use InvalidArgumentException;
use Kalle\Pdf\Font\EmbeddedFontDefinition;
use Kalle\Pdf\Font\EmbeddedFontSource;
use PHPUnit\Framework\TestCase;

use function preg_match;

final class EmbeddedFontDefinitionTest extends TestCase
{
    public function testItEncodesAndMeasuresSimpleWesternText(): void
    {
        $font = EmbeddedFontDefinition::fromSource(
            EmbeddedFontSource::fromString(TrueTypeFontFixture::minimalTrueTypeFontBytes()),
        );

        self::assertTrue($font->supportsText('A'));
        self::assertSame('A', $font->encodeText('A'));
        self::assertSame(12.0, $font->measureTextWidth('A', 20.0));
        self::assertSame(16.0, $font->ascent(20.0));
    }

    public function testItRejectsUnsupportedText(): void
    {
        $font = EmbeddedFontDefinition::fromSource(
            EmbeddedFontSource::fromString(TrueTypeFontFixture::minimalTrueTypeFontBytes()),
        );

        self::assertFalse($font->supportsText('B'));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Text cannot be encoded with embedded font 'TestFont-Regular'.");

        $font->encodeText('B');
    }

    public function testItSupportsUnicodeBmpTextForPhaseTwoPath(): void
    {
        $font = EmbeddedFontDefinition::fromSource(
            EmbeddedFontSource::fromString(TrueTypeFontFixture::minimalUnicodeTrueTypeFontBytes()),
        );

        self::assertFalse($font->supportsText('Ж'));
        self::assertTrue($font->supportsUnicodeText('Ж'));
        self::assertSame("\x04\x16", $font->encodeUnicodeText('Ж'));
        self::assertSame(14.0, $font->measureTextWidth('Ж', 20.0));
        self::assertSame([0x0416], $font->unicodeCodePointsForText('ЖЖ'));
        self::assertTrue($font->supportsUnicodeText('Ж中'));
        self::assertSame("\x04\x16\x4E\x2D", $font->encodeUnicodeText('Ж中'));
        self::assertSame(30.0, $font->measureTextWidth('Ж中', 20.0));
        self::assertSame([0x4E2D, 0x0416], $font->unicodeCodePointsForText('中ЖЖ'));
        self::assertTrue($font->supportsUnicodeText('😀'));
        self::assertSame("\xD8\x3D\xDE\x00", $font->encodeUnicodeText('😀'));
        self::assertSame(18.0, $font->measureTextWidth('😀', 20.0));
    }

    public function testItSupportsSimpleWesternTextForCffOpenTypeFonts(): void
    {
        $font = EmbeddedFontDefinition::fromSource(
            EmbeddedFontSource::fromString(TrueTypeFontFixture::minimalCffOpenTypeFontBytes()),
        );

        self::assertSame('TestCff-Regular', $font->metadata->postScriptName);
        self::assertTrue($font->supportsText('A'));
        self::assertSame('A', $font->encodeText('A'));
        self::assertSame(12.0, $font->measureTextWidth('A', 20.0));
        self::assertStringContainsString('/Subtype /OpenType', $font->fontFileStreamContents());
        self::assertStringContainsString('/FontFile3 9 0 R', $font->fontDescriptorContents(9));
        self::assertStringContainsString('/Subtype /Type1', $font->fontObjectContents(8));
    }

    public function testItSupportsUnicodeTextForCffOpenTypeFonts(): void
    {
        $font = EmbeddedFontDefinition::fromSource(
            EmbeddedFontSource::fromString(TrueTypeFontFixture::minimalUnicodeCffOpenTypeFontBytes()),
        );

        self::assertTrue($font->supportsUnicodeText('Ж中😀'));
        self::assertSame("\x04\x16\x4E\x2D\xD8\x3D\xDE\x00", $font->encodeUnicodeText('Ж中😀'));
        $subsetStream = $font->unicodeSubsetFontFileStreamContents([0x0416, 0x4E2D, 0x1F600]);

        self::assertStringContainsString('/Subtype /OpenType', $subsetStream);
        self::assertStringContainsString('/Subtype /CIDFontType0', $font->unicodeCidFontObjectContents(8, null, [0x0416, 0x4E2D]));
        self::assertLessThan(strlen(TrueTypeFontFixture::minimalUnicodeCffOpenTypeFontBytes()), $this->extractLength($subsetStream));
    }

    public function testItScalesPdfWidthsForUnicodeCidFontsToPdfTextSpace(): void
    {
        $font = EmbeddedFontDefinition::fromSource(
            EmbeddedFontSource::fromPath(dirname(__DIR__, 2) . '/assets/fonts/inter/static/Inter-Regular.ttf'),
        );
        $glyphs = $font->embeddedGlyphsForCodePoints([0x0041]);
        $contents = $font->unicodeCidFontObjectContentsForGlyphs(8, 9, $glyphs);

        self::assertStringContainsString('/W [1 [690]]', $contents);
    }

    private function extractLength(string $stream): int
    {
        self::assertMatchesRegularExpression('/\\/Length ([0-9]+)/', $stream);
        preg_match('/\\/Length ([0-9]+)/', $stream, $matches);

        return (int) ($matches[1] ?? 0);
    }
}
