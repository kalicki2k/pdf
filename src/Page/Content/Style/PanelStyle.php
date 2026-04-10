<?php

declare(strict_types=1);

namespace Kalle\Pdf\Page\Content\Style;

use InvalidArgumentException;
use Kalle\Pdf\Layout\Value\HorizontalAlign;
use Kalle\Pdf\Style\Color;
use Kalle\Pdf\Style\Opacity;

final readonly class PanelStyle
{
    public function __construct(
        public float $paddingHorizontal = 10.0,
        public float $paddingVertical = 8.0,
        public float $cornerRadius = 6.0,
        public float $titleSpacing = 6.0,
        public int $titleSize = 13,
        public int $bodySize = 11,
        public HorizontalAlign $titleAlign = HorizontalAlign::LEFT,
        public HorizontalAlign $bodyAlign = HorizontalAlign::LEFT,
        public ?Color $fillColor = null,
        public ?Color $titleColor = null,
        public ?Color $bodyColor = null,
        public ?float $borderWidth = 1.0,
        public ?Color $borderColor = null,
        public ?Opacity $opacity = null,
    ) {
        if ($this->paddingHorizontal < 0) {
            throw new InvalidArgumentException('Panel horizontal padding must be zero or greater.');
        }

        if ($this->paddingVertical < 0) {
            throw new InvalidArgumentException('Panel vertical padding must be zero or greater.');
        }

        if ($this->cornerRadius < 0) {
            throw new InvalidArgumentException('Panel corner radius must be zero or greater.');
        }

        if ($this->titleSpacing < 0) {
            throw new InvalidArgumentException('Panel title spacing must be zero or greater.');
        }

        if ($this->titleSize <= 0) {
            throw new InvalidArgumentException('Panel title size must be greater than zero.');
        }

        if ($this->bodySize <= 0) {
            throw new InvalidArgumentException('Panel body size must be greater than zero.');
        }

        if ($this->borderWidth !== null && $this->borderWidth <= 0) {
            throw new InvalidArgumentException('Panel border width must be greater than zero.');
        }
    }
}
