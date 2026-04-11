<?php

declare(strict_types=1);

namespace Kalle\Pdf\Page\Annotation;

use Kalle\Pdf\Layout\Geometry\Rect;
use Kalle\Pdf\Page\Page;
use Kalle\Pdf\Style\Color;

final readonly class PageTextMarkupAnnotationFactory
{
    public function __construct(
        private Page $page,
        private PageAnnotationFactoryContext $context,
        private PageAnnotationFinalizer $finalizer,
    ) {
    }

    public function createHighlightAnnotation(
        Rect $box,
        ?Color $color,
        ?string $contents,
        ?string $title,
    ): HighlightAnnotation {
        $this->finalizer->assertAllowsAnnotations();
        $this->finalizer->assertRectHasPositiveDimensions($box, 'Highlight annotation');

        $annotation = new HighlightAnnotation(
            $this->context->nextObjectId(),
            $this->page,
            $box->x,
            $box->y,
            $box->width,
            $box->height,
            $color,
            $contents,
            $title,
        );

        $this->finalizer->finalizeBoxAnnotation($annotation, $box, 'Highlight annotation', $contents, $title);

        return $annotation;
    }

    public function createUnderlineAnnotation(
        Rect $box,
        ?Color $color,
        ?string $contents,
        ?string $title,
    ): UnderlineAnnotation {
        $this->finalizer->assertAllowsAnnotations();
        $this->finalizer->assertRectHasPositiveDimensions($box, 'Underline annotation');

        $annotation = new UnderlineAnnotation(
            $this->context->nextObjectId(),
            $this->page,
            $box->x,
            $box->y,
            $box->width,
            $box->height,
            $color,
            $contents,
            $title,
        );

        $this->finalizer->finalizeBoxAnnotation($annotation, $box, 'Underline annotation', $contents, $title);

        return $annotation;
    }

    public function createStrikeOutAnnotation(
        Rect $box,
        ?Color $color,
        ?string $contents,
        ?string $title,
    ): StrikeOutAnnotation {
        $this->finalizer->assertAllowsAnnotations();
        $this->finalizer->assertRectHasPositiveDimensions($box, 'StrikeOut annotation');

        $annotation = new StrikeOutAnnotation(
            $this->context->nextObjectId(),
            $this->page,
            $box->x,
            $box->y,
            $box->width,
            $box->height,
            $color,
            $contents,
            $title,
        );

        $this->finalizer->finalizeBoxAnnotation($annotation, $box, 'Strike-out annotation', $contents, $title);

        return $annotation;
    }

    public function createSquigglyAnnotation(
        Rect $box,
        ?Color $color,
        ?string $contents,
        ?string $title,
    ): SquigglyAnnotation {
        $this->finalizer->assertAllowsAnnotations();
        $this->finalizer->assertRectHasPositiveDimensions($box, 'Squiggly annotation');

        $annotation = new SquigglyAnnotation(
            $this->context->nextObjectId(),
            $this->page,
            $box->x,
            $box->y,
            $box->width,
            $box->height,
            $color,
            $contents,
            $title,
        );

        $this->finalizer->finalizeBoxAnnotation($annotation, $box, 'Squiggly annotation', $contents, $title);

        return $annotation;
    }
}
