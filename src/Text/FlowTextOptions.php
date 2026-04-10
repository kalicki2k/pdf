<?php

declare(strict_types=1);

namespace Kalle\Pdf\Text;

use Kalle\Pdf\Internal\Layout\Value\HorizontalAlign;
use Kalle\Pdf\Internal\Layout\Value\TextOverflow;
use Kalle\Pdf\Structure\StructElem;
use Kalle\Pdf\Structure\StructureTag;
use Kalle\Pdf\Style\Color;
use Kalle\Pdf\Style\Opacity;

final readonly class FlowTextOptions
{
    public function __construct(
        public ?StructureTag $structureTag = null,
        public ?StructElem $parentStructElem = null,
        public ?float $lineHeight = null,
        public ?float $bottomMargin = null,
        public ?Color $color = null,
        public ?Opacity $opacity = null,
        public HorizontalAlign $align = HorizontalAlign::LEFT,
        public ?int $maxLines = null,
        public TextOverflow $overflow = TextOverflow::CLIP,
    ) {
    }
}
