<?php

declare(strict_types=1);

namespace Kalle\Pdf\Page\Annotation;

use Kalle\Pdf\Layout\Geometry\Rect;
use Kalle\Pdf\Object\IndirectObject;
use Kalle\Pdf\Page\Link\LinkTarget;
use Kalle\Pdf\Page\Page;
use Kalle\Pdf\TaggedPdf\StructElem;

final readonly class PageInteractiveAnnotationFactory
{
    public function __construct(
        private Page $page,
        private PageAnnotationFactoryContext $context,
        private PageAnnotationFinalizer $finalizer,
    ) {
    }

    public function createLinkAnnotation(
        Rect $box,
        LinkTarget $target,
        ?StructElem $linkStructElem = null,
        ?string $alternativeDescription = null,
    ): LinkAnnotation {
        $this->finalizer->assertAllowsLinkAnnotation($linkStructElem);
        $this->finalizer->assertRectHasPositiveDimensions($box, 'Link');

        $annotation = new LinkAnnotation(
            $this->context->nextObjectId(),
            $this->page,
            $box->x,
            $box->y,
            $box->width,
            $box->height,
            $target,
        );

        if ($linkStructElem !== null) {
            $structParentId = $this->page->getDocument()->getNextStructParentId();
            $annotation->withStructParent($structParentId);
            $linkStructElem->addObjectReference($annotation, $this->page);
            $this->page->getDocument()->registerObjectStructElem($structParentId, $linkStructElem);
        }

        if (
            $alternativeDescription !== null
            && $alternativeDescription !== ''
            && $this->page->getDocument()->getProfile()->requiresLinkAnnotationAlternativeDescriptions()
        ) {
            $annotation->withContents($alternativeDescription);

            if ($linkStructElem !== null) {
                $linkStructElem->setAltText($alternativeDescription);
            }
        }

        return $annotation;
    }

    public function createPopupAnnotation(
        IndirectObject $parent,
        Rect $box,
        bool $open,
    ): PopupAnnotation {
        $this->finalizer->assertAllowsAnnotations();
        $this->finalizer->assertRectHasPositiveDimensions($box, 'Popup annotation');

        $popup = new PopupAnnotation(
            $this->context->nextObjectId(),
            $this->page,
            $parent,
            $box->x,
            $box->y,
            $box->width,
            $box->height,
            $open,
        );

        if (method_exists($parent, 'withPopup')) {
            $parent->withPopup($popup);
        }

        return $popup;
    }
}
