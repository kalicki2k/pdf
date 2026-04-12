<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document\TaggedPdf;

use function implode;

final readonly class StructTreeRoot
{
    /**
     * @param list<int> $kidObjectIds
     */
    public function __construct(
        private array $kidObjectIds,
        private ?int $parentTreeObjectId = null,
    ) {
    }

    public function objectContents(): string
    {
        $entries = [
            '/Type /StructTreeRoot',
            '/K [' . implode(' ', array_map(
                static fn (int $objectId): string => $objectId . ' 0 R',
                $this->kidObjectIds,
            )) . ']',
        ];

        if ($this->parentTreeObjectId !== null) {
            $entries[] = '/ParentTree ' . $this->parentTreeObjectId . ' 0 R';
        }

        return '<< ' . implode(' ', $entries) . ' >>';
    }
}
