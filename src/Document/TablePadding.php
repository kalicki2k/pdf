<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use InvalidArgumentException;

final readonly class TablePadding
{
    private function __construct(
        public float $top,
        public float $right,
        public float $bottom,
        public float $left,
    ) {
        foreach ([$this->top, $this->right, $this->bottom, $this->left] as $value) {
            if ($value < 0) {
                throw new InvalidArgumentException('Table padding values must be zero or greater.');
            }
        }
    }

    public static function all(float $value): self
    {
        return new self($value, $value, $value, $value);
    }

    public static function symmetric(float $horizontal, float $vertical): self
    {
        return new self($vertical, $horizontal, $vertical, $horizontal);
    }

    public static function only(
        float $top = 0.0,
        float $right = 0.0,
        float $bottom = 0.0,
        float $left = 0.0,
    ): self {
        return new self($top, $right, $bottom, $left);
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
