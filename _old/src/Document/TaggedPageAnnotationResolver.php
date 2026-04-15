<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use Kalle\Pdf\Page\LinkAnnotation;
use Kalle\Pdf\Page\PageAnnotation;
use Kalle\Pdf\Page\PdfUaTaggedPageAnnotation;

final readonly class TaggedPageAnnotationResolver
{
    public function __construct(
        private PdfA1aPageAnnotationPolicy $pdfA1aPageAnnotationPolicy = new PdfA1aPageAnnotationPolicy(),
    ) {
    }

    public function supports(Document $document, object $annotation): bool
    {
        if (!$document->profile->requiresTaggedPageAnnotations() || $annotation instanceof LinkAnnotation) {
            return false;
        }

        if ($document->profile->isPdfA1() && $document->profile->pdfaConformance() === 'A') {
            return $annotation instanceof PageAnnotation
                && $this->pdfA1aPageAnnotationPolicy->supports($annotation);
        }

        return $annotation instanceof PdfUaTaggedPageAnnotation;
    }

    public function altText(Document $document, object $annotation): ?string
    {
        if (!$this->supports($document, $annotation)) {
            return null;
        }

        if ($document->profile->isPdfA1() && $annotation instanceof PageAnnotation) {
            return $this->pdfA1aPageAnnotationPolicy->altText($annotation);
        }

        return $annotation instanceof PdfUaTaggedPageAnnotation
            ? $annotation->taggedAnnotationAltText()
            : null;
    }

    public function structureTag(Document $document, object $annotation): ?string
    {
        if (!$this->supports($document, $annotation)) {
            return null;
        }

        if ($document->profile->isPdfA1() && $annotation instanceof PageAnnotation) {
            return $this->pdfA1aPageAnnotationPolicy->structureTag($annotation);
        }

        return $annotation instanceof PdfUaTaggedPageAnnotation
            ? $annotation->taggedAnnotationStructureTag()
            : null;
    }
}
