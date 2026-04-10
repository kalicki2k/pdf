<?php

declare(strict_types=1);

namespace Kalle\Pdf\Table\Style;

use Kalle\Pdf\Graphics\Color;
use Kalle\Pdf\Layout\VerticalAlign;

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
