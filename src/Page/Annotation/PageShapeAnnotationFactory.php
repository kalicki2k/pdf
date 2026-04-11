<?php

declare(strict_types=1);

namespace Kalle\Pdf\Page\Annotation;

use Kalle\Pdf\Layout\Geometry\Rect;
use Kalle\Pdf\Page\Annotation\Style\AnnotationBorderStyle;
use Kalle\Pdf\Page\Page;
use Kalle\Pdf\Style\Color;

final readonly class PageShapeAnnotationFactory
{
    public function __construct(
        private Page $page,
        private PageAnnotationFactoryContext $context,
        private PageAnnotationFinalizer $finalizer,
    ) {
    }

    public function createSquareAnnotation(
        Rect $box,
        ?Color $borderColor,
        ?Color $fillColor,
        ?string $contents,
        ?string $title,
        ?AnnotationBorderStyle $borderStyle,
    ): SquareAnnotation {
        $this->finalizer->assertAllowsAnnotations();
        $this->finalizer->assertRectHasPositiveDimensions($box, 'Square annotation');

        $annotation = new SquareAnnotation(
            $this->context->nextObjectId(),
            $this->page,
            $box->x,
            $box->y,
            $box->width,
            $box->height,
            $borderColor,
            $fillColor,
            $contents,
            $title,
            $borderStyle,
        );

        $this->finalizer->finalizeBoxAnnotation($annotation, $box, 'Square annotation', $contents, $title);

        return $annotation;
    }

    public function createCircleAnnotation(
        Rect $box,
        ?Color $borderColor,
        ?Color $fillColor,
        ?string $contents,
        ?string $title,
        ?AnnotationBorderStyle $borderStyle,
    ): CircleAnnotation {
        $this->finalizer->assertAllowsAnnotations();
        $this->finalizer->assertRectHasPositiveDimensions($box, 'Circle annotation');

        $annotation = new CircleAnnotation(
            $this->context->nextObjectId(),
            $this->page,
            $box->x,
            $box->y,
            $box->width,
            $box->height,
            $borderColor,
            $fillColor,
            $contents,
            $title,
            $borderStyle,
        );

        $this->finalizer->finalizeBoxAnnotation($annotation, $box, 'Circle annotation', $contents, $title);

        return $annotation;
    }

    /**
     * @param list<list<array{0: float, 1: float}>> $paths
     */
    public function createInkAnnotation(
        Rect $box,
        array $paths,
        ?Color $color,
        ?string $contents,
        ?string $title,
    ): InkAnnotation {
        $this->finalizer->assertAllowsAnnotations();
        $this->finalizer->assertRectHasPositiveDimensions($box, 'Ink annotation');

        $annotation = new InkAnnotation(
            $this->context->nextObjectId(),
            $this->page,
            $box->x,
            $box->y,
            $box->width,
            $box->height,
            $paths,
            $color,
            $contents,
            $title,
        );

        $this->finalizer->finalizeBoxAnnotation($annotation, $box, 'Ink annotation', $contents, $title);

        return $annotation;
    }
}
