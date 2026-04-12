<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document\TaggedPdf;

use Kalle\Pdf\Document\TableHeaderScope;

final readonly class TaggedTableCell
{
    /**
     * @param list<TaggedTableContentReference> $contentReferences
     */
    public function __construct(
        public int $columnIndex,
        public bool $header,
        public ?TableHeaderScope $headerScope = null,
        public array $contentReferences = [],
    ) {
    }
}
