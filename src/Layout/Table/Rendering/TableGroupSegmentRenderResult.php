<?php

declare(strict_types=1);

namespace Kalle\Pdf\Layout\Table\Rendering;

use Kalle\Pdf\Layout\Table\PendingRowspanCell;
use Kalle\Pdf\Page\Page;

/**
 * @internal Carries the updated page state after rendering a partial table row group.
 */
final class TableGroupSegmentRenderResult
{
    /**
     * @param list<PendingRowspanCell> $pendingRowspanCells
     */
    public function __construct(
        public readonly Page $page,
        public readonly float $cursorY,
        public readonly array $pendingRowspanCells,
    ) {
    }
}
