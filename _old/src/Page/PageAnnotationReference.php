<?php

declare(strict_types=1);

namespace Kalle\Pdf\Page;

use InvalidArgumentException;

final readonly class PageAnnotationReference
{
    public function __construct(
        public int $pageNumber,
        public int $annotationIndex,
    ) {
        if ($this->pageNumber <= 0) {
            throw new InvalidArgumentException('Page annotation reference page number must be greater than zero.');
        }

        if ($this->annotationIndex < 0) {
            throw new InvalidArgumentException('Page annotation reference annotation index must be zero or greater.');
        }
    }
}
