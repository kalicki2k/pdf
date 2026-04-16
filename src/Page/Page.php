<?php

declare(strict_types=1);

namespace Kalle\Pdf\Page;

use Kalle\Pdf\Resources\Resources;

final readonly class Page
{
    public static function make(
        int $parentObjectId,
        PageSize $mediaBox,
        Resources $resources,
        int $contentsObjectId,
    ): self {
        return new self(
            parentObjectId: $parentObjectId,
            mediaBox: $mediaBox,
            resources: $resources,
            contentsObjectId: $contentsObjectId,
        );
    }

    private function __construct(
        public int $parentObjectId,
        public PageSize $mediaBox,
        public Resources $resources,
        public int $contentsObjectId,
    ) {
    }
}