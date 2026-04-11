<?php

declare(strict_types=1);

namespace Kalle\Pdf\Page\Annotation;

use InvalidArgumentException;
use Kalle\Pdf\Document\Attachment\FileSpecification;
use Kalle\Pdf\Layout\Geometry\Rect;
use Kalle\Pdf\Page\Page;
use Kalle\Pdf\Style\Color;

final readonly class PageCommentAnnotationFactory
{
    public function __construct(
        private Page $page,
        private PageAnnotationFactoryContext $context,
        private PageAnnotationFinalizer $finalizer,
    ) {
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
