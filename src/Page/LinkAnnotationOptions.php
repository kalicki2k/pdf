<?php

declare(strict_types=1);

namespace Kalle\Pdf\Page;

final readonly class LinkAnnotationOptions
{
    public function __construct(
        public ?string $contents = null,
        public ?string $accessibleLabel = null,
        public ?string $groupKey = null,
        public ?AnnotationMetadata $metadata = null,
    ) {
    }

    public function metadata(): AnnotationMetadata
    {
        return $this->metadata ?? new AnnotationMetadata(
            contents: $this->contents,
            accessibleLabel: $this->accessibleLabel,
            groupKey: $this->groupKey,
        );
    }
}
