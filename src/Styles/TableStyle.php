<?php

declare(strict_types=1);

namespace Kalle\Pdf\Styles;

use Kalle\Pdf\Layout\VerticalAlign;
use Kalle\Pdf\Graphics\Color;

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
