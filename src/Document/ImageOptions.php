<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use Kalle\Pdf\Document\Text\StructureTag;
use Kalle\Pdf\Structure\StructElem;

final readonly class ImageOptions
{
    public function __construct(
        public ?StructureTag $structureTag = null,
        public ?StructElem $parentStructElem = null,
        public ?string $altText = null,
    ) {
    }
}
