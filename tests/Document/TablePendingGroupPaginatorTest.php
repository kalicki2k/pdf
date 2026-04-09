<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use Kalle\Pdf\Feature\Table\Layout\PreparedTableCell;
use Kalle\Pdf\Feature\Table\Layout\PreparedTableRow;
use Kalle\Pdf\Feature\Table\Rendering\TablePendingGroupPaginator;
use Kalle\Pdf\Feature\Table\Style\TablePadding;
use Kalle\Pdf\Feature\Table\TableCell;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TablePendingGroupPaginatorTest extends TestCase
{
    #[Test]
    public function it_counts_how_many_rows_fit_on_the_current_page(): void
    {
        $paginator = new TablePendingGroupPaginator();

        $pageFit = $paginator->resolvePageFit([20.0, 25.0, 30.0], 50.0, true);

        self::assertTrue($pageFit->repeatHeaders);
        self::assertSame(2, $pageFit->fittingRowCountOnCurrentPage);
    }

    #[Test]
    public function it_defers_a_leading_split_for_a_single_fitting_row_with_rowspan(): void
    {
        $paginator = new TablePendingGroupPaginator();
        $preparedRows = [
            new PreparedTableRow([$this->createPreparedCell(rowspan: 2)], false),
        ];

        self::assertTrue($paginator->shouldDeferLeadingSplit($preparedRows, false, 1));
        self::assertFalse($paginator->shouldDeferLeadingSplit($preparedRows, true, 1));
        self::assertFalse($paginator->shouldDeferLeadingSplit($preparedRows, false, 2));
    }

    private function createPreparedCell(int $rowspan): PreparedTableCell
    {
        return new PreparedTableCell(
            new TableCell('Cell', rowspan: $rowspan),
            100.0,
            0,
            12.0,
            12.0,
            12.0,
            TablePadding::all(4.0),
        );
    }
}
