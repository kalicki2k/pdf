<?php

declare(strict_types=1);

namespace Kalle\Pdf\Page;

use InvalidArgumentException;

final readonly class PageAnnotationRenderContext
{
    /**
     * @param array<int, int> $pageObjectIdsByPageNumber
     * @param array<string, string> $namedDestinations
     */
    public function __construct(
        public int $pageObjectId,
        public bool $printable,
        public array $pageObjectIdsByPageNumber,
        public array $namedDestinations = [],
        public ?int $structParentId = null,
    ) {
    }

    public function targetPageObjectId(int $pageNumber): int
    {
        $pageObjectId = $this->pageObjectIdsByPageNumber[$pageNumber] ?? null;

        if ($pageObjectId === null) {
            throw new InvalidArgumentException(sprintf(
                'Link annotation target page %d does not exist.',
                $pageNumber,
            ));
        }

        return $pageObjectId;
    }

    public function namedDestination(string $name): string
    {
        $destination = $this->namedDestinations[$name] ?? null;

        if ($destination === null) {
            throw new InvalidArgumentException(sprintf(
                'Named destination "%s" does not exist.',
                $name,
            ));
        }

        return $destination;
    }
}
