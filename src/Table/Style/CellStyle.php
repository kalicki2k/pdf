<?php

declare(strict_types=1);

namespace Kalle\Pdf\Table\Style;

use Kalle\Pdf\Layout\HorizontalAlign;
use Kalle\Pdf\Layout\VerticalAlign;
use Kalle\Pdf\Style\Color;
use Kalle\Pdf\Style\Opacity;

final readonly class CellStyle
{
    public function __construct(
        public ?HorizontalAlign $horizontalAlign = null,
        public ?VerticalAlign $verticalAlign = null,
        public ?TablePadding $padding = null,
        public ?Color $fillColor = null,
        public ?Color $textColor = null,
        public ?Opacity $opacity = null,
        public ?TableBorder $border = null,
    ) {
    }
}
