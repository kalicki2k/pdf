<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document\Text;

use Kalle\Pdf\Document\LinkTarget;
use Kalle\Pdf\Graphics\Color;
use Kalle\Pdf\Graphics\Opacity;

final readonly class TextOptions
{
    public function __construct(
        public ?StructureTag $structureTag = null,
        public ?Color $color = null,
        public ?Opacity $opacity = null,
        public bool $underline = false,
        public bool $strikethrough = false,
        public ?LinkTarget $link = null,
    ) {
    }
}
