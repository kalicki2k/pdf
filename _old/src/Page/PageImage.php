<?php

declare(strict_types=1);

namespace Kalle\Pdf\Page;

use Kalle\Pdf\Image\ImageAccessibility;
use Kalle\Pdf\Image\ImagePlacement;

final readonly class PageImage
{
    public function __construct(
        public string $resourceAlias,
        public ImagePlacement $placement,
        public ?ImageAccessibility $accessibility = null,
        public ?int $markedContentId = null,
        public ?string $structureKey = null,
    ) {
    }
}
