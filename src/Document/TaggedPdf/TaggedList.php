<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document\TaggedPdf;

final readonly class TaggedList
{
    /**
     * @param list<TaggedListItem> $items
     */
    public function __construct(
        public int $listId,
        public array $items,
        public ?string $key = null,
    ) {
    }
}
