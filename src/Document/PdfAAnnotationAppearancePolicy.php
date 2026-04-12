<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use Kalle\Pdf\Page\AppearanceStreamAnnotation;
use Kalle\Pdf\Page\LinkAnnotation;
use Kalle\Pdf\Page\PageAnnotation;
use Kalle\Pdf\Page\PdfUaTaggedPageAnnotation;

final class PdfAAnnotationAppearancePolicy
{
    public function __construct(
        private readonly PdfA1aPageAnnotationPolicy $pdfA1aPageAnnotationPolicy = new PdfA1aPageAnnotationPolicy(),
    ) {
    }

    public function requiresAppearanceStream(Document $document, object $annotation): bool
    {
        if (
            !$document->profile->requiresAnnotationAppearanceStreams()
            || !$annotation instanceof AppearanceStreamAnnotation
        ) {
            return false;
        }

        if (!$document->profile->isPdfA1()) {
            return true;
        }

        if ($annotation instanceof LinkAnnotation) {
            return true;
        }

        if (!$document->profile->requiresTaggedPageAnnotations()) {
            return false;
        }

        if (
            $document->profile->pdfaConformance() === 'A'
            && $annotation instanceof PageAnnotation
            && $this->pdfA1aPageAnnotationPolicy->supports($annotation)
        ) {
            return true;
        }

        return $annotation instanceof PdfUaTaggedPageAnnotation;
    }
}
