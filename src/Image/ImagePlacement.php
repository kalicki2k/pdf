<?php

declare(strict_types=1);

namespace Kalle\Pdf\Image;

use InvalidArgumentException;

final readonly class ImagePlacement
{
    public function __construct(
        public float $x,
        public float $y,
        public ?float $width = null,
        public ?float $height = null,
    ) {
        if ($this->width !== null && $this->width <= 0.0) {
            throw new InvalidArgumentException('Image width must be greater than 0.');
        }

        if ($this->height !== null && $this->height <= 0.0) {
            throw new InvalidArgumentException('Image height must be greater than 0.');
        }
    }

    public static function at(float $x, float $y, ?float $width = null, ?float $height = null): self
    {
        return new self($x, $y, $width, $height);
    }
}
