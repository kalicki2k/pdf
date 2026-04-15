<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

final class TaggedAnnotationStructParentRegistry
{
    /** @var array<int, list<string>> */
    private array $parentTreeEntries = [];

    /** @var array<string|int, int> */
    private array $structParentIds = [];

    public function __construct(
        private int $nextStructParentId,
    ) {
    }

    public function register(string | int $ownerKey, string $entryKey): void
    {
        $this->structParentIds[$ownerKey] = $this->nextStructParentId;
        $this->parentTreeEntries[$this->nextStructParentId] = [$entryKey];
        $this->nextStructParentId++;
    }

    /**
     * @return array<int, list<string>>
     */
    public function parentTreeEntries(): array
    {
        return $this->parentTreeEntries;
    }

    /**
     * @return array<string|int, int>
     */
    public function structParentIds(): array
    {
        return $this->structParentIds;
    }

    /**
     * @return array<string, int>
     */
    public function stringStructParentIds(): array
    {
        /** @var array<string, int> $structParentIds */
        $structParentIds = $this->structParentIds;

        return $structParentIds;
    }

    /**
     * @return array<int, int>
     */
    public function intStructParentIds(): array
    {
        /** @var array<int, int> $structParentIds */
        $structParentIds = $this->structParentIds;

        return $structParentIds;
    }

    public function nextStructParentId(): int
    {
        return $this->nextStructParentId;
    }
}
