<?php

declare(strict_types=1);

namespace Kalle\Pdf\Page;

final readonly class TextAnnotationOptions
{
    public function __construct(
        public ?string $title = null,
        public string $icon = 'Note',
        public bool $open = false,
        public ?AnnotationMetadata $metadata = null,
    ) {
    }

    public function metadata(): AnnotationMetadata
    {
        return $this->metadata ?? new AnnotationMetadata(title: $this->title);
    }
}
