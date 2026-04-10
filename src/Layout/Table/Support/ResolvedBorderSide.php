<?php

declare(strict_types=1);

namespace Kalle\Pdf\Layout\Table\Support;

use Kalle\Pdf\Style\Color;
use Kalle\Pdf\Style\Opacity;

final readonly class ResolvedBorderSide
{
    public function __construct(
        public float $width,
        public ?Color $color,
        public ?Opacity $opacity,
    ) {
    }
}
