<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use Kalle\Pdf\Feature\Table\Style\CellStyle;
use Kalle\Pdf\Feature\Table\Style\TableBorder;
use Kalle\Pdf\Feature\Table\Style\TablePadding;
use Kalle\Pdf\Graphics\Color;
use Kalle\Pdf\Graphics\Opacity;
use Kalle\Pdf\Layout\HorizontalAlign;
use Kalle\Pdf\Layout\VerticalAlign;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CellStyleTest extends TestCase
{
    #[Test]
    public function it_stores_cell_style_values(): void
    {
        $padding = TablePadding::only(top: 1, right: 2, bottom: 3, left: 4);
        $border = TableBorder::only(['left', 'bottom'], 1.5, Color::rgb(255, 0, 0), Opacity::both(0.4));
        $style = new CellStyle(
            horizontalAlign: HorizontalAlign::CENTER,
            verticalAlign: VerticalAlign::BOTTOM,
            padding: $padding,
            fillColor: Color::gray(0.8),
            textColor: Color::rgb(0, 0, 255),
            opacity: Opacity::both(0.5),
            border: $border,
        );

        self::assertSame(HorizontalAlign::CENTER, $style->horizontalAlign);
        self::assertSame(VerticalAlign::BOTTOM, $style->verticalAlign);
        self::assertSame($padding, $style->padding);
        self::assertSame('0.8 g', $style->fillColor?->renderNonStrokingOperator());
        self::assertSame('0 0 1 rg', $style->textColor?->renderNonStrokingOperator());
        self::assertSame('<< /ca 0.5 /CA 0.5 >>', $style->opacity?->renderExtGStateDictionary());
        self::assertSame($border, $style->border);
    }
}
