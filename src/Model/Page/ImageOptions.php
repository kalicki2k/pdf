<?php

declare(strict_types=1);

namespace Kalle\Pdf\Model\Page;

use Kalle\Pdf\Structure\StructElem;
use Kalle\Pdf\Structure\StructureTag;

readonly class ImageOptions
{
    public function __construct(
        public ?StructureTag $structureTag = null,
        public ?StructElem $parentStructElem = null,
        public ?string $altText = null,
    ) {
    }
}
