<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document\Annotation;

interface StructParentAwareAnnotation
{
    public function withStructParent(int $structParentId): self;
}
