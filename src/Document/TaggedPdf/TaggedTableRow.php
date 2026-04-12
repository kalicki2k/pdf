<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document\TaggedPdf;

final readonly class TaggedTableRow
{
    /**
     * @param list<TaggedTableCell> $cells
     */
    public function __construct(
        public int $rowIndex,
        public array $cells,
    ) {
    }
}
