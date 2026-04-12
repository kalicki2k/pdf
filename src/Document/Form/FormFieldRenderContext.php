<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document\Form;

use InvalidArgumentException;

final readonly class FormFieldRenderContext
{
    /**
     * @param array<int, int> $pageObjectIdsByPageNumber
     * @param array<int, int> $structParentIdsByAnnotationObjectId
     */
    public function __construct(
        public array $pageObjectIdsByPageNumber,
        public array $structParentIdsByAnnotationObjectId = [],
    ) {
    }

    public function pageObjectId(int $pageNumber): int
    {
        $pageObjectId = $this->pageObjectIdsByPageNumber[$pageNumber] ?? null;

        if ($pageObjectId === null) {
            throw new InvalidArgumentException(sprintf(
                'Form field target page %d does not exist.',
                $pageNumber,
            ));
        }

        return $pageObjectId;
    }

    public function structParentId(int $annotationObjectId): ?int
    {
        return $this->structParentIdsByAnnotationObjectId[$annotationObjectId] ?? null;
    }
}
