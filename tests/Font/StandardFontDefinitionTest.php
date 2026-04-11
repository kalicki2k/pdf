<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Font;

use InvalidArgumentException;
use Kalle\Pdf\Document\Version;
use Kalle\Pdf\Font\StandardFont;
use Kalle\Pdf\Font\StandardFontDefinition;
use Kalle\Pdf\Font\StandardFontEncoding;
use PHPUnit\Framework\TestCase;

final class StandardFontDefinitionTest extends TestCase
{
    public function testItRejectsUnknownFonts(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Font 'NotoSans-Regular' is not a valid PDF standard font.");

        StandardFontDefinition::from('NotoSans-Regular');
    }

    public function testItResolvesEncodingsThroughTheFontDefinition(): void
    {
        $font = StandardFontDefinition::from(StandardFont::HELVETICA);

        self::assertSame(StandardFontEncoding::STANDARD, $font->resolveEncoding(Version::V1_0));
        self::assertSame(StandardFontEncoding::WIN_ANSI, $font->resolveEncoding(Version::V1_4));
    }

    public function testItEncodesTextThroughTheFontDefinition(): void
    {
        $font = StandardFontDefinition::from(StandardFont::HELVETICA);

        self::assertSame(
            'c4d6dce4f6fcdf80',
            bin2hex($font->encodeText('ÄÖÜäöüß€', Version::V1_4)),
        );
    }

    public function testItMeasuresTextWidthThroughTheFontDefinition(): void
    {
        $font = StandardFontDefinition::from(StandardFont::SYMBOL);

        self::assertSame(23.59, $font->measureTextWidth('αβγΩ', 10));
    }

    public function testItMeasuresKerningAwareWidthThroughTheFontDefinition(): void
    {
        $font = StandardFontDefinition::from(StandardFont::HELVETICA);

        self::assertEqualsWithDelta(12.63, $font->measureTextWidth('AV', 10), 0.0001);
    }

    public function testItBuildsThePdfEncodingObjectValueThroughTheFontDefinition(): void
    {
        $font = StandardFontDefinition::from(StandardFont::HELVETICA);

        self::assertSame(
            '/WinAnsiEncoding',
            $font->pdfEncodingObjectValue(StandardFontEncoding::WIN_ANSI),
        );
    }
}
