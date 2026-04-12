<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use InvalidArgumentException;

final readonly class TablePlacement
{
    public function __construct(
        public float $x,
        public float $width,
    ) {
        if ($this->width <= 0.0) {
            throw new InvalidArgumentException('Table placement width must be greater than zero.');
        }
    }
}
