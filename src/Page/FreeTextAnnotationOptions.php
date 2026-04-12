<?php

declare(strict_types=1);

namespace Kalle\Pdf\Page;

use Kalle\Pdf\Color\Color;

final readonly class FreeTextAnnotationOptions
{
    public function __construct(
        public ?Color $textColor = null,
        public ?Color $borderColor = null,
        public ?Color $fillColor = null,
        public ?AnnotationMetadata $metadata = null,
    ) {
    }

    public function metadata(): AnnotationMetadata
    {
        return $this->metadata ?? new AnnotationMetadata();
    }
}
