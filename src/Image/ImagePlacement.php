<?php

declare(strict_types=1);

namespace Kalle\Pdf\Image;

use InvalidArgumentException;

final readonly class ImagePlacement
{
    public function __construct(
        public ?float $x = null,
        public ?float $y = null,
        public ?float $width = null,
        public ?float $height = null,
        public ?ImageAlign $align = null,
        public float $spacingBefore = 0.0,
        public float $spacingAfter = 0.0,
    ) {
        if (($this->x === null) !== ($this->y === null)) {
            throw new InvalidArgumentException('Image x and y must be provided together.');
        }

        if ($this->width !== null && $this->width <= 0.0) {
            throw new InvalidArgumentException('Image width must be greater than 0.');
        }

        if ($this->height !== null && $this->height <= 0.0) {
            throw new InvalidArgumentException('Image height must be greater than 0.');
        }

        if ($this->spacingBefore < 0.0) {
            throw new InvalidArgumentException('Image spacingBefore must be greater than or equal to 0.');
        }

        if ($this->spacingAfter < 0.0) {
            throw new InvalidArgumentException('Image spacingAfter must be greater than or equal to 0.');
        }

        if ($this->x !== null && $this->align !== null) {
            throw new InvalidArgumentException('Aligned flow images cannot also define absolute x/y coordinates.');
        }
    }

    public static function at(float $x, float $y, ?float $width = null, ?float $height = null): self
    {
        return new self($x, $y, $width, $height);
    }

    public static function flow(
        ?float $width = null,
        ?float $height = null,
        ImageAlign $align = ImageAlign::LEFT,
        float $spacingBefore = 0.0,
        float $spacingAfter = 0.0,
    ): self {
        return new self(
            width: $width,
            height: $height,
            align: $align,
            spacingBefore: $spacingBefore,
            spacingAfter: $spacingAfter,
        );
    }

    public function isAbsolute(): bool
    {
        return $this->x !== null;
    }

    public function isFlow(): bool
    {
        return !$this->isAbsolute();
    }
}
