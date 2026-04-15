<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document\TaggedPdf;

final readonly class TaggedStructureElement
{
    /**
     * @param list<string> $childKeys
     */
    public function __construct(
        public string $key,
        public string $tag,
        public array $childKeys,
    ) {
    }
}
