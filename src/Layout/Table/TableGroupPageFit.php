<?php

declare(strict_types=1);

namespace Kalle\Pdf\Layout\Table;

final readonly class TableGroupPageFit
{
    public function __construct(
        public bool $repeatHeaders,
        public int $fittingRowCountOnCurrentPage,
    ) {
    }
}
