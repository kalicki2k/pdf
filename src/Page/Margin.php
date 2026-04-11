<?php

declare(strict_types=1);

namespace Kalle\Pdf\Page;

final readonly class Margin
{
    public function __construct(
        public float $top,
        public float $right,
        public float $bottom,
        public float $left,
    ) {
    }

    public static function all(float $value): self
    {
        return new self($value, $value, $value, $value);
    }

    public static function symmetric(float $vertical, float $horizontal): self
    {
        return new self($vertical, $horizontal, $vertical, $horizontal);
    }
}
