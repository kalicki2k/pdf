<?php

declare(strict_types=1);

namespace Kalle\Pdf\Internal\Layout\Table\Layout;

use InvalidArgumentException;
use Kalle\Pdf\Table\TableCell;
use Kalle\Pdf\Text\TextSegment;

/**
 * @internal Prepares standalone table row groups such as repeated headers and footers.
 */
final readonly class RowGroupPreparer
{
    public function __construct(
        private RowPreparer $rowPreparer,
        private int $columnCount,
    ) {
    }

    /**
     * @param list<list<string|list<TextSegment>|TableCell>> $rows
     * @return list<PreparedTableRow>
     */
    public function prepareGroup(array $rows, bool $header, bool $footer, string $rowspanErrorMessage): array
    {
        $activeRowspans = array_fill(0, $this->columnCount, 0);
        $preparedRows = [];

        foreach ($rows as $row) {
            $preparedRow = $this->rowPreparer->prepareRow($row, $activeRowspans, $header, $footer);
            $preparedRows[] = new PreparedTableRow($preparedRow['cells'], $header, $footer);
            $activeRowspans = $preparedRow['nextRowspans'];
        }

        if (array_any($activeRowspans, static fn (int $remainingRows): bool => $remainingRows > 0)) {
            throw new InvalidArgumentException($rowspanErrorMessage);
        }

        return $preparedRows;
    }
}
