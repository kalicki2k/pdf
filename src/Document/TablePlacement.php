<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use InvalidArgumentException;

final readonly class TablePlacement
{
    public function __construct(
        public float $x,
        public float $width,
        public ?float $y = null,
    ) {
        if ($this->width <= 0.0) {
            throw new InvalidArgumentException('Table placement width must be greater than zero.');
        }
    }

    public static function at(float $x, float $y, float $width): self
    {
        return new self($x, $width, $y);
    }
}
