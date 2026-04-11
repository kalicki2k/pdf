<?php

declare(strict_types=1);

namespace Kalle\Pdf\Page\Annotation;

use InvalidArgumentException;
use Kalle\Pdf\Document\Attachment\FileSpecification;
use Kalle\Pdf\Layout\Geometry\Rect;
use Kalle\Pdf\Page\Annotation\Style\AnnotationBorderStyle;
use Kalle\Pdf\Page\Page;
use Kalle\Pdf\Style\Color;

final readonly class PageBoxAnnotationFactory
{
    private PageTextMarkupAnnotationFactory $textMarkupAnnotations;

    public function __construct(
        private Page $page,
        private PageAnnotationFactoryContext $context,
        private PageAnnotationFinalizer $finalizer,
    ) {
        $this->textMarkupAnnotations = new PageTextMarkupAnnotationFactory($page, $context, $finalizer);
    }

    public function createFileAttachmentAnnotation(
        Rect $box,
        FileSpecification $file,
        string $icon,
        ?string $contents,
    ): FileAttachmentAnnotation {
        $this->finalizer->assertAllowsAnnotations();
        $this->finalizer->assertRectHasPositiveDimensions($box, 'File attachment');
        $this->page->getDocument()->assertAllowsAttachments();

        if ($icon === '') {
            throw new InvalidArgumentException('File attachment icon must not be empty.');
        }

        $annotation = new FileAttachmentAnnotation(
            $this->context->nextObjectId(),
            $this->page,
            $box->x,
            $box->y,
            $box->width,
            $box->height,
            $file,
            $icon,
            $contents,
        );

        $this->finalizer->finalizeAccessibleAnnotation(
            $annotation,
            'File attachment',
            $contents,
            $file->getFilename(),
        );

        return $annotation;
    }

    public function createTextAnnotation(
        Rect $box,
        string $contents,
        ?string $title,
        string $icon,
        bool $open,
    ): TextAnnotation {
        $this->finalizer->assertAllowsAnnotations();
        $this->finalizer->assertRectHasPositiveDimensions($box, 'Text annotation');

        if ($contents === '') {
            throw new InvalidArgumentException('Text annotation contents must not be empty.');
        }

        if ($icon === '') {
            throw new InvalidArgumentException('Text annotation icon must not be empty.');
        }

        $annotation = new TextAnnotation(
            $this->context->nextObjectId(),
            $this->page,
            $box->x,
            $box->y,
            $box->width,
            $box->height,
            $contents,
            $title,
            $icon,
            $open,
        );

        $this->finalizer->finalizeBoxAnnotation($annotation, $box, 'Text annotation', $contents, $title, $icon);

        return $annotation;
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
        $this->finalizer->assertAllowsAnnotations();
        $this->finalizer->assertRectHasPositiveDimensions($box, 'Free text annotation');

        if ($contents === '') {
            throw new InvalidArgumentException('Free text annotation contents must not be empty.');
        }

        if ($size <= 0) {
            throw new InvalidArgumentException('Free text annotation font size must be greater than zero.');
        }

        $font = $this->context->resolveFont($baseFont);
        $fontResourceName = $this->context->registerFontResource($font);

        $annotation = new FreeTextAnnotation(
            $this->context->nextObjectId(),
            $this->page,
            $box->x,
            $box->y,
            $box->width,
            $box->height,
            $contents,
            $fontResourceName,
            $size,
            $textColor,
            $borderColor,
            $fillColor,
            $title,
        );

        $this->finalizer->finalizeBoxAnnotation($annotation, $box, 'Free text annotation', $contents, $title);

        return $annotation;
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
        $this->finalizer->assertAllowsAnnotations();
        $this->finalizer->assertRectHasPositiveDimensions($box, 'Stamp annotation');

        if ($icon === '') {
            throw new InvalidArgumentException('Stamp annotation icon must not be empty.');
        }

        $annotation = new StampAnnotation(
            $this->context->nextObjectId(),
            $this->page,
            $box->x,
            $box->y,
            $box->width,
            $box->height,
            $icon,
            $color,
            $contents,
            $title,
        );

        $this->finalizer->finalizeBoxAnnotation($annotation, $box, 'Stamp annotation', $contents, $title, $icon);

        return $annotation;
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

    public function createCaretAnnotation(
        Rect $box,
        ?string $contents,
        ?string $title,
        string $symbol,
    ): CaretAnnotation {
        $this->finalizer->assertAllowsAnnotations();
        $this->finalizer->assertRectHasPositiveDimensions($box, 'Caret annotation');

        $annotation = new CaretAnnotation(
            $this->context->nextObjectId(),
            $this->page,
            $box->x,
            $box->y,
            $box->width,
            $box->height,
            $contents,
            $title,
            $symbol,
        );

        $this->finalizer->finalizeBoxAnnotation($annotation, $box, 'Caret annotation', $contents, $title, $symbol);

        return $annotation;
    }
}
