<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use Kalle\Pdf\Graphics\Color;
use Kalle\Pdf\Graphics\Opacity;
use Kalle\Pdf\Layout\HorizontalAlign;
use Kalle\Pdf\Layout\TextOverflow;
use Kalle\Pdf\Layout\VerticalAlign;

final readonly class TextBoxOptions
{
    public function __construct(
        public ?StructureTag $structureTag = null,
        public ?float $lineHeight = null,
        public ?Color $color = null,
        public ?Opacity $opacity = null,
        public HorizontalAlign $align = HorizontalAlign::LEFT,
        public VerticalAlign $verticalAlign = VerticalAlign::TOP,
        public ?int $maxLines = null,
        public TextOverflow $overflow = TextOverflow::CLIP,
        public float $paddingTop = 0.0,
        public float $paddingRight = 0.0,
        public float $paddingBottom = 0.0,
        public float $paddingLeft = 0.0,
    ) {
    }
}
