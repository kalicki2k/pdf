<?php

declare(strict_types=1);

namespace Kalle\Pdf\Page;

use Kalle\Pdf\Document\Attachment\FileSpecification;
use Kalle\Pdf\Layout\Geometry\Position;
use Kalle\Pdf\Layout\Geometry\Rect;
use Kalle\Pdf\Object\IndirectObject;
use Kalle\Pdf\Page\Annotation\PageAnnotation as PopupParentAnnotation;
use Kalle\Pdf\Page\Annotation\Style\AnnotationBorderStyle;
use Kalle\Pdf\Page\Annotation\Style\LineEndingStyle;
use Kalle\Pdf\Style\Color;

trait HandlesPageAnnotations
{
    public function addFileAttachment(
        Rect $box,
        FileSpecification $file,
        string $icon = 'PushPin',
        ?string $contents = null,
    ): self {
        $this->pageAnnotations()->addFileAttachmentAnnotation($box, $file, $icon, $contents);

        return $this;
    }

    public function addTextAnnotation(
        Rect $box,
        string $contents,
        ?string $title = null,
        string $icon = 'Note',
        bool $open = false,
    ): self {
        $this->pageAnnotations()->addTextAnnotation($box, $contents, $title, $icon, $open);

        return $this;
    }

    public function addPopupAnnotation(
        PopupParentAnnotation & IndirectObject $parent,
        Rect $box,
        bool $open = false,
    ): self {
        $this->pageAnnotations()->addPopupAnnotation($parent, $box, $open);

        return $this;
    }

    public function addFreeTextAnnotation(
        Rect $box,
        string $contents,
        string $baseFont = 'Helvetica',
        int $size = 12,
        ?Color $textColor = null,
        ?Color $borderColor = null,
        ?Color $fillColor = null,
        ?string $title = null,
    ): self {
        $this->pageAnnotations()->addFreeTextAnnotation(
            $box,
            $contents,
            $baseFont,
            $size,
            $textColor,
            $borderColor,
            $fillColor,
            $title,
        );

        return $this;
    }

    public function addHighlightAnnotation(
        Rect $box,
        ?Color $color = null,
        ?string $contents = null,
        ?string $title = null,
    ): self {
        $this->pageAnnotations()->addHighlightAnnotation($box, $color, $contents, $title);

        return $this;
    }

    public function addUnderlineAnnotation(
        Rect $box,
        ?Color $color = null,
        ?string $contents = null,
        ?string $title = null,
    ): self {
        $this->pageAnnotations()->addUnderlineAnnotation($box, $color, $contents, $title);

        return $this;
    }

    public function addStrikeOutAnnotation(
        Rect $box,
        ?Color $color = null,
        ?string $contents = null,
        ?string $title = null,
    ): self {
        $this->pageAnnotations()->addStrikeOutAnnotation($box, $color, $contents, $title);

        return $this;
    }

    public function addSquigglyAnnotation(
        Rect $box,
        ?Color $color = null,
        ?string $contents = null,
        ?string $title = null,
    ): self {
        $this->pageAnnotations()->addSquigglyAnnotation($box, $color, $contents, $title);

        return $this;
    }

    public function addStampAnnotation(
        Rect $box,
        string $icon = 'Draft',
        ?Color $color = null,
        ?string $contents = null,
        ?string $title = null,
    ): self {
        $this->pageAnnotations()->addStampAnnotation($box, $icon, $color, $contents, $title);

        return $this;
    }

    public function addSquareAnnotation(
        Rect $box,
        ?Color $borderColor = null,
        ?Color $fillColor = null,
        ?string $contents = null,
        ?string $title = null,
        ?AnnotationBorderStyle $borderStyle = null,
    ): self {
        $this->pageAnnotations()->addSquareAnnotation(
            $box,
            $borderColor,
            $fillColor,
            $contents,
            $title,
            $borderStyle,
        );

        return $this;
    }

    public function addCircleAnnotation(
        Rect $box,
        ?Color $borderColor = null,
        ?Color $fillColor = null,
        ?string $contents = null,
        ?string $title = null,
        ?AnnotationBorderStyle $borderStyle = null,
    ): self {
        $this->pageAnnotations()->addCircleAnnotation(
            $box,
            $borderColor,
            $fillColor,
            $contents,
            $title,
            $borderStyle,
        );

        return $this;
    }

    /**
     * @param list<list<array{0: float, 1: float}>> $paths
     */
    public function addInkAnnotation(
        Rect $box,
        array $paths,
        ?Color $color = null,
        ?string $contents = null,
        ?string $title = null,
    ): self {
        $this->pageAnnotations()->addInkAnnotation($box, $paths, $color, $contents, $title);

        return $this;
    }

    public function addLineAnnotation(
        Position $from,
        Position $to,
        ?Color $color = null,
        ?string $contents = null,
        ?string $title = null,
        ?LineEndingStyle $startStyle = null,
        ?LineEndingStyle $endStyle = null,
        ?string $subject = null,
        ?AnnotationBorderStyle $borderStyle = null,
    ): self {
        $this->pageAnnotations()->addLineAnnotation(
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

        return $this;
    }

    /**
     * @param list<array{0: float, 1: float}> $vertices
     */
    public function addPolyLineAnnotation(
        array $vertices,
        ?Color $color = null,
        ?string $contents = null,
        ?string $title = null,
        ?LineEndingStyle $startStyle = null,
        ?LineEndingStyle $endStyle = null,
        ?string $subject = null,
        ?AnnotationBorderStyle $borderStyle = null,
    ): self {
        $this->pageAnnotations()->addPolyLineAnnotation(
            $vertices,
            $color,
            $contents,
            $title,
            $startStyle,
            $endStyle,
            $subject,
            $borderStyle,
        );

        return $this;
    }

    /**
     * @param list<array{0: float, 1: float}> $vertices
     */
    public function addPolygonAnnotation(
        array $vertices,
        ?Color $borderColor = null,
        ?Color $fillColor = null,
        ?string $contents = null,
        ?string $title = null,
        ?string $subject = null,
        ?AnnotationBorderStyle $borderStyle = null,
    ): self {
        $this->pageAnnotations()->addPolygonAnnotation(
            $vertices,
            $borderColor,
            $fillColor,
            $contents,
            $title,
            $subject,
            $borderStyle,
        );

        return $this;
    }

    public function addCaretAnnotation(
        Rect $box,
        ?string $contents = null,
        ?string $title = null,
        string $symbol = 'None',
    ): self {
        $this->pageAnnotations()->addCaretAnnotation($box, $contents, $title, $symbol);

        return $this;
    }
}
