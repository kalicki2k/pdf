<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use Kalle\Pdf\Graphics\Color;
use Kalle\Pdf\Graphics\Opacity;
use Kalle\Pdf\Layout\HorizontalAlign;
use Kalle\Pdf\Layout\VerticalAlign;
use Kalle\Pdf\Styles\TableBorder;
use Kalle\Pdf\Styles\TablePadding;

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
