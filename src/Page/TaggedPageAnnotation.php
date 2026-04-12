<?php

declare(strict_types=1);

namespace Kalle\Pdf\Page;

interface TaggedPageAnnotation
{
    public function taggedAnnotationAltText(): ?string;

    public function taggedAnnotationStructureTag(): string;
}
