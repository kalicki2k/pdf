<?php

declare(strict_types=1);

namespace Kalle\Pdf\Internal\Layout\Table;

use Kalle\Pdf\Internal\Layout\Table\Layout\PreparedTableCell;
use Kalle\Pdf\Internal\Layout\Table\Support\ResolvedTableCellStyle;
use Kalle\Pdf\Text\TextSegment;

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
