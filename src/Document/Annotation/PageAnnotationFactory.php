<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document\Annotation;

use Closure;
use InvalidArgumentException;
use Kalle\Pdf\Document\FileSpecification;
use Kalle\Pdf\Document\Geometry\Position;
use Kalle\Pdf\Document\Geometry\Rect;
use Kalle\Pdf\Document\LinkTarget;
use Kalle\Pdf\Document\Page;
use Kalle\Pdf\Font\FontDefinition;
use Kalle\Pdf\Graphics\Color;
use Kalle\Pdf\Object\IndirectObject;

/**
 * Builds page annotations so Page can stay focused on the public API surface.
 */
final readonly class PageAnnotationFactory
{
    /**
     * @param Closure(): int $nextObjectId
     * @param Closure(string): FontDefinition $resolveFont
     * @param Closure(FontDefinition): string $registerFontResource
     */
    public function __construct(
        private Page    $page,
        private Closure $nextObjectId,
        private Closure $resolveFont,
        private Closure $registerFontResource,
    ) {
    }

    public function createLinkAnnotation(Rect $box, LinkTarget $target): LinkAnnotation
    {
        $this->assertRectHasPositiveDimensions($box, 'Link');

        return new LinkAnnotation(
            $this->nextObjectId(),
            $this->page,
            $box->x,
            $box->y,
            $box->width,
            $box->height,
            $target,
        );
    }

    public function createFileAttachmentAnnotation(
        Rect $box,
        FileSpecification $file,
        string $icon,
        ?string $contents,
    ): FileAttachmentAnnotation {
        $this->assertRectHasPositiveDimensions($box, 'File attachment');

        if ($icon === '') {
            throw new InvalidArgumentException('File attachment icon must not be empty.');
        }

        return new FileAttachmentAnnotation(
            $this->nextObjectId(),
            $this->page,
            $box->x,
            $box->y,
            $box->width,
            $box->height,
            $file,
            $icon,
            $contents,
        );
    }

    public function createTextAnnotation(
        Rect $box,
        string $contents,
        ?string $title,
        string $icon,
        bool $open,
    ): TextAnnotation {
        $this->assertRectHasPositiveDimensions($box, 'Text annotation');

        if ($contents === '') {
            throw new InvalidArgumentException('Text annotation contents must not be empty.');
        }

        if ($icon === '') {
            throw new InvalidArgumentException('Text annotation icon must not be empty.');
        }

        return new TextAnnotation(
            $this->nextObjectId(),
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
    }

    /**
     * @param PageAnnotation&IndirectObject $parent
     */
    public function createPopupAnnotation(
        IndirectObject $parent,
        Rect $box,
        bool $open,
    ): PopupAnnotation {
        $this->assertRectHasPositiveDimensions($box, 'Popup annotation');

        $popup = new PopupAnnotation(
            $this->nextObjectId(),
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
        $this->assertRectHasPositiveDimensions($box, 'Free text annotation');

        if ($contents === '') {
            throw new InvalidArgumentException('Free text annotation contents must not be empty.');
        }

        if ($size <= 0) {
            throw new InvalidArgumentException('Free text annotation font size must be greater than zero.');
        }

        $font = ($this->resolveFont)($baseFont);
        $fontResourceName = ($this->registerFontResource)($font);

        return new FreeTextAnnotation(
            $this->nextObjectId(),
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
    }

    public function createHighlightAnnotation(
        Rect $box,
        ?Color $color,
        ?string $contents,
        ?string $title,
    ): HighlightAnnotation {
        $this->assertRectHasPositiveDimensions($box, 'Highlight annotation');

        return new HighlightAnnotation(
            $this->nextObjectId(),
            $this->page,
            $box->x,
            $box->y,
            $box->width,
            $box->height,
            $color,
            $contents,
            $title,
        );
    }

    public function createUnderlineAnnotation(
        Rect $box,
        ?Color $color,
        ?string $contents,
        ?string $title,
    ): UnderlineAnnotation {
        $this->assertRectHasPositiveDimensions($box, 'Underline annotation');

        return new UnderlineAnnotation(
            $this->nextObjectId(),
            $this->page,
            $box->x,
            $box->y,
            $box->width,
            $box->height,
            $color,
            $contents,
            $title,
        );
    }

    public function createStrikeOutAnnotation(
        Rect $box,
        ?Color $color,
        ?string $contents,
        ?string $title,
    ): StrikeOutAnnotation {
        $this->assertRectHasPositiveDimensions($box, 'StrikeOut annotation');

        return new StrikeOutAnnotation(
            $this->nextObjectId(),
            $this->page,
            $box->x,
            $box->y,
            $box->width,
            $box->height,
            $color,
            $contents,
            $title,
        );
    }

    public function createSquigglyAnnotation(
        Rect $box,
        ?Color $color,
        ?string $contents,
        ?string $title,
    ): SquigglyAnnotation {
        $this->assertRectHasPositiveDimensions($box, 'Squiggly annotation');

        return new SquigglyAnnotation(
            $this->nextObjectId(),
            $this->page,
            $box->x,
            $box->y,
            $box->width,
            $box->height,
            $color,
            $contents,
            $title,
        );
    }

    public function createStampAnnotation(
        Rect $box,
        string $icon,
        ?Color $color,
        ?string $contents,
        ?string $title,
    ): StampAnnotation {
        $this->assertRectHasPositiveDimensions($box, 'Stamp annotation');

        if ($icon === '') {
            throw new InvalidArgumentException('Stamp annotation icon must not be empty.');
        }

        return new StampAnnotation(
            $this->nextObjectId(),
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
    }

    public function createSquareAnnotation(
        Rect $box,
        ?Color $borderColor,
        ?Color $fillColor,
        ?string $contents,
        ?string $title,
        ?AnnotationBorderStyle $borderStyle,
    ): SquareAnnotation {
        $this->assertRectHasPositiveDimensions($box, 'Square annotation');

        return new SquareAnnotation(
            $this->nextObjectId(),
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
    }

    public function createCircleAnnotation(
        Rect $box,
        ?Color $borderColor,
        ?Color $fillColor,
        ?string $contents,
        ?string $title,
        ?AnnotationBorderStyle $borderStyle,
    ): CircleAnnotation {
        $this->assertRectHasPositiveDimensions($box, 'Circle annotation');

        return new CircleAnnotation(
            $this->nextObjectId(),
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
        $this->assertRectHasPositiveDimensions($box, 'Ink annotation');

        return new InkAnnotation(
            $this->nextObjectId(),
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
        return new LineAnnotation(
            $this->nextObjectId(),
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
        return new PolyLineAnnotation(
            $this->nextObjectId(),
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
        return new PolygonAnnotation(
            $this->nextObjectId(),
            $this->page,
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
        $this->assertRectHasPositiveDimensions($box, 'Caret annotation');

        return new CaretAnnotation(
            $this->nextObjectId(),
            $this->page,
            $box->x,
            $box->y,
            $box->width,
            $box->height,
            $contents,
            $title,
            $symbol,
        );
    }

    private function nextObjectId(): int
    {
        return ($this->nextObjectId)();
    }

    private function assertRectHasPositiveDimensions(Rect $box, string $subject): void
    {
        if ($box->width <= 0) {
            throw new InvalidArgumentException("$subject width must be greater than zero.");
        }

        if ($box->height <= 0) {
            throw new InvalidArgumentException("$subject height must be greater than zero.");
        }
    }
}
