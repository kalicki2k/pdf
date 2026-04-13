<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use function sprintf;

use Kalle\Pdf\Page\FileAttachmentAnnotation;
use Kalle\Pdf\Page\FreeTextAnnotation;
use Kalle\Pdf\Page\HighlightAnnotation;
use Kalle\Pdf\Page\LinkAnnotation;
use Kalle\Pdf\Page\PageAnnotation;
use Kalle\Pdf\Page\RelatedObjectsPageAnnotation;
use Kalle\Pdf\Page\SupportsPopupAnnotation;
use Kalle\Pdf\Page\TextAnnotation;

final class PdfA4AnnotationScopePolicy
{
    public function supportsPageAnnotation(Document $document, PageAnnotation $annotation): bool
    {
        if (!$document->profile->isPdfA4()) {
            return true;
        }

        if ($annotation instanceof FileAttachmentAnnotation) {
            return false;
        }

        return $annotation instanceof LinkAnnotation
            || $annotation instanceof TextAnnotation
            || $annotation instanceof HighlightAnnotation
            || $annotation instanceof FreeTextAnnotation;
    }

    public function assertPageAnnotationAllowed(Document $document, PageAnnotation $annotation, int $pageIndex, int $annotationIndex): void
    {
        if (!$document->profile->isPdfA4()) {
            return;
        }

        $scopeLabel = $this->scopeLabel($document->profile);

        if ($annotation instanceof FileAttachmentAnnotation) {
            throw new DocumentValidationException(DocumentBuildError::PDFA_EMBEDDED_ATTACHMENTS_NOT_ALLOWED, sprintf(
                'Profile %s does not allow page-level file attachment annotations in the %s. Use document-level associated files instead.',
                $document->profile->name(),
                $scopeLabel,
            ));
        }

        if ($this->usesPopup($annotation)) {
            throw new DocumentValidationException(DocumentBuildError::PDFA_OBJECT_GRAPH_INVALID, sprintf(
                'Profile %s does not allow popup related objects in the %s for page annotation %d on page %d.',
                $document->profile->name(),
                $scopeLabel,
                $annotationIndex + 1,
                $pageIndex + 1,
            ));
        }

        if ($this->supportsPageAnnotation($document, $annotation)) {
            return;
        }

        throw new DocumentValidationException(DocumentBuildError::PDFA_OBJECT_GRAPH_INVALID, sprintf(
            'Profile %s only allows Link, Text, Highlight and FreeText annotations in the %s on page %d.',
            $document->profile->name(),
            $scopeLabel,
            $pageIndex + 1,
        ));
    }

    private function usesPopup(PageAnnotation $annotation): bool
    {
        return $annotation instanceof SupportsPopupAnnotation
            && $annotation instanceof RelatedObjectsPageAnnotation
            && $annotation->relatedObjectCount() > 0;
    }

    private function scopeLabel(Profile $profile): string
    {
        return $profile->pdfaConformance() === 'E'
            ? 'current constrained PDF/A-4e scope'
            : 'current PDF/A-4 scope';
    }
}
