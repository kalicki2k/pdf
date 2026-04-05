<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

final readonly class TableGroupPageFit
{
    public function __construct(
        public bool $fitsOnCurrentPage,
        public bool $fitsOnFreshPage,
        public bool $repeatHeaders,
        public int $fittingRowCountOnCurrentPage,
        public int $fittingRowCountOnFreshPage,
    ) {
    }
}
