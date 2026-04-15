<?php

declare(strict_types=1);

namespace Kalle\Pdf\Page;

use function implode;

final readonly class PopupAnnotation
{
    use FormatsPdfAnnotationValues;

    public function __construct(
        public PopupAnnotationDefinition $definition,
    ) {
    }

    public function pdfObjectContents(PageAnnotationRenderContext $context): string
    {
        $parentObjectId = $context->annotationObjectId ?? $context->relatedObjectId(0);

        return '<< ' . implode(' ', [
            '/Type /Annot',
            '/Subtype /Popup',
            '/Rect ' . $this->rect(
                $this->definition->x,
                $this->definition->y,
                $this->definition->width,
                $this->definition->height,
            ),
            '/P ' . $context->pageObjectId . ' 0 R',
            '/Parent ' . $parentObjectId . ' 0 R',
            '/Open ' . ($this->definition->open ? 'true' : 'false'),
        ]) . ' >>';
    }
}
