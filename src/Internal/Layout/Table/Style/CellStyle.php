<?php

declare(strict_types=1);

namespace Kalle\Pdf\Internal\Layout\Table\Style;

use Kalle\Pdf\Internal\Layout\Value\HorizontalAlign;
use Kalle\Pdf\Internal\Layout\Value\VerticalAlign;
use Kalle\Pdf\Internal\Style\Color;
use Kalle\Pdf\Internal\Style\Opacity;

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
