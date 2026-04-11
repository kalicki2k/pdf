<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Font;

use InvalidArgumentException;
use Kalle\Pdf\Font\EmbeddedFontDefinition;
use Kalle\Pdf\Font\EmbeddedFontSource;
use PHPUnit\Framework\TestCase;

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

    public function testItRejectsCffOutlineFontsInPhaseOne(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Phase 1 only supports embedded TrueType outlines.');

        EmbeddedFontDefinition::fromSource(
            EmbeddedFontSource::fromString(TrueTypeFontFixture::minimalCffOpenTypeFontBytes()),
        );
    }
}
