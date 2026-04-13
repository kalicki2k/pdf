<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use Kalle\Pdf\Document\Form\FormField;
use Kalle\Pdf\Document\Form\PushButtonField;
use Kalle\Pdf\Page\LinkAnnotation;
use Kalle\Pdf\Page\PageAnnotation;

use function sprintf;

final class PdfA1ActionPolicy
{
    public function assertOutlineAllowed(Document $document, Outline $outline, int $outlineIndex): void
    {
        if (!$document->profile->isPdfA1()) {
            return;
        }

        if ($outline->destination->isRemote()) {
            throw new DocumentValidationException(DocumentBuildError::PDFA_ACTION_NOT_ALLOWED, sprintf(
                'Profile %s does not allow remote outline actions such as GoToR in outline %d.',
                $document->profile->name(),
                $outlineIndex + 1,
            ));
        }
    }

    public function assertAnnotationAllowed(Document $document, PageAnnotation $annotation, int $pageIndex, int $annotationIndex): void
    {
        if (!$document->profile->isPdfA1()) {
            return;
        }

        if ($annotation instanceof LinkAnnotation && $annotation->target->isExternalUrl()) {
            throw new DocumentValidationException(DocumentBuildError::PDFA_ACTION_NOT_ALLOWED, sprintf(
                'Profile %s does not allow URI annotation actions in link annotation %d on page %d. Use an internal /Dest target instead.',
                $document->profile->name(),
                $annotationIndex + 1,
                $pageIndex + 1,
            ));
        }
    }

    public function assertFormFieldAllowed(Document $document, FormField $field): void
    {
        if (!$document->profile->isPdfA1()) {
            return;
        }

        if ($field instanceof PushButtonField && $field->url !== null) {
            throw new DocumentValidationException(DocumentBuildError::PDFA_ACTION_NOT_ALLOWED, sprintf(
                'Profile %s does not allow URI actions in push button field "%s".',
                $document->profile->name(),
                $field->name,
            ));
        }
    }
}
