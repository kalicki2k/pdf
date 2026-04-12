<?php

declare(strict_types=1);

namespace Kalle\Pdf\Page;

use InvalidArgumentException;

final readonly class PageAnnotationRenderContext
{
    /**
     * @param array<int, int> $pageObjectIdsByPageNumber
     */
    public function __construct(
        public int $pageObjectId,
        public bool $printable,
        public array $pageObjectIdsByPageNumber,
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
}
