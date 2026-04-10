<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use Kalle\Pdf\Layout\Table\Style\TableBorder;
use Kalle\Pdf\Layout\Table\Style\TablePadding;
use Kalle\Pdf\Layout\Table\Support\ResolvedTableCellStyle;
use Kalle\Pdf\Layout\Value\HorizontalAlign;
use Kalle\Pdf\Layout\Value\VerticalAlign;
use Kalle\Pdf\Style\Color;
use Kalle\Pdf\Style\Opacity;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ResolvedTableCellStyleTest extends TestCase
{
    #[Test]
    public function it_stores_resolved_table_cell_style_values(): void
    {
        $padding = TablePadding::only(top: 1, right: 2, bottom: 3, left: 4);
        $rowBorder = TableBorder::all(1.5, Color::rgb(255, 0, 0));
        $cellBorder = TableBorder::only(['left'], 0.75, Color::gray(0.4));
        $style = new ResolvedTableCellStyle(
            padding: $padding,
            fillColor: Color::gray(0.8),
            textColor: Color::rgb(0, 0, 255),
            verticalAlign: VerticalAlign::MIDDLE,
            horizontalAlign: HorizontalAlign::CENTER,
            opacity: Opacity::both(0.25),
            rowBorder: $rowBorder,
            cellBorder: $cellBorder,
        );

        self::assertSame($padding, $style->padding);
        self::assertSame('0.8 g', $style->fillColor?->renderNonStrokingOperator());
        self::assertSame('0 0 1 rg', $style->textColor?->renderNonStrokingOperator());
        self::assertSame(VerticalAlign::MIDDLE, $style->verticalAlign);
        self::assertSame(HorizontalAlign::CENTER, $style->horizontalAlign);
        self::assertSame('<< /ca 0.25 /CA 0.25 >>', $style->opacity?->renderExtGStateDictionary());
        self::assertSame($rowBorder, $style->rowBorder);
        self::assertSame($cellBorder, $style->cellBorder);
    }
}
