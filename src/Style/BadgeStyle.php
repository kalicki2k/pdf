<?php

declare(strict_types=1);

namespace Kalle\Pdf\Style;

use InvalidArgumentException;
use Kalle\Pdf\Graphics\Color;
use Kalle\Pdf\Graphics\Opacity;

final readonly class BadgeStyle
{
    public function __construct(
        public float $paddingHorizontal = 6.0,
        public float $paddingVertical = 3.0,
        public float $cornerRadius = 0.0,
        public ?Color $fillColor = null,
        public ?Color $textColor = null,
        public ?float $borderWidth = null,
        public ?Color $borderColor = null,
        public ?Opacity $opacity = null,
    ) {
        if ($this->paddingHorizontal < 0) {
            throw new InvalidArgumentException('Badge horizontal padding must be zero or greater.');
        }

        if ($this->paddingVertical < 0) {
            throw new InvalidArgumentException('Badge vertical padding must be zero or greater.');
        }

        if ($this->cornerRadius < 0) {
            throw new InvalidArgumentException('Badge corner radius must be zero or greater.');
        }

        if ($this->borderWidth !== null && $this->borderWidth <= 0) {
            throw new InvalidArgumentException('Badge border width must be greater than zero.');
        }
    }
}
