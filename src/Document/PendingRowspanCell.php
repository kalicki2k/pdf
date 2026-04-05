<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use Kalle\Pdf\Document\Table\Layout\PreparedTableCell;
use Kalle\Pdf\Document\Table\Support\ResolvedTableCellStyle;

final readonly class PendingRowspanCell
{
    /**
     * @param list<array{segments: array<int, TextSegment>, justify: bool}> $remainingLines
     */
    public function __construct(
        public PreparedTableCell $cell,
        public ResolvedTableCellStyle $style,
        public int $startRowIndex,
        public int $remainingRows,
        public bool $continuesFromPreviousPage = true,
        public array $remainingLines = [],
    ) {
    }
}
