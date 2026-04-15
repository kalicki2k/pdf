<?php

declare(strict_types=1);

namespace Kalle\Pdf\Drawing;

use InvalidArgumentException;
use Kalle\Pdf\Color\Color;

final readonly class StrokeStyle
{
    public function __construct(
        public float $width = 1.0,
        public ?Color $color = null,
    ) {
        if ($this->width <= 0.0) {
            throw new InvalidArgumentException('Stroke width must be greater than zero.');
        }
    }
}
