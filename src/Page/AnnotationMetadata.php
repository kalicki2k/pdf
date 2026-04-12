<?php

declare(strict_types=1);

namespace Kalle\Pdf\Page;

final readonly class AnnotationMetadata
{
    public function __construct(
        public ?string $contents = null,
        public ?string $title = null,
        public ?string $accessibleLabel = null,
        public ?string $groupKey = null,
        public ?string $subject = null,
    ) {
    }
}
