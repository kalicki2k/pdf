<?php

declare(strict_types=1);

namespace Kalle\Pdf\Layout\Table\Rendering;

use Kalle\Pdf\Layout\Table\Layout\PreparedTableRow;
use Kalle\Pdf\Layout\Table\TableGroupPageFit;

/**
 * @internal Decides how many pending table rows fit on the current page.
 */
final class TablePendingGroupPaginator
{
    /**
     * @param list<float> $rowHeights
     */
    public function resolvePageFit(array $rowHeights, float $availableHeight, bool $repeatHeaders): TableGroupPageFit
    {
        return new TableGroupPageFit(
            repeatHeaders: $repeatHeaders,
            fittingRowCountOnCurrentPage: $this->countFittingRows($rowHeights, $availableHeight),
        );
    }

    /**
     * @param list<PreparedTableRow> $preparedRows
     */
    public function shouldDeferLeadingSplit(array $preparedRows, bool $hasPendingRowspanCells, int $fittingRowCount): bool
    {
        if ($hasPendingRowspanCells || $fittingRowCount !== 1 || $preparedRows === []) {
            return false;
        }

        foreach ($preparedRows[0]->cells as $preparedCell) {
            if ($preparedCell->cell->rowspan > 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<float> $rowHeights
     */
    private function countFittingRows(array $rowHeights, float $availableHeight): int
    {
        $usedHeight = 0.0;
        $fittingRows = 0;

        foreach ($rowHeights as $rowHeight) {
            if (($usedHeight + $rowHeight) > $availableHeight) {
                break;
            }

            $usedHeight += $rowHeight;
            $fittingRows++;
        }

        return $fittingRows;
    }
}
