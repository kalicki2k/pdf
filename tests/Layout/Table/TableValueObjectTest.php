<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Layout\Table;

use InvalidArgumentException;
use Kalle\Pdf\Color\Color;
use Kalle\Pdf\Document\TableCell;
use Kalle\Pdf\Layout\Table\Border;
use Kalle\Pdf\Layout\Table\CellPadding;
use Kalle\Pdf\Layout\Table\ColumnWidth;
use Kalle\Pdf\Layout\Table\VerticalAlign;
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

    public function testTableCellCanCarryBackgroundAndVerticalAlignment(): void
    {
        $cell = TableCell::text('Value')
            ->withBackgroundColor(Color::hex('#ffeecc'))
            ->withVerticalAlign(VerticalAlign::MIDDLE);

        self::assertSame(VerticalAlign::MIDDLE, $cell->verticalAlign);
        self::assertEquals(Color::hex('#ffeecc'), $cell->backgroundColor);
    }
}
