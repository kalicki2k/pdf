<?php

declare(strict_types=1);

namespace Kalle\Pdf\Internal\Layout\Text\Input;

use Kalle\Pdf\Style\Color;
use Kalle\Pdf\Style\Opacity;
use Kalle\Pdf\TaggedPdf\StructureTag;

final readonly class ListOptions
{
    public function __construct(
        public ?StructureTag $structureTag = null,
        public ?float $lineHeight = null,
        public ?float $spacingAfter = null,
        public ?float $itemSpacing = null,
        public ?Color $color = null,
        public ?Opacity $opacity = null,
        public ?Color $markerColor = null,
        public ?float $markerIndent = null,
    ) {
    }
}
