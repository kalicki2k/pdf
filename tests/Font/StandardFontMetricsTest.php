<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Font;

use Kalle\Pdf\Font\StandardFontMetrics;
use PHPUnit\Framework\TestCase;

final class StandardFontMetricsTest extends TestCase
{
    public function testItMeasuresKnownStandardFontWidths(): void
    {
        self::assertSame(22.78, StandardFontMetrics::measureTextWidth('Helvetica', 'Hello', 10));
    }

    public function testItMeasuresWesternWinAnsiAndLatin1CharactersForHelvetica(): void
    {
        self::assertEqualsWithDelta(50.02, StandardFontMetrics::measureTextWidth('Helvetica', 'ÄÖÜäöüß€', 10), 0.0001);
        self::assertEqualsWithDelta(29.44, StandardFontMetrics::measureTextWidth('Helvetica', '„Hallo“', 10), 0.0001);
    }

    public function testItMeasuresWesternWinAnsiAndLatin1CharactersForTimesRoman(): void
    {
        self::assertEqualsWithDelta(46.1, StandardFontMetrics::measureTextWidth('Times-Roman', 'ÄÖÜäöüß€', 10), 0.0001);
        self::assertEqualsWithDelta(31.1, StandardFontMetrics::measureTextWidth('Times-Roman', '„Hallo“', 10), 0.0001);
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

    public function testItUsesTheFallbackGlyphWidthForUnknownAsciiCharactersInSupportedFonts(): void
    {
        self::assertSame(6.0, StandardFontMetrics::measureTextWidth('Helvetica', "\x7F", 10));
    }

    public function testItReturnsNullForUnknownNonCourierFonts(): void
    {
        self::assertNull(StandardFontMetrics::measureTextWidth('NotoSans-Regular', 'Hello', 10));
    }
}
