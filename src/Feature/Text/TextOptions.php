<?php

declare(strict_types=1);

namespace Kalle\Pdf\Feature\Text;

use Kalle\Pdf\Graphics\Color;
use Kalle\Pdf\Graphics\Opacity;
use Kalle\Pdf\Navigation\LinkTarget;
use Kalle\Pdf\Structure\StructElem;
use Kalle\Pdf\Structure\StructureTag;

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
