<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Layout\Table;

use InvalidArgumentException;
use Kalle\Pdf\Color\Color;
use Kalle\Pdf\Document\TableCell;
use Kalle\Pdf\Document\TablePlacement;
use Kalle\Pdf\Layout\Table\Border;
use Kalle\Pdf\Layout\Table\CellPadding;
use Kalle\Pdf\Layout\Table\ColumnWidth;
use Kalle\Pdf\Layout\Table\VerticalAlign;
use Kalle\Pdf\Text\TextAlign;
use PHPUnit\Framework\TestCase;

final class TableValueObjectTest extends TestCase
{
    public function testColumnWidthRejectsNonPositiveValues(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ColumnWidth::fixed(0.0);
    }

    public function testCellPaddingReportsCombinedInsets(): void
    {
        $padding = CellPadding::symmetric(3.0, 5.0);

        self::assertSame(10.0, $padding->horizontal());
        self::assertSame(6.0, $padding->vertical());
    }

    public function testBorderVisibilityDependsOnAnySideWidth(): void
    {
        self::assertFalse(Border::none()->isVisible());
        self::assertTrue(Border::all(0.5)->isVisible());
    }

    public function testTablePlacementRejectsNonPositiveWidths(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new TablePlacement(30.0, 0.0);
    }

    public function testTableCellCanCarryCellSpecificOverrides(): void
    {
        $cell = TableCell::text('Value')
            ->withBackgroundColor(Color::hex('#ffeecc'))
            ->withVerticalAlign(VerticalAlign::MIDDLE)
            ->withHorizontalAlign(TextAlign::RIGHT)
            ->withPadding(CellPadding::symmetric(2.0, 6.0))
            ->withBorder(new Border(1.0, 0.0, 1.0, 0.0));

        self::assertSame(VerticalAlign::MIDDLE, $cell->verticalAlign);
        self::assertSame(TextAlign::RIGHT, $cell->horizontalAlign);
        self::assertEquals(Color::hex('#ffeecc'), $cell->backgroundColor);
        self::assertEquals(CellPadding::symmetric(2.0, 6.0), $cell->padding);
        self::assertEquals(new Border(1.0, 0.0, 1.0, 0.0), $cell->border);
    }
}
