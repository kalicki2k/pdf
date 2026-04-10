<?php

declare(strict_types=1);

namespace Kalle\Pdf\Style;

use InvalidArgumentException;
use Kalle\Pdf\Style\Color;
use Kalle\Pdf\Style\Opacity;

final readonly class CalloutStyle
{
    public function __construct(
        public ?PanelStyle $panelStyle = null,
        public float $pointerBaseWidth = 16.0,
        public ?float $pointerStrokeWidth = null,
        public ?Color $pointerStrokeColor = null,
        public ?Color $pointerFillColor = null,
        public ?Opacity $pointerOpacity = null,
    ) {
        if ($this->pointerBaseWidth <= 0) {
            throw new InvalidArgumentException('Callout pointer base width must be greater than zero.');
        }

        if ($this->pointerStrokeWidth !== null && $this->pointerStrokeWidth <= 0) {
            throw new InvalidArgumentException('Callout pointer stroke width must be greater than zero.');
        }
    }
}
