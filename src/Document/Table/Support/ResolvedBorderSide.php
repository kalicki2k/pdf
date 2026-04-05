<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document\Table\Support;

use Kalle\Pdf\Graphics\Color;
use Kalle\Pdf\Graphics\Opacity;

final readonly class ResolvedBorderSide
{
    public function __construct(
        public float $width,
        public ?Color $color,
        public ?Opacity $opacity,
    ) {
    }
}
