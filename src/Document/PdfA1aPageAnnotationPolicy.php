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
use Kalle\Pdf\Page\PageAnnotation;
use Kalle\Pdf\Page\PdfUaTaggedPageAnnotation;
use Kalle\Pdf\Page\PolygonAnnotation;
use Kalle\Pdf\Page\PolyLineAnnotation;
use Kalle\Pdf\Page\SquareAnnotation;
use Kalle\Pdf\Page\SquigglyAnnotation;
use Kalle\Pdf\Page\StampAnnotation;
use Kalle\Pdf\Page\StrikeOutAnnotation;
use Kalle\Pdf\Page\TextAnnotation;
use Kalle\Pdf\Page\UnderlineAnnotation;

final class PdfA1aPageAnnotationPolicy
{
    public function supports(PageAnnotation $annotation): bool
    {
        return $annotation instanceof TextAnnotation
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
            || $annotation instanceof PolygonAnnotation
            || $annotation instanceof FileAttachmentAnnotation;
    }

    public function altText(PageAnnotation $annotation): ?string
    {
        if (!$annotation instanceof PdfUaTaggedPageAnnotation || !$this->supports($annotation)) {
            return null;
        }

        return $annotation->taggedAnnotationAltText();
    }

    public function structureTag(PageAnnotation $annotation): ?string
    {
        if (!$annotation instanceof PdfUaTaggedPageAnnotation || !$this->supports($annotation)) {
            return null;
        }

        return $annotation->taggedAnnotationStructureTag();
    }
}
