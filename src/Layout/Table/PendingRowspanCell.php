<?php

declare(strict_types=1);

namespace Kalle\Pdf\Layout\Table;

use Kalle\Pdf\Layout\Table\Layout\PreparedTableCell;
use Kalle\Pdf\Layout\Table\Support\ResolvedTableCellStyle;
use Kalle\Pdf\Layout\Text\Input\TextSegment;

final readonly class PendingRowspanCell
{
    /**
     * @param list<array{segments: array<int, TextSegment>, justify: bool}> $remainingLines
     */
    public function __construct(
        public PreparedTableCell $cell,
        public ResolvedTableCellStyle $style,
        public int $remainingRows,
        public array $remainingLines = [],
    ) {
    }
}
