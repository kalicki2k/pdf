<?php

declare(strict_types=1);

namespace Kalle\Pdf\Styles;

use Kalle\Pdf\Document\HorizontalAlign;
use Kalle\Pdf\Document\VerticalAlign;
use Kalle\Pdf\Graphics\Color;
use Kalle\Pdf\Graphics\Opacity;

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
