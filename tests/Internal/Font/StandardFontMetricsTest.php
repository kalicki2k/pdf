<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Internal\Font;

use Kalle\Pdf\Internal\Font\StandardFontMetrics;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class StandardFontMetricsTest extends TestCase
{
    #[Test]
    public function it_measures_known_standard_font_widths(): void
    {
        self::assertSame(22.78, StandardFontMetrics::measureTextWidth('Helvetica', 'Hello', 10));
    }

    #[Test]
    public function it_uses_monospace_courier_widths_when_no_explicit_table_exists(): void
    {
        self::assertSame(18.0, StandardFontMetrics::measureTextWidth('Courier-Bold', 'ABC', 10));
    }

    #[Test]
    public function it_uses_the_fallback_glyph_width_for_unknown_ascii_characters_in_supported_fonts(): void
    {
        self::assertSame(6.0, StandardFontMetrics::measureTextWidth('Helvetica', "\x7F", 10));
    }

    #[Test]
    public function it_returns_null_for_unknown_non_courier_fonts(): void
    {
        self::assertNull(StandardFontMetrics::measureTextWidth('NotoSans-Regular', 'Hello', 10));
    }
}
