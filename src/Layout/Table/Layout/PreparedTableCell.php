<?php

declare(strict_types=1);

namespace Kalle\Pdf\Layout\Table\Layout;

use Kalle\Pdf\Layout\Table\Definition\TableCell;
use Kalle\Pdf\Layout\Table\Style\TablePadding;

final readonly class PreparedTableCell
{
    public function __construct(
        public TableCell $cell,
        public float $width,
        public int $column,
        public float $minHeight,
        public float $contentHeight,
        public float $alignmentHeight,
        public TablePadding $padding,
    ) {
    }
}
