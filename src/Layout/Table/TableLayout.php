<?php

declare(strict_types=1);

namespace Kalle\Pdf\Layout\Table;

use function array_sum;

final readonly class TableLayout
{
    /**
     * @param list<float> $columnWidths
     * @param list<float> $rowHeights
     * @param list<TableCellLayout> $cells
     * @param list<TableRowGroupLayout> $rowGroups
     */
    public function __construct(
        public array $columnWidths,
        public array $rowHeights,
        public array $cells,
        public array $rowGroups,
    ) {
    }

    public function rowTopY(int $rowIndex, float $tableTopY): float
    {
        $y = $tableTopY;

        for ($index = 0; $index < $rowIndex; $index++) {
            $y -= $this->rowHeights[$index];
        }

        return $y;
    }

    public function cellHeight(TableCellLayout $cellLayout): float
    {
        $height = 0.0;

        for ($index = 0; $index < $cellLayout->cell->rowspan; $index++) {
            $height += $this->rowHeights[$cellLayout->rowIndex + $index];
        }

        return $height;
    }

    public function totalHeight(): float
    {
        return array_sum($this->rowHeights);
    }
}
