<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document\TaggedPdf;

final readonly class TaggedTableContentReference
{
    public function __construct(
        public int $pageIndex,
        public int $markedContentId,
    ) {
    }
}
