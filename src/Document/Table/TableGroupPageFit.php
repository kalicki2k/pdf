<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document\Table;

final readonly class TableGroupPageFit
{
    public function __construct(
        public bool $repeatHeaders,
        public int $fittingRowCountOnCurrentPage,
    ) {
    }
}
