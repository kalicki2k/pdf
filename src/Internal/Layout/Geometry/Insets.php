<?php

declare(strict_types=1);

namespace Kalle\Pdf\Internal\Layout\Geometry;

final readonly class Insets
{
    public function __construct(
        public float $top = 0.0,
        public float $right = 0.0,
        public float $bottom = 0.0,
        public float $left = 0.0,
    ) {
    }

    public static function all(float $value): self
    {
        return new self($value, $value, $value, $value);
    }

    public static function symmetric(float $horizontal, float $vertical): self
    {
        return new self($vertical, $horizontal, $vertical, $horizontal);
    }
}
