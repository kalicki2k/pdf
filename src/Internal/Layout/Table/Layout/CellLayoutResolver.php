<?php

declare(strict_types=1);

namespace Kalle\Pdf\Internal\Layout\Table\Layout;

use Kalle\Pdf\Internal\Layout\Table\Style\TablePadding;
use Kalle\Pdf\Internal\Layout\Value\VerticalAlign;

final readonly class CellLayoutResolver
{
    private const CELL_BOTTOM_EPSILON = 0.01;

    /**
     * @param list<float|int> $columnWidths
     */
    public function __construct(
        private float $tableX,
        private array $columnWidths,
    ) {
    }

    /**
     * @param list<float> $rowHeights
     */
    public function resolve(
        PreparedTableCell $preparedCell,
        int $rowIndex,
        array $rowHeights,
        float $rowTopY,
        VerticalAlign $verticalAlign,
        int $fontSize,
        ?int $visibleRowspan = null,
    ): PreparedTableCellLayout {
        $height = array_sum(array_slice($rowHeights, $rowIndex, $visibleRowspan ?? $preparedCell->cell->rowspan));
        $x = $this->tableX + $this->calculateColumnOffset($preparedCell->column);
        $bottomY = $rowTopY - $height;
        $padding = $preparedCell->padding;

        return new PreparedTableCellLayout(
            $x,
            $bottomY,
            $preparedCell->width,
            $height,
            $x + $padding->left,
            $this->resolveCellTextStartY(
                $rowTopY,
                $bottomY,
                $preparedCell->alignmentHeight,
                $verticalAlign,
                $fontSize,
                $preparedCell->padding,
            ),
            $preparedCell->width - $padding->horizontal(),
            ($bottomY + $padding->bottom) - self::CELL_BOTTOM_EPSILON,
        );
    }

    private function calculateColumnOffset(int $columnIndex): float
    {
        return array_sum(array_map(
            static fn (float | int $value): float => (float) $value,
            array_slice($this->columnWidths, 0, $columnIndex),
        ));
    }

    private function resolveCellTextStartY(
        float $cellTopY,
        float $cellBottomY,
        float $contentHeight,
        VerticalAlign $verticalAlign,
        int $fontSize,
        TablePadding $padding,
    ): float {
        $topStartY = $cellTopY - $padding->top - $fontSize;
        $bottomStartY = $cellBottomY + $padding->bottom + $contentHeight - $fontSize;

        return match ($verticalAlign) {
            VerticalAlign::TOP => $topStartY,
            VerticalAlign::MIDDLE => $bottomStartY + (($topStartY - $bottomStartY) / 2),
            VerticalAlign::BOTTOM => $bottomStartY,
        };
    }
}
