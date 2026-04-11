<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests;

use InvalidArgumentException;
use Kalle\Pdf\StandardFont;
use Kalle\Pdf\TextMeasurer;
use PHPUnit\Framework\TestCase;

final class TextMeasurerTest extends TestCase
{
    public function testItMeasuresTextWidthForAStandardFontString(): void
    {
        $measurer = new TextMeasurer();

        self::assertSame(22.78, $measurer->measureTextWidth('Hello', 10, 'Helvetica'));
    }

    public function testItMeasuresTextWidthForAStandardFontEnum(): void
    {
        $measurer = new TextMeasurer();

        self::assertSame(18.0, $measurer->measureTextWidth('ABC', 10, StandardFont::COURIER_BOLD));
    }

    public function testItRejectsUnknownFonts(): void
    {
        $measurer = new TextMeasurer();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Font 'NotoSans-Regular' is not a valid PDF standard font.");

        $measurer->measureTextWidth('Hello', 10, 'NotoSans-Regular');
    }
}
