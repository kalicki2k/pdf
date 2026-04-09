<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document\Table\Layout;

/**
 * @internal Bundles prepared table rows with their resolved heights.
 */
final readonly class PreparedTableRowGroup
{
    /**
     * @param list<PreparedTableRow> $rows
     * @param list<float> $rowHeights
     */
    public function __construct(
        public array $rows,
        public array $rowHeights,
    ) {
    }
}
