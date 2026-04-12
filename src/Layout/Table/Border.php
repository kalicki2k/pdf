<?php

declare(strict_types=1);

namespace Kalle\Pdf\Layout\Table;

use InvalidArgumentException;

final readonly class Border
{
    public function __construct(
        public float $top,
        public float $right,
        public float $bottom,
        public float $left,
    ) {
        foreach ([$this->top, $this->right, $this->bottom, $this->left] as $value) {
            if ($value < 0.0) {
                throw new InvalidArgumentException('Border widths must not be negative.');
            }
        }
    }

    public static function none(): self
    {
        return new self(0.0, 0.0, 0.0, 0.0);
    }

    public static function all(float $width): self
    {
        return new self($width, $width, $width, $width);
    }

    public function isVisible(): bool
    {
        return $this->top > 0.0
            || $this->right > 0.0
            || $this->bottom > 0.0
            || $this->left > 0.0;
    }
}
