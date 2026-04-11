<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Font;

use Kalle\Pdf\Font\StandardFontMetrics;
use PHPUnit\Framework\TestCase;

final class StandardFontMetricsTest extends TestCase
{
    public function testItMeasuresKnownStandardFontWidths(): void
    {
        self::assertEqualsWithDelta(22.74, StandardFontMetrics::measureTextWidth('Helvetica', 'Hello', 10), 0.0001);
    }

    public function testItMeasuresWesternWinAnsiAndLatin1CharactersForHelvetica(): void
    {
        self::assertEqualsWithDelta(50.02, StandardFontMetrics::measureTextWidth('Helvetica', 'ÄÖÜäöüß€', 10), 0.0001);
        self::assertEqualsWithDelta(29.37, StandardFontMetrics::measureTextWidth('Helvetica', '„Hallo“', 10), 0.0001);
    }

    public function testItMeasuresWesternWinAnsiAndLatin1CharactersForTimesRoman(): void
    {
        self::assertEqualsWithDelta(46.1, StandardFontMetrics::measureTextWidth('Times-Roman', 'ÄÖÜäöüß€', 10), 0.0001);
        self::assertEqualsWithDelta(31.03, StandardFontMetrics::measureTextWidth('Times-Roman', '„Hallo“', 10), 0.0001);
    }

    public function testItUsesMonospaceCourierWidthsWhenNoExplicitTableExists(): void
    {
        self::assertSame(18.0, StandardFontMetrics::measureTextWidth('Courier-Bold', 'ABC', 10));
    }

    public function testItMeasuresSymbolTextUsingAdobeAfmMetrics(): void
    {
        self::assertSame(23.59, StandardFontMetrics::measureTextWidth('Symbol', 'αβγΩ', 10));
    }

    public function testItMeasuresZapfDingbatsTextUsingAdobeAfmMetrics(): void
    {
        self::assertEqualsWithDelta(31.24, StandardFontMetrics::measureTextWidth('ZapfDingbats', '✓✔✕✖', 10), 0.0001);
    }

    public function testItMeasuresCoreFontTextWithKerningApplied(): void
    {
        self::assertEqualsWithDelta(12.63, StandardFontMetrics::measureTextWidth('Helvetica', 'AV', 10), 0.0001);
        self::assertEqualsWithDelta(10.24, StandardFontMetrics::measureTextWidth('Times-Roman', 'To', 10), 0.0001);
    }

    public function testItExposesGlyphNamesAcrossAllStandardFonts(): void
    {
        self::assertContains('A', StandardFontMetrics::glyphNames('Helvetica'));
        self::assertContains('AE', StandardFontMetrics::glyphNames('Times-Roman'));
        self::assertContains('Alpha', StandardFontMetrics::glyphNames('Symbol'));
        self::assertContains('a1', StandardFontMetrics::glyphNames('ZapfDingbats'));
    }

    public function testItUsesTheFallbackGlyphWidthForUnknownAsciiCharactersInSupportedFonts(): void
    {
        self::assertSame(6.0, StandardFontMetrics::measureTextWidth('Helvetica', "\x7F", 10));
    }

    public function testItReturnsNullForUnknownNonCourierFonts(): void
    {
        self::assertNull(StandardFontMetrics::measureTextWidth('NotoSans-Regular', 'Hello', 10));
    }
}
