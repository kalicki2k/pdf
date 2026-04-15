<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document\Form;

use Kalle\Pdf\Document\DocumentBuildError;
use Kalle\Pdf\Document\DocumentValidationException;
use Kalle\Pdf\Page\PageFont;

final readonly class FormFieldRenderContext
{
    /**
     * @param array<int, int> $pageObjectIdsByPageNumber
     * @param array<int, int> $structParentIdsByAnnotationObjectId
     * @param array<int, array<string, int>> $optionalContentGroupObjectIdsByPageNumber
     */
    public function __construct(
        public array $pageObjectIdsByPageNumber,
        public array $structParentIdsByAnnotationObjectId = [],
        public ?PageFont $defaultTextFont = null,
        public ?string $defaultTextFontAlias = null,
        public ?int $defaultTextFontObjectId = null,
        public array $optionalContentGroupObjectIdsByPageNumber = [],
    ) {
    }

    public function pageObjectId(int $pageNumber): int
    {
        $pageObjectId = $this->pageObjectIdsByPageNumber[$pageNumber] ?? null;

        if ($pageObjectId === null) {
            throw new DocumentValidationException(
                DocumentBuildError::FORM_FIELD_PAGE_INVALID,
                sprintf(
                    'Form field target page %d does not exist.',
                    $pageNumber,
                ),
            );
        }

        return $pageObjectId;
    }

    public function structParentId(int $annotationObjectId): ?int
    {
        return $this->structParentIdsByAnnotationObjectId[$annotationObjectId] ?? null;
    }

    public function optionalContentGroupObjectId(int $pageNumber, string $alias): int
    {
        $objectId = $this->optionalContentGroupObjectIdsByPageNumber[$pageNumber][$alias] ?? null;

        if ($objectId === null) {
            throw new DocumentValidationException(
                DocumentBuildError::BUILD_STATE_INVALID,
                sprintf(
                    'Optional content group alias "%s" is not configured on page %d.',
                    $alias,
                    $pageNumber,
                ),
            );
        }

        return $objectId;
    }

    public function requiresDefaultTextFont(): PageFont
    {
        if ($this->defaultTextFont === null) {
            throw new DocumentValidationException(
                DocumentBuildError::BUILD_STATE_INVALID,
                'Embedded default form font is not configured.',
            );
        }

        return $this->defaultTextFont;
    }

    public function requiresDefaultTextFontAlias(): string
    {
        if ($this->defaultTextFontAlias === null) {
            throw new DocumentValidationException(
                DocumentBuildError::BUILD_STATE_INVALID,
                'Embedded default form font alias is not configured.',
            );
        }

        return $this->defaultTextFontAlias;
    }

    public function requiresDefaultTextFontObjectId(): int
    {
        if ($this->defaultTextFontObjectId === null) {
            throw new DocumentValidationException(
                DocumentBuildError::BUILD_STATE_INVALID,
                'Embedded default form font object ID is not configured.',
            );
        }

        return $this->defaultTextFontObjectId;
    }
}
