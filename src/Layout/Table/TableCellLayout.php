<?php

declare(strict_types=1);

namespace Kalle\Pdf\Layout\Table;

use Kalle\Pdf\Document\TableCell;
use Kalle\Pdf\Text\TextOptions;
use Kalle\Pdf\Text\TextSegment;

final readonly class TableCellLayout
{
    /**
     * @param list<string> $wrappedLines
     * @param list<list<TextSegment>>|null $wrappedSegmentLines
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
        public ?array $wrappedSegmentLines = null,
    ) {
    }

    public function lineCount(): int
    {
        return $this->wrappedSegmentLines !== null
            ? count($this->wrappedSegmentLines)
            : count($this->wrappedLines);
    }

    public function usesRichText(): bool
    {
        return $this->wrappedSegmentLines !== null;
    }
}
