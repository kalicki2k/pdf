<?php

declare(strict_types=1);

namespace Kalle\Pdf\Page;

final readonly class CaretAnnotationOptions
{
    public function __construct(
        public ?string $contents = null,
        public ?string $title = null,
        public string $symbol = 'None',
        public ?AnnotationMetadata $metadata = null,
    ) {
    }

    public function metadata(): AnnotationMetadata
    {
        return $this->metadata ?? new AnnotationMetadata(
            contents: $this->contents,
            title: $this->title,
        );
    }
}
