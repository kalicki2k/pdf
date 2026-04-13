<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use Kalle\Pdf\Page\FileAttachmentAnnotation;
use Kalle\Pdf\Page\FreeTextAnnotation;
use Kalle\Pdf\Page\HighlightAnnotation;
use Kalle\Pdf\Page\LinkAnnotation;
use Kalle\Pdf\Page\PageAnnotation;
use Kalle\Pdf\Page\RelatedObjectsPageAnnotation;
use Kalle\Pdf\Page\SupportsPopupAnnotation;
use Kalle\Pdf\Page\TextAnnotation;

use function sprintf;

final class PdfA23ScopePolicy
{
    public function supportsPageAnnotation(Document $document, PageAnnotation $annotation): bool
    {
        if (!$this->appliesTo($document)) {
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
        if (!$this->appliesTo($document)) {
            return;
        }

        if ($document->profile->pdfaConformance() === 'A' && !$annotation instanceof LinkAnnotation) {
            throw new DocumentValidationException(DocumentBuildError::TAGGED_PDF_REQUIRED, sprintf(
                'Profile %s only allows tagged link annotations in the current PDF/A-%dA scope; other page annotations remain blocked on page %d.',
                $document->profile->name(),
                $document->profile->pdfaPart(),
                $pageIndex + 1,
            ));
        }

        if ($annotation instanceof FileAttachmentAnnotation) {
            throw new DocumentValidationException(DocumentBuildError::PDFA_EMBEDDED_ATTACHMENTS_NOT_ALLOWED, sprintf(
                'Profile %s does not allow page-level file attachment annotations in the current PDF/A-2/3 scope. Use document-level associated files instead.',
                $document->profile->name(),
            ));
        }

        if ($this->usesPopup($annotation)) {
            throw new DocumentValidationException(DocumentBuildError::PDFA_OBJECT_GRAPH_INVALID, sprintf(
                'Profile %s does not allow popup related objects in the current PDF/A-2/3 scope for page annotation %d on page %d.',
                $document->profile->name(),
                $annotationIndex + 1,
                $pageIndex + 1,
            ));
        }

        if ($this->supportsPageAnnotation($document, $annotation)) {
            return;
        }

        throw new DocumentValidationException(DocumentBuildError::PDFA_OBJECT_GRAPH_INVALID, sprintf(
            'Profile %s only allows Link, Text, Highlight and FreeText annotations in the current PDF/A-2/3 scope on page %d.',
            $document->profile->name(),
            $pageIndex + 1,
        ));
    }

    public function assertAcroFormAllowed(Document $document): void
    {
        if (!$this->appliesTo($document) || $document->acroForm === null) {
            return;
        }

        throw new DocumentValidationException(DocumentBuildError::PDFA_ACROFORM_NOT_ALLOWED, sprintf(
            'Profile %s does not allow AcroForm fields in the current PDF/A-2/3 scope.',
            $document->profile->name(),
        ));
    }

    public function assertDocumentAttachmentAllowed(Document $document, int $attachmentIndex, bool $hasAssociatedFileRelationship): void
    {
        if (!$this->appliesTo($document)) {
            return;
        }

        if ($document->profile->isPdfA2()) {
            throw new DocumentValidationException(DocumentBuildError::PDFA_EMBEDDED_ATTACHMENTS_NOT_ALLOWED, sprintf(
                'Profile %s does not allow embedded file attachments in the current PDF/A-2 scope.',
                $document->profile->name(),
            ));
        }

        if ($document->profile->isPdfA3() && !$hasAssociatedFileRelationship) {
            throw new DocumentValidationException(DocumentBuildError::PDFA_ASSOCIATED_FILES_NOT_ALLOWED, sprintf(
                'Profile %s only allows document-level associated files in the current PDF/A-3 scope for attachment %d.',
                $document->profile->name(),
                $attachmentIndex + 1,
            ));
        }
    }

    private function appliesTo(Document $document): bool
    {
        return $document->profile->isPdfA2()
            || $document->profile->isPdfA3();
    }

    private function usesPopup(PageAnnotation $annotation): bool
    {
        return $annotation instanceof SupportsPopupAnnotation
            && $annotation instanceof RelatedObjectsPageAnnotation
            && $annotation->relatedObjectCount() > 0;
    }
}
