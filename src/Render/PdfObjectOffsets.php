<?php

declare(strict_types=1);

namespace Kalle\Pdf\Render;

final readonly class PdfObjectOffsets
{
    /** @var array<int, int> */
    private array $entriesByObjectId;

    /**
     * @param array<int, int> $entriesByObjectId
     */
    public function __construct(array $entriesByObjectId)
    {
        ksort($entriesByObjectId);
        $this->entriesByObjectId = $entriesByObjectId;
    }

    /**
     * @return array<int, int>
     */
    public function entries(): array
    {
        return $this->entriesByObjectId;
    }

    public function size(): int
    {
        return $this->highestObjectId() + 1;
    }

    public function highestObjectId(): int
    {
        if ($this->entriesByObjectId === []) {
            return 0;
        }

        return max(array_keys($this->entriesByObjectId));
    }
}
