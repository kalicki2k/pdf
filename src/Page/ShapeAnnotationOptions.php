<?php

declare(strict_types=1);

namespace Kalle\Pdf\Page;

use Kalle\Pdf\Color\Color;

final readonly class ShapeAnnotationOptions
{
    public function __construct(
        public ?Color $borderColor = null,
        public ?Color $fillColor = null,
        public ?AnnotationBorderStyle $borderStyle = null,
        public ?string $contents = null,
        public ?string $title = null,
        public ?string $subject = null,
        public ?AnnotationMetadata $metadata = null,
    ) {
    }

    public function metadata(): AnnotationMetadata
    {
        return $this->metadata ?? new AnnotationMetadata(
            contents: $this->contents,
            title: $this->title,
            subject: $this->subject,
        );
    }
}
