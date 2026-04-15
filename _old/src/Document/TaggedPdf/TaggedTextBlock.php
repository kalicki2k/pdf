<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document\TaggedPdf;

final readonly class TaggedTextBlock
{
    public function __construct(
        public string $tag,
        public int $pageIndex,
        public int $markedContentId,
        public ?string $key = null,
        public ?string $parentKey = null,
    ) {
    }
}
