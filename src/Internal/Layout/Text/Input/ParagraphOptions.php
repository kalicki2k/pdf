<?php

declare(strict_types=1);

namespace Kalle\Pdf\Internal\Layout\Text\Input;

use Kalle\Pdf\Internal\Layout\Value\HorizontalAlign;
use Kalle\Pdf\Internal\Layout\Value\TextOverflow;
use Kalle\Pdf\Style\Color;
use Kalle\Pdf\Style\Opacity;
use Kalle\Pdf\TaggedPdf\StructElem;
use Kalle\Pdf\TaggedPdf\StructureTag;

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
