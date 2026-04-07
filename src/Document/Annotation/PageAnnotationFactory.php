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
    private const MINIMUM_APPEARANCE_DIMENSION = 0.0001;

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
        $this->page->getDocument()->assertAllowsAttachments();

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

        $annotation = new TextAnnotation(
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

        if ($this->page->getDocument()->getProfile()->isPdfA()) {
            $annotation->withAppearance($this->createAppearanceStream($box->width, $box->height));
        }

        return $annotation;
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

        $annotation = new FreeTextAnnotation(
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

        if ($this->page->getDocument()->getProfile()->isPdfA()) {
            $annotation->withAppearance($this->createAppearanceStream($box->width, $box->height));
        }

        return $annotation;
    }

    public function createHighlightAnnotation(
        Rect $box,
        ?Color $color,
        ?string $contents,
        ?string $title,
    ): HighlightAnnotation {
        $this->assertRectHasPositiveDimensions($box, 'Highlight annotation');

        $annotation = new HighlightAnnotation(
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

        if ($this->page->getDocument()->getProfile()->isPdfA()) {
            $annotation->withAppearance($this->createAppearanceStream($box->width, $box->height));
        }

        return $annotation;
    }

    public function createUnderlineAnnotation(
        Rect $box,
        ?Color $color,
        ?string $contents,
        ?string $title,
    ): UnderlineAnnotation {
        $this->assertRectHasPositiveDimensions($box, 'Underline annotation');

        $annotation = new UnderlineAnnotation(
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

        if ($this->page->getDocument()->getProfile()->isPdfA()) {
            $annotation->withAppearance($this->createAppearanceStream($box->width, $box->height));
        }

        return $annotation;
    }

    public function createStrikeOutAnnotation(
        Rect $box,
        ?Color $color,
        ?string $contents,
        ?string $title,
    ): StrikeOutAnnotation {
        $this->assertRectHasPositiveDimensions($box, 'StrikeOut annotation');

        $annotation = new StrikeOutAnnotation(
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

        if ($this->page->getDocument()->getProfile()->isPdfA()) {
            $annotation->withAppearance($this->createAppearanceStream($box->width, $box->height));
        }

        return $annotation;
    }

    public function createSquigglyAnnotation(
        Rect $box,
        ?Color $color,
        ?string $contents,
        ?string $title,
    ): SquigglyAnnotation {
        $this->assertRectHasPositiveDimensions($box, 'Squiggly annotation');

        $annotation = new SquigglyAnnotation(
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

        if ($this->page->getDocument()->getProfile()->isPdfA()) {
            $annotation->withAppearance($this->createAppearanceStream($box->width, $box->height));
        }

        return $annotation;
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

        $annotation = new StampAnnotation(
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

        if ($this->page->getDocument()->getProfile()->isPdfA()) {
            $annotation->withAppearance($this->createAppearanceStream($box->width, $box->height));
        }

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
        $this->assertRectHasPositiveDimensions($box, 'Square annotation');

        $annotation = new SquareAnnotation(
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

        if ($this->page->getDocument()->getProfile()->isPdfA()) {
            $annotation->withAppearance($this->createAppearanceStream($box->width, $box->height));
        }

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
        $this->assertRectHasPositiveDimensions($box, 'Circle annotation');

        $annotation = new CircleAnnotation(
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

        if ($this->page->getDocument()->getProfile()->isPdfA()) {
            $annotation->withAppearance($this->createAppearanceStream($box->width, $box->height));
        }

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
        $this->assertRectHasPositiveDimensions($box, 'Ink annotation');

        $annotation = new InkAnnotation(
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

        if ($this->page->getDocument()->getProfile()->isPdfA()) {
            $annotation->withAppearance($this->createAppearanceStream($box->width, $box->height));
        }

        return $annotation;
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
        $annotation = new LineAnnotation(
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

        if ($this->page->getDocument()->getProfile()->isPdfA()) {
            $annotation->withAppearance($this->createAppearanceStream(
                abs($to->x - $from->x),
                abs($to->y - $from->y),
            ));
        }

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
        $annotation = new PolyLineAnnotation(
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

        if ($this->page->getDocument()->getProfile()->isPdfA()) {
            $annotation->withAppearance($this->createAppearanceStreamForVertices($vertices));
        }

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
        $annotation = new PolygonAnnotation(
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

        if ($this->page->getDocument()->getProfile()->isPdfA()) {
            $annotation->withAppearance($this->createAppearanceStreamForVertices($vertices));
        }

        return $annotation;
    }

    public function createCaretAnnotation(
        Rect $box,
        ?string $contents,
        ?string $title,
        string $symbol,
    ): CaretAnnotation {
        $this->assertRectHasPositiveDimensions($box, 'Caret annotation');

        $annotation = new CaretAnnotation(
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

        if ($this->page->getDocument()->getProfile()->isPdfA()) {
            $annotation->withAppearance($this->createAppearanceStream($box->width, $box->height));
        }

        return $annotation;
    }

    private function nextObjectId(): int
    {
        return ($this->nextObjectId)();
    }

    private function createAppearanceStream(float $width, float $height): TextAnnotationAppearanceStream
    {
        return new TextAnnotationAppearanceStream(
            $this->nextObjectId(),
            max(self::MINIMUM_APPEARANCE_DIMENSION, $width),
            max(self::MINIMUM_APPEARANCE_DIMENSION, $height),
        );
    }

    /**
     * @param list<array{0: float, 1: float}> $vertices
     */
    private function createAppearanceStreamForVertices(array $vertices): TextAnnotationAppearanceStream
    {
        $xValues = array_map(static fn (array $vertex): float => $vertex[0], $vertices);
        $yValues = array_map(static fn (array $vertex): float => $vertex[1], $vertices);
        assert($xValues !== []);
        assert($yValues !== []);

        return $this->createAppearanceStream(
            max($xValues) - min($xValues),
            max($yValues) - min($yValues),
        );
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
