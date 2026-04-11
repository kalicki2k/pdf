<?php

declare(strict_types=1);

namespace Kalle\Pdf\Page\Annotation;

use Kalle\Pdf\Layout\Geometry\Position;
use Kalle\Pdf\Page\Annotation\Style\AnnotationBorderStyle;
use Kalle\Pdf\Page\Annotation\Style\LineEndingStyle;
use Kalle\Pdf\Page\Page;
use Kalle\Pdf\Style\Color;

final readonly class PageGeometricAnnotationFactory
{
    public function __construct(
        private Page $page,
        private PageAnnotationFactoryContext $context,
        private PageAnnotationFinalizer $finalizer,
    ) {
    }

    public function createLineAnnotation(
        Position $from,
        Position $to,
        ?Color $color,
        ?string $contents,
        ?string $title,
        ?LineEndingStyle $startStyle,
        ?LineEndingStyle $endStyle,
        ?string $subject,
        ?AnnotationBorderStyle $borderStyle,
    ): LineAnnotation {
        $this->finalizer->assertAllowsAnnotations();

        $annotation = new LineAnnotation(
            $this->context->nextObjectId(),
            $this->page,
            $from->x,
            $from->y,
            $to->x,
            $to->y,
            $color,
            $contents,
            $title,
            $startStyle,
            $endStyle,
            $subject,
            $borderStyle,
        );

        $this->finalizer->finalizeLineAnnotation($annotation, $from, $to, 'Line annotation', $contents, $title, $subject);

        return $annotation;
    }

    /**
     * @param list<array{0: float, 1: float}> $vertices
     */
    public function createPolyLineAnnotation(
        array $vertices,
        ?Color $color,
        ?string $contents,
        ?string $title,
        ?LineEndingStyle $startStyle,
        ?LineEndingStyle $endStyle,
        ?string $subject,
        ?AnnotationBorderStyle $borderStyle,
    ): PolyLineAnnotation {
        $this->finalizer->assertAllowsAnnotations();

        $annotation = new PolyLineAnnotation(
            $this->context->nextObjectId(),
            $this->page,
            $vertices,
            $color,
            $contents,
            $title,
            $startStyle,
            $endStyle,
            $subject,
            $borderStyle,
        );

        $this->finalizer->finalizeVerticesAnnotation($annotation, $vertices, 'Polyline annotation', $contents, $title, $subject);

        return $annotation;
    }

    /**
     * @param list<array{0: float, 1: float}> $vertices
     */
    public function createPolygonAnnotation(
        array $vertices,
        ?Color $borderColor,
        ?Color $fillColor,
        ?string $contents,
        ?string $title,
        ?string $subject,
        ?AnnotationBorderStyle $borderStyle,
    ): PolygonAnnotation {
        $this->finalizer->assertAllowsAnnotations();

        $annotation = new PolygonAnnotation(
            $this->context->nextObjectId(),
            $this->page,
            $vertices,
            $borderColor,
            $fillColor,
            $contents,
            $title,
            $subject,
            $borderStyle,
        );

        $this->finalizer->finalizeVerticesAnnotation($annotation, $vertices, 'Polygon annotation', $contents, $title, $subject);

        return $annotation;
    }
}
