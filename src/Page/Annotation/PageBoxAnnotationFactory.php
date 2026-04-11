<?php

declare(strict_types=1);

namespace Kalle\Pdf\Page\Annotation;

use Kalle\Pdf\Document\Attachment\FileSpecification;
use Kalle\Pdf\Layout\Geometry\Rect;
use Kalle\Pdf\Page\Annotation\Style\AnnotationBorderStyle;
use Kalle\Pdf\Page\Page;
use Kalle\Pdf\Style\Color;

final readonly class PageBoxAnnotationFactory
{
    private PageCommentAnnotationFactory $commentAnnotations;
    private PageShapeAnnotationFactory $shapeAnnotations;
    private PageTextMarkupAnnotationFactory $textMarkupAnnotations;

    public function __construct(
        Page $page,
        PageAnnotationFactoryContext $context,
        PageAnnotationFinalizer $finalizer,
    ) {
        $this->commentAnnotations = new PageCommentAnnotationFactory($page, $context, $finalizer);
        $this->shapeAnnotations = new PageShapeAnnotationFactory($page, $context, $finalizer);
        $this->textMarkupAnnotations = new PageTextMarkupAnnotationFactory($page, $context, $finalizer);
    }

    public function createFileAttachmentAnnotation(
        Rect $box,
        FileSpecification $file,
        string $icon,
        ?string $contents,
    ): FileAttachmentAnnotation {
        return $this->commentAnnotations->createFileAttachmentAnnotation($box, $file, $icon, $contents);
    }

    public function createTextAnnotation(
        Rect $box,
        string $contents,
        ?string $title,
        string $icon,
        bool $open,
    ): TextAnnotation {
        return $this->commentAnnotations->createTextAnnotation($box, $contents, $title, $icon, $open);
    }

    public function createFreeTextAnnotation(
        Rect $box,
        string $contents,
        string $baseFont,
        int $size,
        ?Color $textColor,
        ?Color $borderColor,
        ?Color $fillColor,
        ?string $title,
    ): FreeTextAnnotation {
        return $this->commentAnnotations->createFreeTextAnnotation(
            $box,
            $contents,
            $baseFont,
            $size,
            $textColor,
            $borderColor,
            $fillColor,
            $title,
        );
    }

    public function createHighlightAnnotation(
        Rect $box,
        ?Color $color,
        ?string $contents,
        ?string $title,
    ): HighlightAnnotation {
        return $this->textMarkupAnnotations->createHighlightAnnotation($box, $color, $contents, $title);
    }

    public function createUnderlineAnnotation(
        Rect $box,
        ?Color $color,
        ?string $contents,
        ?string $title,
    ): UnderlineAnnotation {
        return $this->textMarkupAnnotations->createUnderlineAnnotation($box, $color, $contents, $title);
    }

    public function createStrikeOutAnnotation(
        Rect $box,
        ?Color $color,
        ?string $contents,
        ?string $title,
    ): StrikeOutAnnotation {
        return $this->textMarkupAnnotations->createStrikeOutAnnotation($box, $color, $contents, $title);
    }

    public function createSquigglyAnnotation(
        Rect $box,
        ?Color $color,
        ?string $contents,
        ?string $title,
    ): SquigglyAnnotation {
        return $this->textMarkupAnnotations->createSquigglyAnnotation($box, $color, $contents, $title);
    }

    public function createStampAnnotation(
        Rect $box,
        string $icon,
        ?Color $color,
        ?string $contents,
        ?string $title,
    ): StampAnnotation {
        return $this->commentAnnotations->createStampAnnotation($box, $icon, $color, $contents, $title);
    }

    public function createSquareAnnotation(
        Rect $box,
        ?Color $borderColor,
        ?Color $fillColor,
        ?string $contents,
        ?string $title,
        ?AnnotationBorderStyle $borderStyle,
    ): SquareAnnotation {
        return $this->shapeAnnotations->createSquareAnnotation(
            $box,
            $borderColor,
            $fillColor,
            $contents,
            $title,
            $borderStyle,
        );
    }

    public function createCircleAnnotation(
        Rect $box,
        ?Color $borderColor,
        ?Color $fillColor,
        ?string $contents,
        ?string $title,
        ?AnnotationBorderStyle $borderStyle,
    ): CircleAnnotation {
        return $this->shapeAnnotations->createCircleAnnotation(
            $box,
            $borderColor,
            $fillColor,
            $contents,
            $title,
            $borderStyle,
        );
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
        return $this->shapeAnnotations->createInkAnnotation($box, $paths, $color, $contents, $title);
    }

    public function createCaretAnnotation(
        Rect $box,
        ?string $contents,
        ?string $title,
        string $symbol,
    ): CaretAnnotation {
        return $this->commentAnnotations->createCaretAnnotation($box, $contents, $title, $symbol);
    }
}
