<?php

declare(strict_types=1);

namespace Kalle\Pdf\Layout\Table;

use InvalidArgumentException;

final readonly class CellPadding
{
    public function __construct(
        public float $top,
        public float $right,
        public float $bottom,
        public float $left,
    ) {
        foreach ([$this->top, $this->right, $this->bottom, $this->left] as $value) {
            if ($value < 0.0) {
                throw new InvalidArgumentException('Cell padding values must not be negative.');
            }
        }
    }

    public static function all(float $value): self
    {
        return new self($value, $value, $value, $value);
    }

    public static function symmetric(float $vertical, float $horizontal): self
    {
        return new self($vertical, $horizontal, $vertical, $horizontal);
    }

    public function horizontal(): float
    {
        return $this->left + $this->right;
    }

    public function vertical(): float
    {
        return $this->top + $this->bottom;
    }
}
