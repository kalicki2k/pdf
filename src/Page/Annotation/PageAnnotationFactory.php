<?php

declare(strict_types=1);

namespace Kalle\Pdf\Page\Annotation;

use Kalle\Pdf\Document\Attachment\FileSpecification;
use Kalle\Pdf\Layout\Geometry\Position;
use Kalle\Pdf\Layout\Geometry\Rect;
use Kalle\Pdf\Object\IndirectObject;
use Kalle\Pdf\Page\Annotation\Style\AnnotationBorderStyle;
use Kalle\Pdf\Page\Annotation\Style\LineEndingStyle;
use Kalle\Pdf\Page\Link\LinkTarget;
use Kalle\Pdf\Page\Page;
use Kalle\Pdf\Style\Color;
use Kalle\Pdf\TaggedPdf\StructElem;

/**
 * Builds page annotations so Page can stay focused on the public API surface.
 */
final readonly class PageAnnotationFactory
{
    private PageInteractiveAnnotationFactory $interactiveAnnotations;
    private PageBoxAnnotationFactory $boxAnnotations;
    private PageGeometricAnnotationFactory $geometricAnnotations;

    public function __construct(Page $page, PageAnnotationFactoryContext $context)
    {
        $finalizer = new PageAnnotationFinalizer($page, $context);
        $this->interactiveAnnotations = new PageInteractiveAnnotationFactory($page, $context, $finalizer);
        $this->boxAnnotations = new PageBoxAnnotationFactory($page, $context, $finalizer);
        $this->geometricAnnotations = new PageGeometricAnnotationFactory($page, $context, $finalizer);
    }

    public function createLinkAnnotation(
        Rect $box,
        LinkTarget $target,
        ?StructElem $linkStructElem = null,
        ?string $alternativeDescription = null,
    ): LinkAnnotation {
        return $this->interactiveAnnotations->createLinkAnnotation($box, $target, $linkStructElem, $alternativeDescription);
    }

    public function createFileAttachmentAnnotation(
        Rect $box,
        FileSpecification $file,
        string $icon,
        ?string $contents,
    ): FileAttachmentAnnotation {
        return $this->boxAnnotations->createFileAttachmentAnnotation($box, $file, $icon, $contents);
    }

    public function createTextAnnotation(
        Rect $box,
        string $contents,
        ?string $title,
        string $icon,
        bool $open,
    ): TextAnnotation {
        return $this->boxAnnotations->createTextAnnotation($box, $contents, $title, $icon, $open);
    }

    public function createPopupAnnotation(
        IndirectObject $parent,
        Rect $box,
        bool $open,
    ): PopupAnnotation {
        return $this->interactiveAnnotations->createPopupAnnotation($parent, $box, $open);
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
        return $this->boxAnnotations->createFreeTextAnnotation($box, $contents, $baseFont, $size, $textColor, $borderColor, $fillColor, $title);
    }

    public function createHighlightAnnotation(
        Rect $box,
        ?Color $color,
        ?string $contents,
        ?string $title,
    ): HighlightAnnotation {
        return $this->boxAnnotations->createHighlightAnnotation($box, $color, $contents, $title);
    }

    public function createUnderlineAnnotation(
        Rect $box,
        ?Color $color,
        ?string $contents,
        ?string $title,
    ): UnderlineAnnotation {
        return $this->boxAnnotations->createUnderlineAnnotation($box, $color, $contents, $title);
    }

    public function createStrikeOutAnnotation(
        Rect $box,
        ?Color $color,
        ?string $contents,
        ?string $title,
    ): StrikeOutAnnotation {
        return $this->boxAnnotations->createStrikeOutAnnotation($box, $color, $contents, $title);
    }

    public function createSquigglyAnnotation(
        Rect $box,
        ?Color $color,
        ?string $contents,
        ?string $title,
    ): SquigglyAnnotation {
        return $this->boxAnnotations->createSquigglyAnnotation($box, $color, $contents, $title);
    }

    public function createStampAnnotation(
        Rect $box,
        string $icon,
        ?Color $color,
        ?string $contents,
        ?string $title,
    ): StampAnnotation {
        return $this->boxAnnotations->createStampAnnotation($box, $icon, $color, $contents, $title);
    }

    public function createSquareAnnotation(
        Rect $box,
        ?Color $borderColor,
        ?Color $fillColor,
        ?string $contents,
        ?string $title,
        ?AnnotationBorderStyle $borderStyle,
    ): SquareAnnotation {
        return $this->boxAnnotations->createSquareAnnotation($box, $borderColor, $fillColor, $contents, $title, $borderStyle);
    }

    public function createCircleAnnotation(
        Rect $box,
        ?Color $borderColor,
        ?Color $fillColor,
        ?string $contents,
        ?string $title,
        ?AnnotationBorderStyle $borderStyle,
    ): CircleAnnotation {
        return $this->boxAnnotations->createCircleAnnotation($box, $borderColor, $fillColor, $contents, $title, $borderStyle);
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
        return $this->boxAnnotations->createInkAnnotation($box, $paths, $color, $contents, $title);
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
        return $this->geometricAnnotations->createLineAnnotation(
            $from,
            $to,
            $color,
            $contents,
            $title,
            $startStyle,
            $endStyle,
            $subject,
            $borderStyle,
        );
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
        return $this->geometricAnnotations->createPolyLineAnnotation(
            $vertices,
            $color,
            $contents,
            $title,
            $startStyle,
            $endStyle,
            $subject,
            $borderStyle,
        );
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
        return $this->geometricAnnotations->createPolygonAnnotation(
            $vertices,
            $borderColor,
            $fillColor,
            $contents,
            $title,
            $subject,
            $borderStyle,
        );
    }

    public function createCaretAnnotation(
        Rect $box,
        ?string $contents,
        ?string $title,
        string $symbol,
    ): CaretAnnotation {
        return $this->boxAnnotations->createCaretAnnotation($box, $contents, $title, $symbol);
    }
}
