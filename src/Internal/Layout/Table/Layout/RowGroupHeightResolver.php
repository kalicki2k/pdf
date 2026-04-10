<?php

declare(strict_types=1);

namespace Kalle\Pdf\Internal\Layout\Table\Layout;

use InvalidArgumentException;

final readonly class RowGroupHeightResolver
{
    /**
     * @param list<PreparedTableRow> $preparedRows
     * @return list<float>
     */
    public function resolve(array $preparedRows): array
    {
        $rowHeights = array_fill(0, count($preparedRows), 0.0);

        foreach ($preparedRows as $rowIndex => $preparedRow) {
            foreach ($preparedRow->cells as $preparedCell) {
                if ($preparedCell->cell->rowspan === 1) {
                    $rowHeights[$rowIndex] = max($rowHeights[$rowIndex], $preparedCell->minHeight);
                }
            }
        }

        foreach ($preparedRows as $rowIndex => $preparedRow) {
            foreach ($preparedRow->cells as $preparedCell) {
                $rowspan = $preparedCell->cell->rowspan;

                if ($rowspan === 1) {
                    continue;
                }

                if (($rowIndex + $rowspan) > count($preparedRows)) {
                    throw new InvalidArgumentException('Rowspan groups must be completed by subsequent rows.');
                }

                $currentHeight = array_sum(array_slice($rowHeights, $rowIndex, $rowspan));
                $missingHeight = $preparedCell->minHeight - $currentHeight;

                if ($missingHeight > 0) {
                    $rowHeights[$rowIndex + $rowspan - 1] += $missingHeight;
                }
            }
        }

        return array_values($rowHeights);
    }
}
