<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use Kalle\Pdf\Page\PageAnnotation;
use Kalle\Pdf\Page\RelatedObjectsPageAnnotation;
use Kalle\Pdf\Page\SupportsPopupAnnotation;

final class PdfA1PopupPolicy
{
    public function usesPopup(PageAnnotation $annotation): bool
    {
        return $annotation instanceof SupportsPopupAnnotation
            && $annotation instanceof RelatedObjectsPageAnnotation
            && $annotation->relatedObjectCount() > 0;
    }
}
