<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Font;

use Kalle\Pdf\Font\StandardFontCoreGlyphMetrics;
use Kalle\Pdf\Font\StandardFontMetrics;
use PHPUnit\Framework\TestCase;

final class StandardFontCoreGlyphMetricsTest extends TestCase
{
    public function testItExposesCompleteAfmGlyphTablesForAllCoreTextFonts(): void
    {
        self::assertCount(855, StandardFontCoreGlyphMetrics::glyphNames('Helvetica'));
        self::assertCount(855, StandardFontCoreGlyphMetrics::glyphNames('Helvetica-Bold'));
        self::assertCount(855, StandardFontCoreGlyphMetrics::glyphNames('Helvetica-Oblique'));
        self::assertCount(855, StandardFontCoreGlyphMetrics::glyphNames('Helvetica-BoldOblique'));
        self::assertCount(855, StandardFontCoreGlyphMetrics::glyphNames('Times-Roman'));
        self::assertCount(855, StandardFontCoreGlyphMetrics::glyphNames('Times-Bold'));
        self::assertCount(855, StandardFontCoreGlyphMetrics::glyphNames('Times-Italic'));
        self::assertCount(855, StandardFontCoreGlyphMetrics::glyphNames('Times-BoldItalic'));
        self::assertCount(855, StandardFontCoreGlyphMetrics::glyphNames('Courier'));
        self::assertCount(855, StandardFontCoreGlyphMetrics::glyphNames('Courier-Bold'));
        self::assertCount(855, StandardFontCoreGlyphMetrics::glyphNames('Courier-Oblique'));
        self::assertCount(855, StandardFontCoreGlyphMetrics::glyphNames('Courier-BoldOblique'));
    }

    public function testItExposesKnownGlyphWidthsFromAfmData(): void
    {
        self::assertSame(278, StandardFontCoreGlyphMetrics::widthForGlyph('Helvetica', 'space'));
        self::assertSame(1000, StandardFontCoreGlyphMetrics::widthForGlyph('Helvetica', 'AE'));
        self::assertSame(600, StandardFontCoreGlyphMetrics::widthForGlyph('Courier', 'A'));
        self::assertSame(722, StandardFontCoreGlyphMetrics::widthForGlyph('Times-Roman', 'A'));
    }

    public function testItMeasuresGlyphNameSequencesForCoreFonts(): void
    {
        self::assertSame(
            26.12,
            StandardFontMetrics::measureGlyphNamesWidth('Helvetica', ['A', 'E', 'space', 'AE'], 10),
        );
    }
}
