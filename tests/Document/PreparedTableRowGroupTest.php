<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use Kalle\Pdf\Feature\Table\Layout\PreparedTableCell;
use Kalle\Pdf\Feature\Table\Layout\PreparedTableRow;
use Kalle\Pdf\Feature\Table\Layout\PreparedTableRowGroup;
use Kalle\Pdf\Feature\Table\Style\TablePadding;
use Kalle\Pdf\Feature\Table\TableCell;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PreparedTableRowGroupTest extends TestCase
{
    #[Test]
    public function it_reports_basic_group_state_and_can_slice_remaining_rows(): void
    {
        $group = new PreparedTableRowGroup(
            [
                new PreparedTableRow([$this->createPreparedCell('A')], false),
                new PreparedTableRow([$this->createPreparedCell('B')], false),
                new PreparedTableRow([$this->createPreparedCell('C')], false),
            ],
            [12.0, 14.0, 16.0],
        );

        self::assertFalse($group->isEmpty());
        self::assertSame(3, $group->count());

        $remaining = $group->slice(1);

        self::assertSame(2, $remaining->count());
        self::assertSame([14.0, 16.0], $remaining->rowHeights);
        self::assertSame('B', $remaining->rows[0]->cells[0]->cell->text);
        self::assertSame('C', $remaining->rows[1]->cells[0]->cell->text);
    }

    private function createPreparedCell(string $text): PreparedTableCell
    {
        return new PreparedTableCell(
            new TableCell($text),
            50.0,
            0,
            12.0,
            10.0,
            10.0,
            TablePadding::all(0.0),
        );
    }
}
