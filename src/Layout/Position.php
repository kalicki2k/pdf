<?php

declare(strict_types=1);

namespace Kalle\Pdf\Layout;

final readonly class Position
{
    public function __construct(
        public float $x,
        public float $y,
    ) {
    }
}
