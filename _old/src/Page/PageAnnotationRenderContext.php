<?php

declare(strict_types=1);

namespace Kalle\Pdf\Page;

use InvalidArgumentException;

final readonly class PageAnnotationRenderContext
{
    /**
     * @param array<int, int> $pageObjectIdsByPageNumber
     * @param array<string, string> $namedDestinations
     * @param list<int> $relatedObjectIds
     * @param array<string, int> $attachmentObjectIdsByFilename
     */
    public function __construct(
        public int $pageObjectId,
        public bool $printable,
        public array $pageObjectIdsByPageNumber,
        public array $namedDestinations = [],
        public ?int $structParentId = null,
        public ?int $appearanceObjectId = null,
        public ?int $annotationObjectId = null,
        public array $relatedObjectIds = [],
        public array $attachmentObjectIdsByFilename = [],
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

    public function relatedObjectId(int $index): int
    {
        $objectId = $this->relatedObjectIds[$index] ?? null;

        if ($objectId === null) {
            throw new InvalidArgumentException(sprintf(
                'Related annotation object %d is not available.',
                $index + 1,
            ));
        }

        return $objectId;
    }

    public function attachmentObjectId(string $filename): int
    {
        $objectId = $this->attachmentObjectIdsByFilename[$filename] ?? null;

        if ($objectId === null) {
            throw new InvalidArgumentException(sprintf(
                'Attachment "%s" is not available for file attachment annotations.',
                $filename,
            ));
        }

        return $objectId;
    }
}
