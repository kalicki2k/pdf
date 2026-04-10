<?php

declare(strict_types=1);

namespace Kalle\Pdf\Internal\Page\Annotation;

interface StructParentAwareAnnotation
{
    public function withStructParent(int $structParentId): self;
}
