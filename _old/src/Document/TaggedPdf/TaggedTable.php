<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document\TaggedPdf;

final readonly class TaggedTable
{
    /**
     * @param list<TaggedTableContentReference> $captionReferences
     * @param list<TaggedTableRow> $headerRows
     * @param list<TaggedTableRow> $bodyRows
     * @param list<TaggedTableRow> $footerRows
     */
    public function __construct(
        public int $tableId,
        public array $captionReferences = [],
        public array $headerRows = [],
        public array $bodyRows = [],
        public array $footerRows = [],
        public ?string $key = null,
    ) {
    }

    public function hasCaption(): bool
    {
        return $this->captionReferences !== [];
    }
}
