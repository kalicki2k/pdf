<?php

declare(strict_types=1);

namespace Kalle\Pdf\Page;

use InvalidArgumentException;

final readonly class PopupAnnotationDefinition
{
    public function __construct(
        public float $x,
        public float $y,
        public float $width,
        public float $height,
        public bool $open = false,
    ) {
        if ($this->width <= 0.0) {
            throw new InvalidArgumentException('Popup annotation width must be greater than zero.');
        }

        if ($this->height <= 0.0) {
            throw new InvalidArgumentException('Popup annotation height must be greater than zero.');
        }
    }
}
