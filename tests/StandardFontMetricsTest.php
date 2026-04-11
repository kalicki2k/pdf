<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests;

use Kalle\Pdf\StandardFontMetrics;
use PHPUnit\Framework\TestCase;

final class StandardFontMetricsTest extends TestCase
{
    public function testItMeasuresKnownStandardFontWidths(): void
    {
        self::assertSame(22.78, StandardFontMetrics::measureTextWidth('Helvetica', 'Hello', 10));
    }

    public function testItUsesMonospaceCourierWidthsWhenNoExplicitTableExists(): void
    {
        self::assertSame(18.0, StandardFontMetrics::measureTextWidth('Courier-Bold', 'ABC', 10));
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
