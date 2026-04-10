<?php

declare(strict_types=1);

namespace Kalle\Pdf\Internal\Layout\Table\Style;

use Kalle\Pdf\Internal\Layout\Value\VerticalAlign;
use Kalle\Pdf\Style\Color;

final readonly class TableStyle
{
    public function __construct(
        public ?TablePadding $padding = null,
        public ?TableBorder $border = null,
        public ?VerticalAlign $verticalAlign = null,
        public ?Color $fillColor = null,
        public ?Color $textColor = null,
    ) {
    }
}
