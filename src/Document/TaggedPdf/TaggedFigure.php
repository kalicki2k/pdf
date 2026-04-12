<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document\TaggedPdf;

final readonly class TaggedFigure
{
    public function __construct(
        public int $pageIndex,
        public int $markedContentId,
        public ?string $altText = null,
    ) {
    }
}
