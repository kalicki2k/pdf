<?php

declare(strict_types=1);

namespace Kalle\Pdf\Internal\Layout\Text\Input;

use Kalle\Pdf\Internal\Page\Link\LinkTarget;
use Kalle\Pdf\Style\Color;
use Kalle\Pdf\Style\Opacity;
use Kalle\Pdf\TaggedPdf\StructElem;
use Kalle\Pdf\TaggedPdf\StructureTag;

final readonly class TextOptions
{
    public function __construct(
        public ?StructureTag $structureTag = null,
        public ?StructElem $parentStructElem = null,
        public ?Color $color = null,
        public ?Opacity $opacity = null,
        public bool $underline = false,
        public bool $strikethrough = false,
        public ?LinkTarget $link = null,
    ) {
    }
}
