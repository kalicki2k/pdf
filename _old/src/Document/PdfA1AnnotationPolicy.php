<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use Kalle\Pdf\Page\CaretAnnotation;
use Kalle\Pdf\Page\CircleAnnotation;
use Kalle\Pdf\Page\FileAttachmentAnnotation;
use Kalle\Pdf\Page\FreeTextAnnotation;
use Kalle\Pdf\Page\HighlightAnnotation;
use Kalle\Pdf\Page\InkAnnotation;
use Kalle\Pdf\Page\LineAnnotation;
use Kalle\Pdf\Page\LinkAnnotation;
use Kalle\Pdf\Page\PageAnnotation;
use Kalle\Pdf\Page\PolygonAnnotation;
use Kalle\Pdf\Page\PolyLineAnnotation;
use Kalle\Pdf\Page\SquareAnnotation;
use Kalle\Pdf\Page\SquigglyAnnotation;
use Kalle\Pdf\Page\StampAnnotation;
use Kalle\Pdf\Page\StrikeOutAnnotation;
use Kalle\Pdf\Page\TextAnnotation;
use Kalle\Pdf\Page\UnderlineAnnotation;

final class PdfA1AnnotationPolicy
{
    public function supports(Document $document, PageAnnotation $annotation): bool
    {
        if (!$document->profile->isPdfA1()) {
            return true;
        }

        if ($annotation instanceof FileAttachmentAnnotation) {
            return false;
        }

        if ($document->profile->pdfaConformance() !== 'A') {
            return $annotation instanceof LinkAnnotation;
        }

        return $annotation instanceof LinkAnnotation
            || $annotation instanceof TextAnnotation
            || $annotation instanceof FreeTextAnnotation
            || $annotation instanceof HighlightAnnotation
            || $annotation instanceof UnderlineAnnotation
            || $annotation instanceof StrikeOutAnnotation
            || $annotation instanceof SquigglyAnnotation
            || $annotation instanceof StampAnnotation
            || $annotation instanceof SquareAnnotation
            || $annotation instanceof CircleAnnotation
            || $annotation instanceof CaretAnnotation
            || $annotation instanceof InkAnnotation
            || $annotation instanceof LineAnnotation
            || $annotation instanceof PolyLineAnnotation
            || $annotation instanceof PolygonAnnotation;
    }
}
