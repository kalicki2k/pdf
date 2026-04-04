<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use Kalle\Pdf\Styles\TablePadding;

final readonly class PreparedTableCell
{
    public function __construct(
        public TableCell $cell,
        public float $width,
        public int $column,
        public float $minHeight,
        public float $contentHeight,
        public TablePadding $padding,
    ) {
    }
}
