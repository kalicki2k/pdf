<?php

declare(strict_types=1);

namespace Kalle\Pdf\Page;

use Kalle\Pdf\Document\Attachment\AssociatedFileRelationship;

final readonly class FileAttachmentAnnotationOptions
{
    public function __construct(
        public ?string $description = null,
        public ?AssociatedFileRelationship $associatedFileRelationship = null,
        public string $icon = 'PushPin',
        public ?string $contents = null,
        public ?AnnotationMetadata $metadata = null,
    ) {
    }

    public function metadata(): AnnotationMetadata
    {
        return $this->metadata ?? new AnnotationMetadata(contents: $this->contents);
    }
}
