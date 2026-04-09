<?php

declare(strict_types=1);

namespace Kalle\Pdf\Feature\Table\Rendering;

use Kalle\Pdf\Document\Page;
use Kalle\Pdf\Feature\Table\PendingRowspanCell;

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
