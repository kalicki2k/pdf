<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

final readonly class TableFooterContext
{
    public function __construct(
        public int $pageNumber,
        public int $completedBodyRowCount,
        public int $totalBodyRowCount,
        public bool $isLastPage,
    ) {
    }

    public function hasCompletedBodyRows(): bool
    {
        return $this->completedBodyRowCount > 0;
    }
}
