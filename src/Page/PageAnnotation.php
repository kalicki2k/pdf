<?php

declare(strict_types=1);

namespace Kalle\Pdf\Page;

interface PageAnnotation
{
    public function pdfObjectContents(PageAnnotationRenderContext $context): string;
}
