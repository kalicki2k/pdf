<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use Kalle\Pdf\Feature\Table\Layout\PreparedTableCell;
use Kalle\Pdf\Feature\Table\Style\TablePadding;
use Kalle\Pdf\Feature\Table\TableCell;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PreparedTableCellTest extends TestCase
{
    #[Test]
    public function it_stores_prepared_table_cell_values(): void
    {
        $cell = new TableCell('Value', colspan: 2, rowspan: 3);
        $padding = TablePadding::only(top: 1, right: 2, bottom: 3, left: 4);

        $preparedCell = new PreparedTableCell($cell, 120, 1, 18, 14, 16, $padding);

        self::assertSame($cell, $preparedCell->cell);
        self::assertSame(120.0, $preparedCell->width);
        self::assertSame(1, $preparedCell->column);
        self::assertSame(18.0, $preparedCell->minHeight);
        self::assertSame(14.0, $preparedCell->contentHeight);
        self::assertSame(16.0, $preparedCell->alignmentHeight);
        self::assertSame($padding, $preparedCell->padding);
    }
}
