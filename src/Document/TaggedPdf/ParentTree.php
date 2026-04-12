<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document\TaggedPdf;

use function implode;
use function ksort;

final readonly class ParentTree
{
    /**
     * @param array<int, list<int>> $entries
     */
    public function __construct(
        private array $entries,
    ) {
    }

    public function objectContents(): string
    {
        $entries = $this->entries;
        ksort($entries);
        $nums = [];

        foreach ($entries as $structParentId => $objectIds) {
            $references = implode(' ', array_map(
                static fn (int $objectId): string => $objectId . ' 0 R',
                $objectIds,
            ));
            $nums[] = $structParentId . ' [' . $references . ']';
        }

        return '<< /Nums [' . implode(' ', $nums) . '] >>';
    }
}
