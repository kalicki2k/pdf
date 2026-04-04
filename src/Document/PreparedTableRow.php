<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

final readonly class PreparedTableRow
{
    /**
     * @param list<PreparedTableCell> $cells
     */
    public function __construct(
        public array $cells,
        public bool $header,
    ) {
    }
}
