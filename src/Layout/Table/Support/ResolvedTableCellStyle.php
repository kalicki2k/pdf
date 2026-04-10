<?php

declare(strict_types=1);

namespace Kalle\Pdf\Layout\Table\Support;

use Kalle\Pdf\Layout\Table\Style\TableBorder;
use Kalle\Pdf\Layout\Table\Style\TablePadding;
use Kalle\Pdf\Layout\Value\HorizontalAlign;
use Kalle\Pdf\Layout\Value\VerticalAlign;
use Kalle\Pdf\Style\Color;
use Kalle\Pdf\Style\Opacity;

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
