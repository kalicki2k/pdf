<?php

declare(strict_types=1);

namespace Kalle\Pdf\Internal\Layout\Table\Support;

use Kalle\Pdf\Internal\Style\Color;
use Kalle\Pdf\Internal\Style\Opacity;

final readonly class ResolvedBorderSide
{
    public function __construct(
        public float $width,
        public ?Color $color,
        public ?Opacity $opacity,
    ) {
    }
}
