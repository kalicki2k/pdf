<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Text;

use InvalidArgumentException;
use Kalle\Pdf\Font\StandardFont;
use Kalle\Pdf\Text\TextMeasurer;
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

    public function testItMeasuresWesternTextWidthForHelvetica(): void
    {
        $measurer = new TextMeasurer();

        self::assertEqualsWithDelta(50.02, $measurer->measureTextWidth('ÄÖÜäöüß€', 10, StandardFont::HELVETICA), 0.0001);
    }

    public function testItMeasuresSymbolTextWidthForUnicodeGlyphs(): void
    {
        $measurer = new TextMeasurer();

        self::assertSame(23.59, $measurer->measureTextWidth('αβγΩ', 10, StandardFont::SYMBOL));
    }

    public function testItMeasuresZapfDingbatsTextWidthForUnicodeGlyphs(): void
    {
        $measurer = new TextMeasurer();

        self::assertEqualsWithDelta(31.24, $measurer->measureTextWidth('✓✔✕✖', 10, StandardFont::ZAPF_DINGBATS), 0.0001);
    }

    public function testItRejectsUnknownFonts(): void
    {
        $measurer = new TextMeasurer();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Font 'NotoSans-Regular' is not a valid PDF standard font.");

        $measurer->measureTextWidth('Hello', 10, 'NotoSans-Regular');
    }
}
