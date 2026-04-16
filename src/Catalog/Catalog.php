<?php

declare(strict_types=1);

namespace Kalle\Pdf\Catalog;

final readonly class Catalog
{
    public static function make(
        int $pagesObjectId,
        ?int $pageLabelsObjectId = null,
        ?int $namesObjectId = null,
        ?int $outlinesObjectId = null,
    ): self {
        return new self(
            pagesObjectId: $pagesObjectId,
            pageLabelsObjectId: $pageLabelsObjectId,
            namesObjectId: $namesObjectId,
            outlinesObjectId: $outlinesObjectId,
        );
    }

    private function __construct(
        public int $pagesObjectId,
        public ?int $pageLabelsObjectId = null,
        public ?int $namesObjectId = null,
        public ?int $outlinesObjectId = null,
    ) {
    }
}