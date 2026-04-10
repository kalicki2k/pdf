<?php

declare(strict_types=1);

namespace Kalle\Pdf\Internal\Page\Content;

use Kalle\Pdf\Internal\TaggedPdf\StructElem;
use Kalle\Pdf\Internal\TaggedPdf\StructureTag;

readonly class ImageOptions
{
    public function __construct(
        public ?StructureTag $structureTag = null,
        public ?StructElem $parentStructElem = null,
        public ?string $altText = null,
    ) {
    }
}
