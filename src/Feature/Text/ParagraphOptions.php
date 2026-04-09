<?php

declare(strict_types=1);

namespace Kalle\Pdf\Feature\Text;

use Kalle\Pdf\Graphics\Color;
use Kalle\Pdf\Graphics\Opacity;
use Kalle\Pdf\Layout\HorizontalAlign;
use Kalle\Pdf\Layout\TextOverflow;
use Kalle\Pdf\Structure\StructElem;

final readonly class ParagraphOptions
{
    public function __construct(
        public ?StructureTag $structureTag = null,
        public ?StructElem $parentStructElem = null,
        public ?float $lineHeight = null,
        public ?float $spacingAfter = null,
        public ?float $bottomMargin = null,
        public ?Color $color = null,
        public ?Opacity $opacity = null,
        public HorizontalAlign $align = HorizontalAlign::LEFT,
        public ?int $maxLines = null,
        public TextOverflow $overflow = TextOverflow::CLIP,
    ) {
    }
}
