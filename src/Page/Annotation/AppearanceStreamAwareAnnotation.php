<?php

declare(strict_types=1);

namespace Kalle\Pdf\Page\Annotation;

interface AppearanceStreamAwareAnnotation
{
    public function withAppearance(TextAnnotationAppearanceStream $appearance): self;
}
