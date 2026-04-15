<?php

declare(strict_types=1);

namespace Kalle\Pdf\Layout\Table;

final readonly class TableRowGroupLayout
{
    public function __construct(
        public int $startRowIndex,
        public int $endRowIndex,
        public float $height,
    ) {
    }
}
