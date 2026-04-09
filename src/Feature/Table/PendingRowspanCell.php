<?php

declare(strict_types=1);

namespace Kalle\Pdf\Feature\Table;

use Kalle\Pdf\Feature\Table\Layout\PreparedTableCell;
use Kalle\Pdf\Feature\Table\Support\ResolvedTableCellStyle;
use Kalle\Pdf\Feature\Text\TextSegment;

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
