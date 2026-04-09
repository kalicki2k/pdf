<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use Kalle\Pdf\Feature\Table\Style\TableBorder;
use Kalle\Pdf\Feature\Table\Style\TablePadding;
use Kalle\Pdf\Feature\Table\Style\TableStyle;
use Kalle\Pdf\Graphics\Color;
use Kalle\Pdf\Layout\VerticalAlign;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TableStyleTest extends TestCase
{
    #[Test]
    public function it_stores_table_style_values(): void
    {
        $padding = TablePadding::symmetric(8, 3);
        $border = TableBorder::all(1.5, Color::rgb(255, 0, 0));
        $style = new TableStyle(
            padding: $padding,
            border: $border,
            verticalAlign: VerticalAlign::MIDDLE,
            fillColor: Color::gray(0.8),
            textColor: Color::rgb(0, 0, 255),
        );

        self::assertSame($padding, $style->padding);
        self::assertSame($border, $style->border);
        self::assertSame(VerticalAlign::MIDDLE, $style->verticalAlign);
        self::assertSame('0.8 g', $style->fillColor?->renderNonStrokingOperator());
        self::assertSame('0 0 1 rg', $style->textColor?->renderNonStrokingOperator());
    }
}
