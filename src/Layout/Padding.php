<?php

declare(strict_types=1);

namespace Kalle\Pdf\Layout;

use InvalidArgumentException;

/**
 * Immutable padding value object expressed in PDF points.
 */
final readonly class Padding
{
    /**
     * Creates equal padding for all four sides.
     */
    public static function all(float $value): self
    {
        return new self($value, $value, $value, $value);
    }

    /**
     * Creates explicit padding for each side.
     */
    public static function make(float $top, float $right, float $bottom, float $left): self
    {
        return new self($top, $right, $bottom, $left);
    }

    /**
     * Creates padding with shared vertical and horizontal values.
     */
    public static function symmetric(float $vertical, float $horizontal): self
    {
        return new self($vertical, $horizontal, $vertical, $horizontal);
    }

    /**
     * @param float $top Top padding in PDF points.
     * @param float $right Right padding in PDF points.
     * @param float $bottom Bottom padding in PDF points.
     * @param float $left Left padding in PDF points.
     *
     * @throws InvalidArgumentException If any padding value is negative.
     */
    private function __construct(
        public float $top,
        public float $right,
        public float $bottom,
        public float $left,
    ) {
        foreach ([$this->top, $this->right, $this->bottom, $this->left] as $value) {
            if ($value < 0.0) {
                throw new InvalidArgumentException('Padding values must not be negative.');
            }
        }
    }

    /**
     * Returns the total horizontal padding in PDF points.
     */
    public function horizontal(): float
    {
        return $this->left + $this->right;
    }

    /**
     * Returns the total vertical padding in PDF points.
     */
    public function vertical(): float
    {
        return $this->top + $this->bottom;
    }
}
