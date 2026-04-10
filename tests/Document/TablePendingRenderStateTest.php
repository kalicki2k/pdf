<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use Kalle\Pdf\Internal\Layout\Table\Layout\PreparedTableCell;
use Kalle\Pdf\Internal\Layout\Table\Layout\PreparedTableRow;
use Kalle\Pdf\Internal\Layout\Table\PendingRowspanCell;
use Kalle\Pdf\Internal\Layout\Table\Rendering\TablePendingRenderState;
use Kalle\Pdf\Internal\Layout\Table\Support\ResolvedTableCellStyle;
use Kalle\Pdf\Internal\Layout\Value\HorizontalAlign;
use Kalle\Pdf\Internal\Layout\Value\VerticalAlign;
use Kalle\Pdf\Table\Style\TablePadding;
use Kalle\Pdf\Table\TableCell;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TablePendingRenderStateTest extends TestCase
{
    #[Test]
    public function it_tracks_pending_rows_and_rowspan_cells_until_cleared(): void
    {
        $state = new TablePendingRenderState();
        $preparedCell = $this->createPreparedCell();
        $preparedRow = new PreparedTableRow([$preparedCell], false);
        $pendingRowspanCell = new PendingRowspanCell(
            $preparedCell,
            new ResolvedTableCellStyle(
                TablePadding::all(4.0),
                null,
                null,
                VerticalAlign::TOP,
                HorizontalAlign::LEFT,
                null,
                null,
                null,
            ),
            2,
        );

        $state->addRow($preparedRow);
        $state->replacePendingRowspanCells([$pendingRowspanCell]);

        self::assertSame([$preparedRow], $state->rows());
        self::assertSame([$pendingRowspanCell], $state->pendingRowspanCells());
        self::assertTrue($state->hasPendingRowspanCells());

        $state->clear();

        self::assertSame([], $state->rows());
        self::assertSame([], $state->pendingRowspanCells());
        self::assertFalse($state->hasPendingRowspanCells());
    }

    private function createPreparedCell(): PreparedTableCell
    {
        return new PreparedTableCell(
            new TableCell('Cell'),
            100.0,
            0,
            12.0,
            12.0,
            12.0,
            TablePadding::all(4.0),
        );
    }
}
