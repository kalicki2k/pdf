<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Text;

use Kalle\Pdf\Font\StandardFont\StandardFont;
use Kalle\Pdf\Text\StandardFontTextMeasurer;
use PHPUnit\Framework\TestCase;

final class StandardFontTextMeasurerTest extends TestCase
{
    public function testItMeasuresTextWidthForAStandardFontString(): void
    {
        $measurer = new StandardFontTextMeasurer(StandardFont::HELVETICA);

        self::assertEqualsWithDelta(22.74, $measurer->width('Hello', 10), 0.0001);
    }

    public function testItMeasuresTextWidthForAStandardFontEnum(): void
    {
        $measurer = new StandardFontTextMeasurer(StandardFont::COURIER_BOLD);

        self::assertSame(18.0, $measurer->width('ABC', 10));
    }

    public function testItMeasuresKerningAwareCoreTextWidth(): void
    {
        $measurer = new StandardFontTextMeasurer(StandardFont::HELVETICA);

        self::assertEqualsWithDelta(12.63, $measurer->width('AV', 10), 0.0001);
    }

    public function testItMeasuresAscentAndDescent(): void
    {
        $measurer = new StandardFontTextMeasurer(StandardFont::HELVETICA);

        self::assertSame(12.0, $measurer->ascent(12));
        self::assertGreaterThan(0.0, $measurer->descent(12));
    }
}
