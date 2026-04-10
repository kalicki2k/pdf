<?php

declare(strict_types=1);

namespace Kalle\Pdf\Text;

use Kalle\Pdf\Navigation\LinkTarget;
use Kalle\Pdf\Structure\StructElem;
use Kalle\Pdf\Structure\StructureTag;
use Kalle\Pdf\Style\Color;
use Kalle\Pdf\Style\Opacity;

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
