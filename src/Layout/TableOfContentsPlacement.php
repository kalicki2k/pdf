<?php

declare(strict_types=1);

namespace Kalle\Pdf\Layout;

use InvalidArgumentException;

final readonly class TableOfContentsPlacement
{
    private function __construct(
        private TableOfContentsPosition $position,
        private ?int $pageNumber = null,
    ) {
    }

    public static function start(): self
    {
        return new self(TableOfContentsPosition::START);
    }

    public static function end(): self
    {
        return new self(TableOfContentsPosition::END);
    }

    public static function afterPage(int $pageNumber): self
    {
        if ($pageNumber < 1) {
            throw new InvalidArgumentException('Table of contents insertion page must be greater than zero.');
        }

        return new self(TableOfContentsPosition::AFTER_PAGE, $pageNumber);
    }

    public function insertionIndex(int $pageCount): int
    {
        return match ($this->position) {
            TableOfContentsPosition::START => 0,
            TableOfContentsPosition::END => $pageCount,
            TableOfContentsPosition::AFTER_PAGE => $this->resolveAfterPageInsertionIndex($pageCount),
        };
    }

    private function resolveAfterPageInsertionIndex(int $pageCount): int
    {
        if ($this->pageNumber === null || $this->pageNumber > $pageCount) {
            throw new InvalidArgumentException(sprintf(
                'Table of contents insertion page %d is out of bounds for a document with %d pages.',
                $this->pageNumber ?? 0,
                $pageCount,
            ));
        }

        return $this->pageNumber;
    }
}
