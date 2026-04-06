<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use Kalle\Pdf\Document\Table\Layout\PreparedTableCell;
use Kalle\Pdf\Document\Table\Layout\PreparedTableRow;
use Kalle\Pdf\Document\Table\Style\TablePadding;
use Kalle\Pdf\Document\Table\TableCell;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PreparedTableRowTest extends TestCase
{
    #[Test]
    public function it_stores_prepared_table_row_values(): void
    {
        $cells = [
            new PreparedTableCell(new TableCell('A'), 50, 0, 12, 10, 10, TablePadding::all(0)),
            new PreparedTableCell(new TableCell('B'), 60, 1, 14, 12, 12, TablePadding::all(0)),
        ];

        $row = new PreparedTableRow($cells, true);

        self::assertSame($cells, $row->cells);
        self::assertTrue($row->header);
    }
}
