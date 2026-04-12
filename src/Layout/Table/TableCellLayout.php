<?php

declare(strict_types=1);

namespace Kalle\Pdf\Layout\Table;

use Kalle\Pdf\Document\TableCell;

final readonly class TableCellLayout
{
    /**
     * @param list<string> $wrappedLines
     */
    public function __construct(
        public TableCell $cell,
        public int $rowIndex,
        public int $columnIndex,
        public float $width,
        public float $contentWidth,
        public float $height,
        public array $wrappedLines,
    ) {
    }
}
