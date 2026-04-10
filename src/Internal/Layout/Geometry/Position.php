<?php

declare(strict_types=1);

namespace Kalle\Pdf\Internal\Layout\Geometry;

final readonly class Position
{
    public function __construct(
        public float $x,
        public float $y,
    ) {
    }
}
