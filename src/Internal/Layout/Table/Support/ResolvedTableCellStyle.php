<?php

declare(strict_types=1);

namespace Kalle\Pdf\Internal\Layout\Table\Support;

use Kalle\Pdf\Layout\HorizontalAlign;
use Kalle\Pdf\Layout\VerticalAlign;
use Kalle\Pdf\Style\Color;
use Kalle\Pdf\Style\Opacity;
use Kalle\Pdf\Table\Style\TableBorder;
use Kalle\Pdf\Table\Style\TablePadding;

final readonly class ResolvedTableCellStyle
{
    public function __construct(
        public TablePadding $padding,
        public ?Color $fillColor,
        public ?Color $textColor,
        public VerticalAlign $verticalAlign,
        public HorizontalAlign $horizontalAlign,
        public ?Opacity $opacity,
        public ?TableBorder $rowBorder,
        public ?TableBorder $cellBorder,
    ) {
    }
}
