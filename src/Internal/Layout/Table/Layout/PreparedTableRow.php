<?php

declare(strict_types=1);

namespace Kalle\Pdf\Internal\Layout\Table\Layout;

final readonly class PreparedTableRow
{
    /**
     * @param list<PreparedTableCell> $cells
     */
    public function __construct(
        public array $cells,
        public bool $header,
        public bool $footer = false,
    ) {
    }
}
