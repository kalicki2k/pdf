<?php

declare(strict_types=1);

namespace Kalle\Pdf\Internal\Layout\Table\Layout;

use Kalle\Pdf\Table\Style\TablePadding;
use Kalle\Pdf\Table\TableCell;

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
