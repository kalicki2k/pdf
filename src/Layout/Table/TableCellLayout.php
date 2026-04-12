<?php

declare(strict_types=1);

namespace Kalle\Pdf\Layout\Table;

use Kalle\Pdf\Document\TableCell;
use Kalle\Pdf\Text\TextOptions;

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
        public CellPadding $padding,
        public Border $border,
        public TextOptions $textOptions,
        public array $wrappedLines,
    ) {
    }
}
