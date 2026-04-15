<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document\TaggedPdf;

use function implode;

final readonly class StructTreeRoot
{
    /**
     * @param list<int> $kidObjectIds
     * @param array<string, string> $roleMap
     */
    public function __construct(
        private array $kidObjectIds,
        private ?int $parentTreeObjectId = null,
        private array $roleMap = [],
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

        if ($this->roleMap !== []) {
            $roleMapEntries = [];

            foreach ($this->roleMap as $customType => $standardType) {
                $roleMapEntries[] = '/' . $customType . ' /' . $standardType;
            }

            $entries[] = '/RoleMap << ' . implode(' ', $roleMapEntries) . ' >>';
        }

        return '<< ' . implode(' ', $entries) . ' >>';
    }
}
