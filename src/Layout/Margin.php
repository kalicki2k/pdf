<?php

declare(strict_types=1);

namespace Kalle\Pdf\Layout;

/**
 * Immutable margin value object expressed in PDF points.
 */
readonly class Margin
{
    /**
     * Creates explicit margins for each side.
     */
    public static function make(
        float $top,
        float $right,
        float $bottom,
        float $left,
    ): self {
        return new self($top, $right, $bottom, $left);
    }

    /**
     * Creates equal margins for all four sides.
     */
    public static function all(float $value): self
    {
        return new self($value, $value, $value, $value);
    }

    /**
     * Creates margins with shared vertical and horizontal values.
     */
    public static function symmetric(float $vertical, float $horizontal): self
    {
        return new self($vertical, $horizontal, $vertical, $horizontal);
    }

    /**
     * @param float $top Top margin in PDF points.
     * @param float $right Right margin in PDF points.
     * @param float $bottom Bottom margin in PDF points.
     * @param float $left Left margin in PDF points.
     */
    private function __construct(
        public float $top,
        public float $right,
        public float $bottom,
        public float $left,
    ) {
    }
}
