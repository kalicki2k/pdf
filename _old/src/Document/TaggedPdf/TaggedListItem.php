<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document\TaggedPdf;

final readonly class TaggedListItem
{
    public function __construct(
        public TaggedListContentReference $labelReference,
        public TaggedListContentReference $bodyReference,
    ) {
    }
}
