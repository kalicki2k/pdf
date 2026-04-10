<?php

declare(strict_types=1);

namespace Kalle\Pdf\Page\Content;

/**
 * @internal Tracks marked-content identifiers allocated while rendering a page.
 */
final class PageMarkedContentIds
{
    private int $nextId = 0;

    public function next(): int
    {
        return $this->nextId++;
    }

    public function hasAllocatedIds(): bool
    {
        return $this->nextId > 0;
    }
}
