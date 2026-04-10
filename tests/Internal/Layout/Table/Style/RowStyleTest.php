<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Internal\Layout\Table\Style;

use Kalle\Pdf\Internal\Layout\Table\Style\RowStyle;
use Kalle\Pdf\Internal\Layout\Table\Style\TableBorder;
use Kalle\Pdf\Internal\Layout\Table\Style\TablePadding;
use Kalle\Pdf\Internal\Layout\Value\HorizontalAlign;
use Kalle\Pdf\Internal\Layout\Value\VerticalAlign;
use Kalle\Pdf\Style\Color;
use Kalle\Pdf\Style\Opacity;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RowStyleTest extends TestCase
{
    #[Test]
    public function it_stores_row_style_values(): void
    {
        $padding = TablePadding::only(top: 1, right: 2, bottom: 3, left: 4);
        $border = TableBorder::horizontal(1.5, Color::rgb(255, 0, 0), Opacity::both(0.4));
        $style = new RowStyle(
            horizontalAlign: HorizontalAlign::RIGHT,
            verticalAlign: VerticalAlign::MIDDLE,
            padding: $padding,
            fillColor: Color::gray(0.8),
            textColor: Color::rgb(0, 0, 255),
            opacity: Opacity::both(0.5),
            border: $border,
        );

        self::assertSame(HorizontalAlign::RIGHT, $style->horizontalAlign);
        self::assertSame(VerticalAlign::MIDDLE, $style->verticalAlign);
        self::assertSame($padding, $style->padding);
        self::assertSame('0.8 g', $style->fillColor?->renderNonStrokingOperator());
        self::assertSame('0 0 1 rg', $style->textColor?->renderNonStrokingOperator());
        self::assertSame('<< /ca 0.5 /CA 0.5 >>', $style->opacity?->renderExtGStateDictionary());
        self::assertSame($border, $style->border);
    }
}
