<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document\Annotation;

use Closure;
use Kalle\Pdf\Document\FileSpecification;
use Kalle\Pdf\Document\Geometry\Position;
use Kalle\Pdf\Document\Geometry\Rect;
use Kalle\Pdf\Document\LinkTarget;
use Kalle\Pdf\Document\Page;
use Kalle\Pdf\Font\FontDefinition;
use Kalle\Pdf\Graphics\Color;
use Kalle\Pdf\Object\IndirectObject;
use Kalle\Pdf\Structure\StructElem;

/**
 * @internal Collects page annotations so Page can stay focused on its public API.
 */
final class PageAnnotations
{
    /** @var list<IndirectObject&PageAnnotation> */
    private array $annotations = [];
    private ?PageAnnotationFactory $factory = null;

    /**
     * @param Closure(string): FontDefinition $resolveFont
     * @param Closure(FontDefinition): string $registerFontResource
     */
    public function __construct(
        private readonly Page $page,
        private readonly Closure $resolveFont,
        private readonly Closure $registerFontResource,
    ) {
    }

    public function addLinkAnnotation(
        Rect $box,
        LinkTarget $target,
        ?StructElem $linkStructElem = null,
        ?string $alternativeDescription = null,
    ): void {
        $this->annotations[] = $this->factory()->createLinkAnnotation($box, $target, $linkStructElem, $alternativeDescription);
    }

    public function addFileAttachmentAnnotation(
        Rect $box,
        FileSpecification $file,
        string $icon,
        ?string $contents,
    ): void {
        $this->annotations[] = $this->factory()->createFileAttachmentAnnotation($box, $file, $icon, $contents);
    }

    public function addTextAnnotation(
        Rect $box,
        string $contents,
        ?string $title,
        string $icon,
        bool $open,
    ): void {
        $this->annotations[] = $this->factory()->createTextAnnotation($box, $contents, $title, $icon, $open);
    }

    public function addPopupAnnotation(
        PageAnnotation & IndirectObject $parent,
        Rect $box,
        bool $open,
    ): void {
        $this->factory()->createPopupAnnotation($parent, $box, $open);
    }

    public function add(PageAnnotation & IndirectObject $annotation): void
    {
        $this->annotations[] = $annotation;
    }

    public function addFreeTextAnnotation(
        Rect $box,
        string $contents,
        string $baseFont,
        int $size,
        ?Color $textColor,
        ?Color $borderColor,
        ?Color $fillColor,
        ?string $title,
    ): void {
        $this->annotations[] = $this->factory()->createFreeTextAnnotation(
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

    public function addHighlightAnnotation(
        Rect $box,
        ?Color $color,
        ?string $contents,
        ?string $title,
    ): void {
        $this->annotations[] = $this->factory()->createHighlightAnnotation($box, $color, $contents, $title);
    }

    public function addUnderlineAnnotation(
        Rect $box,
        ?Color $color,
        ?string $contents,
        ?string $title,
    ): void {
        $this->annotations[] = $this->factory()->createUnderlineAnnotation($box, $color, $contents, $title);
    }

    public function addStrikeOutAnnotation(
        Rect $box,
        ?Color $color,
        ?string $contents,
        ?string $title,
    ): void {
        $this->annotations[] = $this->factory()->createStrikeOutAnnotation($box, $color, $contents, $title);
    }

    public function addSquigglyAnnotation(
        Rect $box,
        ?Color $color,
        ?string $contents,
        ?string $title,
    ): void {
        $this->annotations[] = $this->factory()->createSquigglyAnnotation($box, $color, $contents, $title);
    }

    public function addStampAnnotation(
        Rect $box,
        string $icon,
        ?Color $color,
        ?string $contents,
        ?string $title,
    ): void {
        $this->annotations[] = $this->factory()->createStampAnnotation($box, $icon, $color, $contents, $title);
    }

    public function addSquareAnnotation(
        Rect $box,
        ?Color $borderColor,
        ?Color $fillColor,
        ?string $contents,
        ?string $title,
        ?AnnotationBorderStyle $borderStyle,
    ): void {
        $this->annotations[] = $this->factory()->createSquareAnnotation(
            $box,
            $borderColor,
            $fillColor,
            $contents,
            $title,
            $borderStyle,
        );
    }

    public function addCircleAnnotation(
        Rect $box,
        ?Color $borderColor,
        ?Color $fillColor,
        ?string $contents,
        ?string $title,
        ?AnnotationBorderStyle $borderStyle,
    ): void {
        $this->annotations[] = $this->factory()->createCircleAnnotation(
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
    public function addInkAnnotation(
        Rect $box,
        array $paths,
        ?Color $color,
        ?string $contents,
        ?string $title,
    ): void {
        $this->annotations[] = $this->factory()->createInkAnnotation($box, $paths, $color, $contents, $title);
    }

    public function addLineAnnotation(
        Position $from,
        Position $to,
        ?Color $color,
        ?string $contents,
        ?string $title,
        ?LineEndingStyle $startStyle,
        ?LineEndingStyle $endStyle,
        ?string $subject,
        ?AnnotationBorderStyle $borderStyle,
    ): void {
        $this->annotations[] = $this->factory()->createLineAnnotation(
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
    public function addPolyLineAnnotation(
        array $vertices,
        ?Color $color,
        ?string $contents,
        ?string $title,
        ?LineEndingStyle $startStyle,
        ?LineEndingStyle $endStyle,
        ?string $subject,
        ?AnnotationBorderStyle $borderStyle,
    ): void {
        $this->annotations[] = $this->factory()->createPolyLineAnnotation(
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
    public function addPolygonAnnotation(
        array $vertices,
        ?Color $borderColor,
        ?Color $fillColor,
        ?string $contents,
        ?string $title,
        ?string $subject,
        ?AnnotationBorderStyle $borderStyle,
    ): void {
        $this->annotations[] = $this->factory()->createPolygonAnnotation(
            $vertices,
            $borderColor,
            $fillColor,
            $contents,
            $title,
            $subject,
            $borderStyle,
        );
    }

    public function addCaretAnnotation(
        Rect $box,
        ?string $contents,
        ?string $title,
        string $symbol,
    ): void {
        $this->annotations[] = $this->factory()->createCaretAnnotation($box, $contents, $title, $symbol);
    }

    /**
     * @return list<IndirectObject&PageAnnotation>
     */
    public function all(): array
    {
        return $this->annotations;
    }

    private function factory(): PageAnnotationFactory
    {
        return $this->factory ??= new PageAnnotationFactory(
            $this->page,
            fn (): int => $this->page->getDocument()->getUniqObjectId(),
            $this->resolveFont,
            $this->registerFontResource,
        );
    }
}
