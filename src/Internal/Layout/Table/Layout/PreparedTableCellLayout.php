<?php

declare(strict_types=1);

namespace Kalle\Pdf\Internal\Layout\Table\Layout;

final readonly class PreparedTableCellLayout
{
    public function __construct(
        public float $x,
        public float $bottomY,
        public float $width,
        public float $height,
        public float $textX,
        public float $textY,
        public float $textWidth,
        public float $bottomLimitY,
    ) {
    }
}
