<?php

declare(strict_types=1);

namespace Kalle\Pdf\Model\Page;

use Kalle\Pdf\Feature\Text\StructureTag;
use Kalle\Pdf\Structure\StructElem;

readonly class ImageOptions
{
    public function __construct(
        public ?StructureTag $structureTag = null,
        public ?StructElem $parentStructElem = null,
        public ?string $altText = null,
    ) {
    }
}
