<?php

declare(strict_types=1);

namespace Kalle\Pdf\Page;

use Kalle\Pdf\Writer\IndirectObject;

interface RelatedObjectsPageAnnotation
{
    public function relatedObjectCount(): int;

    /**
     * @return list<IndirectObject>
     */
    public function relatedObjects(PageAnnotationRenderContext $context): array;
}
