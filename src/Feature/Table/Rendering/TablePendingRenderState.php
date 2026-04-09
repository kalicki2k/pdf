<?php

declare(strict_types=1);

namespace Kalle\Pdf\Feature\Table\Rendering;

use Kalle\Pdf\Feature\Table\Layout\PreparedTableRow;
use Kalle\Pdf\Feature\Table\PendingRowspanCell;

/**
 * @internal Tracks pending table row groups and rowspan continuations while rendering.
 */
final class TablePendingRenderState
{
    /** @var list<PreparedTableRow> */
    private array $rows = [];

    /** @var list<PendingRowspanCell> */
    private array $pendingRowspanCells = [];

    public function addRow(PreparedTableRow $row): void
    {
        $this->rows[] = $row;
    }

    /**
     * @return list<PreparedTableRow>
     */
    public function rows(): array
    {
        return $this->rows;
    }

    /**
     * @return list<PendingRowspanCell>
     */
    public function pendingRowspanCells(): array
    {
        return $this->pendingRowspanCells;
    }

    public function hasPendingRowspanCells(): bool
    {
        return $this->pendingRowspanCells !== [];
    }

    /**
     * @param list<PendingRowspanCell> $pendingRowspanCells
     */
    public function replacePendingRowspanCells(array $pendingRowspanCells): void
    {
        $this->pendingRowspanCells = $pendingRowspanCells;
    }

    public function clear(): void
    {
        $this->rows = [];
        $this->pendingRowspanCells = [];
    }
}
