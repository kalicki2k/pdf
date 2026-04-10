<?php

declare(strict_types=1);

namespace Kalle\Pdf\Text;

use Kalle\Pdf\Internal\Page\Link\LinkTarget;
use Kalle\Pdf\Internal\Style\Color;
use Kalle\Pdf\Internal\Style\Opacity;
use Kalle\Pdf\Internal\TaggedPdf\StructElem;
use Kalle\Pdf\Internal\TaggedPdf\StructureTag;

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
