<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use InvalidArgumentException;
use Kalle\Pdf\Document\Action\ButtonAction;
use Kalle\Pdf\Document\Action\SetOcgStateAction;
use Kalle\Pdf\Document\Annotation\AnnotationBorderStyle;
use Kalle\Pdf\Document\Annotation\LineEndingStyle;
use Kalle\Pdf\Document\Annotation\PageAnnotation;
use Kalle\Pdf\Document\Annotation\PageAnnotations;
use Kalle\Pdf\Document\Form\AcroForm;
use Kalle\Pdf\Document\Form\FormFieldFlags;
use Kalle\Pdf\Document\Form\FormFieldLabel;
use Kalle\Pdf\Document\Form\FormWidgetFactory;
use Kalle\Pdf\Document\Geometry\Position;
use Kalle\Pdf\Document\Geometry\Rect;
use Kalle\Pdf\Document\ImageOptions;
use Kalle\Pdf\Document\Style\BadgeStyle;
use Kalle\Pdf\Document\Style\CalloutStyle;
use Kalle\Pdf\Document\Style\PanelStyle;
use Kalle\Pdf\Document\Text\FlowTextOptions;
use Kalle\Pdf\Document\Text\StructureTag;
use Kalle\Pdf\Document\Text\TextBoxOptions;
use Kalle\Pdf\Document\Text\TextFrame;
use Kalle\Pdf\Document\Text\TextLayoutEngine;
use Kalle\Pdf\Document\Text\TextOptions;
use Kalle\Pdf\Document\Text\TextSegment;
use Kalle\Pdf\Element\DrawImage;
use Kalle\Pdf\Element\Element;
use Kalle\Pdf\Element\Image;
use Kalle\Pdf\Element\Line;
use Kalle\Pdf\Element\Raw;
use Kalle\Pdf\Element\Rectangle;
use Kalle\Pdf\Element\Text;
use Kalle\Pdf\Font\FontDefinition;
use Kalle\Pdf\Font\FontRegistry;
use Kalle\Pdf\Font\OpenTypeFontParser;
use Kalle\Pdf\Font\StandardFontName;
use Kalle\Pdf\Font\UnicodeFont;
use Kalle\Pdf\Graphics\Color;
use Kalle\Pdf\Graphics\Opacity;
use Kalle\Pdf\Layout\HorizontalAlign;
use Kalle\Pdf\Layout\TextOverflow;
use Kalle\Pdf\Layout\VerticalAlign;
use Kalle\Pdf\Object\IndirectObject;
use Kalle\Pdf\Structure\StructElem;
use Kalle\Pdf\Types\ArrayType;
use Kalle\Pdf\Types\DictionaryType;
use Kalle\Pdf\Types\NameType;
use Kalle\Pdf\Types\ReferenceType;

/**
 * @internal Internal page implementation. Use Kalle\Pdf\Page from the public API.
 */
final class Page extends IndirectObject
{
    private const float DEFAULT_LINE_HEIGHT_FACTOR = 1.2;
    private const float DEFAULT_BOTTOM_MARGIN = 20.0;

    private int $markedContentId = 0;
    private ?PageAnnotations $pageAnnotations = null;
    private ?FormWidgetFactory $formWidgetFactory = null;
    private ?TextLayoutEngine $textLayoutEngine = null;
    public Contents $contents;
    public Resources $resources;

    public function __construct(
        public int                $id,
        int                       $contentsId,
        int                       $resourcesId,
        public readonly int       $structParentId,
        private readonly float    $width,
        private readonly float    $height,
        private readonly Document $document,
    ) {
        parent::__construct($this->id);

        $this->contents = new Contents($contentsId);
        $this->resources = new Resources($resourcesId);
    }

    public function addText(
        string $text,
        Position $position,
        string $fontName = 'Helvetica',
        int $size = 12,
        TextOptions $options = new TextOptions(),
    ): self {
        $structureTag = $this->resolveMarkedContentStructureTag($options);

        $artifactTag = $structureTag === null && $this->document->isRenderingArtifactContext()
            ? 'Artifact'
            : null;
        $contentTag = $structureTag !== null
            ? $structureTag->value
            : $artifactTag;

        if ($structureTag !== null) {
            $this->document->ensureStructureEnabled();
        }

        $font = $this->resolveFont($fontName);
        $markedContentId = $structureTag !== null ? $this->markedContentId++ : null;
        $encodedText = $this->encodeText($font, $fontName, $text);
        $resourceFontName = $this->registerFontResource($font);
        $textWidth = $font->measureTextWidth($text, $size);
        [$leadingDecorationInset, $trailingDecorationInset] = $this->resolveDecorationInsets($font, $text, $size);
        $colorOperator = $options->color?->renderNonStrokingOperator();
        $graphicsStateName = $this->resolveGraphicsStateName($options->opacity);

        $this->updateUnicodeFontWidths($font);

        $this->contents->addElement(new Text(
            $markedContentId,
            $encodedText,
            $position->x,
            $position->y,
            $resourceFontName,
            $size,
            $textWidth,
            $colorOperator,
            $graphicsStateName,
            $options->underline,
            $options->strikethrough,
            $contentTag,
            $leadingDecorationInset,
            $trailingDecorationInset,
        ));

        $textStructElem = null;

        if ($structureTag !== null && $markedContentId !== null) {
            $textStructElem = $this->attachTextToStructure($options, $structureTag, $markedContentId, $text);
        }

        if ($options->link !== null && $textWidth > 0.0) {
            $this->addLinkTarget(
                new Rect(
                    $position->x,
                    $position->y - ($size * 0.2),
                    $textWidth,
                    $size,
                ),
                $options->link,
                $textStructElem,
                $this->resolveLinkAlternativeDescription($text),
            );
        }

        return $this;
    }

    /**
     * @param callable(self): void $renderer
     */
    public function layer(string | OptionalContentGroup $layer, callable $renderer, bool $visibleByDefault = true): self
    {
        $group = is_string($layer)
            ? $this->document->addLayer($layer, $visibleByDefault)
            : $this->document->addLayer($layer->getName(), $layer->isVisibleByDefault());
        $resourceName = $this->resources->addProperty($group);

        $this->contents->addElement(new Raw("/OC /$resourceName BDC"));

        try {
            $renderer($this);
        } finally {
            $this->contents->addElement(new Raw('EMC'));
        }

        return $this;
    }

    public function addBadge(
        string $text,
        Position $position,
        string $baseFont = 'Helvetica',
        int $size = 11,
        ?BadgeStyle $style = null,
        ?LinkTarget $link = null,
    ): self {
        if ($text === '') {
            throw new InvalidArgumentException('Badge text must not be empty.');
        }

        if ($size <= 0) {
            throw new InvalidArgumentException('Badge font size must be greater than zero.');
        }

        $style ??= new BadgeStyle(
            fillColor: Color::gray(0.9),
        );

        $font = $this->resolveFont($baseFont);
        $textWidth = $font->measureTextWidth($text, $size);
        $badgeWidth = $textWidth + ($style->paddingHorizontal * 2);
        $badgeHeight = $size + ($style->paddingVertical * 2);

        $this->renderDecorativeContent(function () use ($position, $badgeWidth, $badgeHeight, $style): void {
            if ($style->cornerRadius > 0) {
                $this->addRoundedRectangle(
                    new Rect($position->x, $position->y, $badgeWidth, $badgeHeight),
                    $style->cornerRadius,
                    $style->borderWidth,
                    $style->borderColor,
                    $style->fillColor,
                    $style->opacity,
                );

                return;
            }

            $this->addRectangle(
                new Rect($position->x, $position->y, $badgeWidth, $badgeHeight),
                $style->borderWidth,
                $style->borderColor,
                $style->fillColor,
                $style->opacity,
            );
        });

        $this->addText(
            $text,
            new Position(
                $position->x + $style->paddingHorizontal,
                $position->y + $style->paddingVertical + ($size * 0.2),
            ),
            $baseFont,
            $size,
            new TextOptions(
                structureTag: $this->resolveComponentTextStructureTag(),
                color: $style->textColor,
                opacity: $style->opacity,
                link: $link,
            ),
        );

        return $this;
    }

    /**
     * @param string|list<TextSegment> $body
     */
    public function addPanel(
        string | array $body,
        float $x,
        float $y,
        float $width,
        float $height,
        ?string $title = null,
        string $bodyFont = 'Helvetica',
        ?PanelStyle $style = null,
        ?string $titleFont = null,
        ?LinkTarget $link = null,
    ): self {
        if ($width <= 0) {
            throw new InvalidArgumentException('Panel width must be greater than zero.');
        }

        if ($height <= 0) {
            throw new InvalidArgumentException('Panel height must be greater than zero.');
        }

        if ($title === null && $body === '') {
            throw new InvalidArgumentException('Panel requires a title or body.');
        }

        $style ??= new PanelStyle(
            fillColor: Color::gray(0.96),
            borderColor: Color::gray(0.75),
        );
        $titleFont ??= $bodyFont;
        $bindLinkToText = $this->shouldBindHighLevelComponentLinkToText($link);

        $this->renderDecorativeContent(function () use ($style, $x, $y, $width, $height): void {
            if ($style->cornerRadius > 0) {
                $this->addRoundedRectangle(
                    new Rect($x, $y, $width, $height),
                    $style->cornerRadius,
                    $style->borderWidth,
                    $style->borderColor,
                    $style->fillColor,
                    $style->opacity,
                );

                return;
            }

            $this->addRectangle(
                new Rect($x, $y, $width, $height),
                $style->borderWidth,
                $style->borderColor,
                $style->fillColor,
                $style->opacity,
            );
        });

        $contentWidth = $width - ($style->paddingHorizontal * 2);

        if ($contentWidth <= 0) {
            throw new InvalidArgumentException('Panel content width must be greater than zero.');
        }

        $bodyTopOffset = $style->paddingVertical;

        if ($title !== null && $title !== '') {
            $this->addText(
                $title,
                new Position(
                    $x + $style->paddingHorizontal,
                    $y + $height - $style->paddingVertical - $style->titleSize,
                ),
                $titleFont,
                $style->titleSize,
                new TextOptions(
                    structureTag: $this->resolveComponentTextStructureTag(),
                    color: $style->titleColor,
                    opacity: $style->opacity,
                    link: $bindLinkToText ? $link : null,
                ),
            );
            $bodyTopOffset += $style->titleSize + $style->titleSpacing;
        }

        if ($body !== '' && $body !== []) {
            $bodyLineHeight = $style->bodySize * self::DEFAULT_LINE_HEIGHT_FACTOR;
            $availableBodyHeight = $height - $bodyTopOffset - $style->paddingVertical;
            $maxLines = (int) floor($availableBodyHeight / $bodyLineHeight);

            if ($maxLines < 1) {
                throw new InvalidArgumentException('Panel height is too small for its content.');
            }

            $this->addTextBox(
                text: $this->bindLinkToTextContent($body, $link),
                box: new Rect(
                    $x + $style->paddingHorizontal,
                    $y + $style->paddingVertical,
                    $contentWidth,
                    $availableBodyHeight,
                ),
                fontName: $bodyFont,
                size: $style->bodySize,
                options: new TextBoxOptions(
                    structureTag: $this->resolveComponentTextStructureTag(),
                    lineHeight: $bodyLineHeight,
                    color: $style->bodyColor,
                    opacity: $style->opacity,
                    align: $style->bodyAlign,
                    maxLines: $maxLines,
                    overflow: TextOverflow::ELLIPSIS,
                ),
            );
        }

        if ($link !== null && !$bindLinkToText) {
            $this->addLinkTarget(new Rect($x, $y, $width, $height), $link);
        }

        return $this;
    }

    /**
     * @param string|list<TextSegment> $body
     */
    public function addCallout(
        string | array $body,
        float $x,
        float $y,
        float $width,
        float $height,
        float $pointerX,
        float $pointerY,
        ?string $title = null,
        string $bodyFont = 'Helvetica',
        ?CalloutStyle $style = null,
        ?string $titleFont = null,
        ?LinkTarget $link = null,
    ): self {
        $style ??= new CalloutStyle(
            panelStyle: new PanelStyle(
                fillColor: Color::gray(0.96),
                borderColor: Color::gray(0.75),
            ),
        );
        $panelStyle = $style->panelStyle ?? new PanelStyle(
            fillColor: Color::gray(0.96),
            borderColor: Color::gray(0.75),
        );

        $this->addPanel(
            $body,
            $x,
            $y,
            $width,
            $height,
            $title,
            $bodyFont,
            $panelStyle,
            $titleFont,
            $link,
        );

        $pointerStrokeWidth = $style->pointerStrokeWidth ?? $panelStyle->borderWidth;
        $pointerStrokeColor = $style->pointerStrokeColor ?? $panelStyle->borderColor;
        $pointerFillColor = $style->pointerFillColor ?? $panelStyle->fillColor;
        $pointerOpacity = $style->pointerOpacity ?? $panelStyle->opacity;
        $halfBaseWidth = $style->pointerBaseWidth / 2;

        if ($pointerY <= $y) {
            $baseCenterX = max($x + $halfBaseWidth, min($x + $width - $halfBaseWidth, $pointerX));
            $baseY = $y;
            $points = [
                [$baseCenterX - $halfBaseWidth, $baseY],
                [$baseCenterX + $halfBaseWidth, $baseY],
                [$pointerX, $pointerY],
            ];
        } elseif ($pointerY >= $y + $height) {
            $baseCenterX = max($x + $halfBaseWidth, min($x + $width - $halfBaseWidth, $pointerX));
            $baseY = $y + $height;
            $points = [
                [$baseCenterX - $halfBaseWidth, $baseY],
                [$pointerX, $pointerY],
                [$baseCenterX + $halfBaseWidth, $baseY],
            ];
        } elseif ($pointerX <= $x) {
            $baseCenterY = max($y + $halfBaseWidth, min($y + $height - $halfBaseWidth, $pointerY));
            $baseX = $x;
            $points = [
                [$baseX, $baseCenterY - $halfBaseWidth],
                [$baseX, $baseCenterY + $halfBaseWidth],
                [$pointerX, $pointerY],
            ];
        } else {
            $baseCenterY = max($y + $halfBaseWidth, min($y + $height - $halfBaseWidth, $pointerY));
            $baseX = $x + $width;
            $points = [
                [$baseX, $baseCenterY - $halfBaseWidth],
                [$pointerX, $pointerY],
                [$baseX, $baseCenterY + $halfBaseWidth],
            ];
        }

        $this->renderDecorativeContent(function () use ($points, $pointerStrokeWidth, $pointerStrokeColor, $pointerFillColor, $pointerOpacity): void {
            $this->addPolygon(
                $points,
                $pointerStrokeWidth,
                $pointerStrokeColor,
                $pointerFillColor,
                $pointerOpacity,
            );
        });

        return $this;
    }

    /**
     * @param string|list<TextSegment> $text
     */
    public function addFlowText(
        string | array $text,
        Position $position,
        float $maxWidth,
        string $fontName = 'Helvetica',
        int $size = 12,
        FlowTextOptions $options = new FlowTextOptions(),
    ): self {
        $lineHeight = $options->lineHeight ?? $size * self::DEFAULT_LINE_HEIGHT_FACTOR;
        $bottomMargin = $options->bottomMargin ?? self::DEFAULT_BOTTOM_MARGIN;

        if ($maxWidth <= 0) {
            throw new InvalidArgumentException('Paragraph width must be greater than zero.');
        }

        if ($lineHeight <= 0) {
            throw new InvalidArgumentException('Line height must be greater than zero.');
        }

        if ($options->maxLines !== null && $options->maxLines <= 0) {
            throw new InvalidArgumentException('Max lines must be greater than zero.');
        }

        $lines = $this->layoutParagraphLines(
            $text,
            $fontName,
            $size,
            $maxWidth,
            $options->color,
            $options->opacity,
            $options->maxLines,
            $options->overflow,
        );

        return $this->renderParagraphLines(
            $lines,
            $position->x,
            $position->y,
            $maxWidth,
            $fontName,
            $size,
            $options->structureTag,
            $options->parentStructElem,
            $lineHeight,
            $bottomMargin,
            $options->align,
        );
    }

    /**
     * @param string|list<TextSegment> $text
     */
    public function addTextBox(
        string | array $text,
        Rect $box,
        string $fontName = 'Helvetica',
        int $size = 12,
        TextBoxOptions $options = new TextBoxOptions(),
    ): self {
        $lineHeight = $options->lineHeight ?? $size * self::DEFAULT_LINE_HEIGHT_FACTOR;

        if ($box->width <= 0) {
            throw new InvalidArgumentException('Text box width must be greater than zero.');
        }

        if ($box->height <= 0) {
            throw new InvalidArgumentException('Text box height must be greater than zero.');
        }

        if ($lineHeight <= 0) {
            throw new InvalidArgumentException('Line height must be greater than zero.');
        }

        if ($options->maxLines !== null && $options->maxLines <= 0) {
            throw new InvalidArgumentException('Max lines must be greater than zero.');
        }

        if (
            $options->padding->top < 0
            || $options->padding->right < 0
            || $options->padding->bottom < 0
            || $options->padding->left < 0
        ) {
            throw new InvalidArgumentException('Text box padding must not be negative.');
        }

        $contentWidth = $box->width - $options->padding->left - $options->padding->right;

        if ($contentWidth <= 0) {
            throw new InvalidArgumentException('Text box content width must be greater than zero.');
        }

        $contentHeight = $box->height - $options->padding->top - $options->padding->bottom;

        if ($contentHeight < $size) {
            throw new InvalidArgumentException('Text box content height must be at least the font size.');
        }

        $visibleLineCapacity = max(1, (int) floor($contentHeight / $lineHeight));
        $maxLines = $options->maxLines === null
            ? $visibleLineCapacity
            : min($options->maxLines, $visibleLineCapacity);

        $lines = $this->layoutParagraphLines(
            $text,
            $fontName,
            $size,
            $contentWidth,
            $options->color,
            $options->opacity,
            $maxLines,
            $options->overflow,
        );

        $startY = $this->resolveTextBoxStartY(
            $box->y,
            $box->height,
            $size,
            $lineHeight,
            count($lines),
            $options->verticalAlign,
            $options->padding->top,
            $options->padding->bottom,
        );

        return $this->renderTextLines(
            $lines,
            $box->x + $options->padding->left,
            $startY,
            $contentWidth,
            $fontName,
            $size,
            $options->structureTag,
            $options->parentStructElem,
            $lineHeight,
            $options->align,
        );
    }

    /**
     * @param string|list<TextSegment> $text
     * @return list<array{segments: array<int, TextSegment>, justify: bool}>
     */
    public function layoutParagraphLines(
        string | array $text,
        string $baseFont,
        int $size,
        float $maxWidth,
        ?Color $color = null,
        ?Opacity $opacity = null,
        ?int $maxLines = null,
        TextOverflow $overflow = TextOverflow::CLIP,
    ): array {
        return $this->textLayoutEngine()->layoutParagraphLines(
            $text,
            $baseFont,
            $size,
            $maxWidth,
            $color,
            $opacity,
            $maxLines,
            $overflow,
        );
    }

    /**
     * @param list<array{segments: array<int, TextSegment>, justify: bool}> $lines
     */
    public function renderParagraphLines(
        array $lines,
        float $x,
        float $y,
        float $maxWidth,
        string $baseFont,
        int $size,
        ?StructureTag $tag = null,
        ?StructElem $parentStructElem = null,
        ?float $lineHeight = null,
        ?float $bottomMargin = null,
        HorizontalAlign $align = HorizontalAlign::LEFT,
    ): self {
        $lineHeight ??= $size * self::DEFAULT_LINE_HEIGHT_FACTOR;
        $bottomMargin ??= self::DEFAULT_BOTTOM_MARGIN;

        if ($maxWidth <= 0) {
            throw new InvalidArgumentException('Paragraph width must be greater than zero.');
        }

        if ($lineHeight <= 0) {
            throw new InvalidArgumentException('Line height must be greater than zero.');
        }

        $page = $this;
        $currentY = $y;
        $topMargin = $this->height - $y;

        foreach ($lines as $line) {
            if ($currentY < $bottomMargin) {
                $page = $this->document->addPage($this->width, $this->height);
                $currentY = $this->height - $topMargin;
            }

            $this->renderTextLine(
                $page,
                $line,
                $x,
                $currentY,
                $maxWidth,
                $baseFont,
                $size,
                $tag,
                $parentStructElem,
                $align,
            );
            $currentY -= $lineHeight;
        }

        return $page;
    }

    /**
     * @param list<array{segments: array<int, TextSegment>, justify: bool}> $lines
     */
    private function renderTextLines(
        array $lines,
        float $x,
        float $y,
        float $maxWidth,
        string $baseFont,
        int $size,
        ?StructureTag $tag,
        ?StructElem $parentStructElem,
        float $lineHeight,
        HorizontalAlign $align,
    ): self {
        $currentY = $y;

        foreach ($lines as $line) {
            $this->renderTextLine($this, $line, $x, $currentY, $maxWidth, $baseFont, $size, $tag, $parentStructElem, $align);
            $currentY -= $lineHeight;
        }

        return $this;
    }

    /**
     * @param array{segments: array<int, TextSegment>, justify: bool} $line
     */
    private function renderTextLine(
        self $page,
        array $line,
        float $x,
        float $y,
        float $maxWidth,
        string $baseFont,
        int $size,
        ?StructureTag $tag,
        ?StructElem $parentStructElem,
        HorizontalAlign $align,
    ): void {
        if ($line['segments'] === []) {
            return;
        }

        $cursorX = $x + $this->calculateAlignedOffset($line['segments'], $baseFont, $size, $maxWidth, $align);

        if ($align === HorizontalAlign::JUSTIFY && $line['justify']) {
            $this->renderJustifiedLine($page, $line['segments'], $cursorX, $y, $baseFont, $size, $tag, $maxWidth, $parentStructElem);

            return;
        }

        foreach ($line['segments'] as $segment) {
            $segmentFontName = $this->resolveStyledBaseFont($baseFont, $segment);
            $segmentFont = $this->resolveFont($segmentFontName);

            $page->addText(
                $segment->text,
                new Position($cursorX, $y),
                $segmentFontName,
                $size,
                new TextOptions(
                    structureTag: $tag,
                    parentStructElem: $parentStructElem,
                    color: $segment->color,
                    opacity: $segment->opacity,
                    underline: $segment->underline,
                    strikethrough: $segment->strikethrough,
                    link: $segment->link,
                ),
            );
            $cursorX += $segmentFont->measureTextWidth($segment->text, $size);
        }
    }

    public function createTextFrame(
        Position $position,
        float $width,
        float $bottomMargin = self::DEFAULT_BOTTOM_MARGIN,
    ): TextFrame {
        return new TextFrame($this, $position->x, $position->y, $width, $bottomMargin);
    }

    /**
     * @param list<float|int> $columnWidths
     */
    public function createTable(
        Position $position,
        float $width,
        array $columnWidths,
        float $bottomMargin = self::DEFAULT_BOTTOM_MARGIN,
    ): Table {
        return new Table($this, $position->x, $position->y, $width, $columnWidths, $bottomMargin);
    }

    public function addPath(): PathBuilder
    {
        return new PathBuilder($this);
    }

    public function addLine(
        Position $from,
        Position $to,
        float $width = 1.0,
        ?Color $color = null,
        ?Opacity $opacity = null,
    ): self {
        if ($width <= 0) {
            throw new InvalidArgumentException('Line width must be greater than zero.');
        }

        $colorOperator = $color?->renderStrokingOperator();
        $graphicsStateName = $this->resolveGraphicsStateName($opacity);

        $this->addGraphicElement(new Line(
            $from->x,
            $from->y,
            $to->x,
            $to->y,
            $width,
            $colorOperator,
            $graphicsStateName,
        ));

        return $this;
    }

    public function addRectangle(
        Rect $box,
        ?float $strokeWidth = 1.0,
        ?Color $strokeColor = null,
        ?Color $fillColor = null,
        ?Opacity $opacity = null,
    ): self {
        if ($box->width <= 0) {
            throw new InvalidArgumentException('Rectangle width must be greater than zero.');
        }

        if ($box->height <= 0) {
            throw new InvalidArgumentException('Rectangle height must be greater than zero.');
        }

        if ($strokeWidth !== null && $strokeWidth <= 0) {
            throw new InvalidArgumentException('Rectangle stroke width must be greater than zero.');
        }

        if ($strokeWidth === null && $fillColor === null) {
            throw new InvalidArgumentException('Rectangle requires either a stroke or a fill.');
        }

        $graphicsStateName = $this->resolveGraphicsStateName($opacity);

        $this->addGraphicElement(new Rectangle(
            $box->x,
            $box->y,
            $box->width,
            $box->height,
            $strokeWidth,
            $strokeColor?->renderStrokingOperator(),
            $fillColor?->renderNonStrokingOperator(),
            $graphicsStateName,
        ));

        return $this;
    }

    public function addRoundedRectangle(
        Rect $box,
        float $radius,
        ?float $strokeWidth = 1.0,
        ?Color $strokeColor = null,
        ?Color $fillColor = null,
        ?Opacity $opacity = null,
    ): self {
        if ($box->width <= 0) {
            throw new InvalidArgumentException('Rounded rectangle width must be greater than zero.');
        }

        if ($box->height <= 0) {
            throw new InvalidArgumentException('Rounded rectangle height must be greater than zero.');
        }

        if ($radius <= 0) {
            throw new InvalidArgumentException('Rounded rectangle radius must be greater than zero.');
        }

        if ($radius > ($box->width / 2) || $radius > ($box->height / 2)) {
            throw new InvalidArgumentException('Rounded rectangle radius must not exceed half the width or height.');
        }

        if ($strokeWidth !== null && $strokeWidth <= 0) {
            throw new InvalidArgumentException('Rounded rectangle stroke width must be greater than zero.');
        }

        if ($strokeWidth === null && $fillColor === null) {
            throw new InvalidArgumentException('Rounded rectangle requires either a stroke or a fill.');
        }

        $controlOffset = $radius * 0.5522847498307936;
        $left = $box->x;
        $right = $box->x + $box->width;
        $bottom = $box->y;
        $top = $box->y + $box->height;

        $path = $this->addPath()
            ->moveTo($left + $radius, $top)
            ->lineTo($right - $radius, $top)
            ->curveTo(
                $right - $radius + $controlOffset,
                $top,
                $right,
                $top - $radius + $controlOffset,
                $right,
                $top - $radius,
            )
            ->lineTo($right, $bottom + $radius)
            ->curveTo(
                $right,
                $bottom + $radius - $controlOffset,
                $right - $radius + $controlOffset,
                $bottom,
                $right - $radius,
                $bottom,
            )
            ->lineTo($left + $radius, $bottom)
            ->curveTo(
                $left + $radius - $controlOffset,
                $bottom,
                $left,
                $bottom + $radius - $controlOffset,
                $left,
                $bottom + $radius,
            )
            ->lineTo($left, $top - $radius)
            ->curveTo(
                $left,
                $top - $radius + $controlOffset,
                $left + $radius - $controlOffset,
                $top,
                $left + $radius,
                $top,
            )
            ->close();

        return $this->finishClosedPath($path, $strokeWidth, $strokeColor, $fillColor, $opacity);
    }

    public function addCircle(
        float $centerX,
        float $centerY,
        float $radius,
        ?float $strokeWidth = 1.0,
        ?Color $strokeColor = null,
        ?Color $fillColor = null,
        ?Opacity $opacity = null,
    ): self {
        if ($radius <= 0) {
            throw new InvalidArgumentException('Circle radius must be greater than zero.');
        }

        if ($strokeWidth !== null && $strokeWidth <= 0) {
            throw new InvalidArgumentException('Circle stroke width must be greater than zero.');
        }

        if ($strokeWidth === null && $fillColor === null) {
            throw new InvalidArgumentException('Circle requires either a stroke or a fill.');
        }

        return $this->finishClosedPath(
            $this->buildEllipsePath($centerX, $centerY, $radius, $radius),
            $strokeWidth,
            $strokeColor,
            $fillColor,
            $opacity,
        );
    }

    public function addEllipse(
        float $centerX,
        float $centerY,
        float $radiusX,
        float $radiusY,
        ?float $strokeWidth = 1.0,
        ?Color $strokeColor = null,
        ?Color $fillColor = null,
        ?Opacity $opacity = null,
    ): self {
        if ($radiusX <= 0) {
            throw new InvalidArgumentException('Ellipse radiusX must be greater than zero.');
        }

        if ($radiusY <= 0) {
            throw new InvalidArgumentException('Ellipse radiusY must be greater than zero.');
        }

        if ($strokeWidth !== null && $strokeWidth <= 0) {
            throw new InvalidArgumentException('Ellipse stroke width must be greater than zero.');
        }

        if ($strokeWidth === null && $fillColor === null) {
            throw new InvalidArgumentException('Ellipse requires either a stroke or a fill.');
        }

        return $this->finishClosedPath(
            $this->buildEllipsePath($centerX, $centerY, $radiusX, $radiusY),
            $strokeWidth,
            $strokeColor,
            $fillColor,
            $opacity,
        );
    }

    /**
     * @param list<array{0: float|int, 1: float|int}> $points
     */
    public function addPolygon(
        array $points,
        ?float $strokeWidth = 1.0,
        ?Color $strokeColor = null,
        ?Color $fillColor = null,
        ?Opacity $opacity = null,
    ): self {
        if (count($points) < 3) {
            throw new InvalidArgumentException('Polygon requires at least three points.');
        }

        if ($strokeWidth !== null && $strokeWidth <= 0) {
            throw new InvalidArgumentException('Polygon stroke width must be greater than zero.');
        }

        if ($strokeWidth === null && $fillColor === null) {
            throw new InvalidArgumentException('Polygon requires either a stroke or a fill.');
        }

        $path = $this->addPath()->moveTo((float) $points[0][0], (float) $points[0][1]);

        foreach (array_slice($points, 1) as $point) {
            $path->lineTo((float) $point[0], (float) $point[1]);
        }

        $path->close();

        return $this->finishClosedPath($path, $strokeWidth, $strokeColor, $fillColor, $opacity);
    }

    public function addArrow(
        Position $from,
        Position $to,
        float $strokeWidth = 1.0,
        ?Color $color = null,
        ?Opacity $opacity = null,
        float $headLength = 10.0,
        float $headWidth = 8.0,
    ): self {
        if ($strokeWidth <= 0) {
            throw new InvalidArgumentException('Arrow stroke width must be greater than zero.');
        }

        if ($headLength <= 0) {
            throw new InvalidArgumentException('Arrow head length must be greater than zero.');
        }

        if ($headWidth <= 0) {
            throw new InvalidArgumentException('Arrow head width must be greater than zero.');
        }

        $dx = $to->x - $from->x;
        $dy = $to->y - $from->y;
        $length = hypot($dx, $dy);

        if ($length <= 0.0) {
            throw new InvalidArgumentException('Arrow requires distinct start and end points.');
        }

        $usableHeadLength = min($headLength, $length);
        $ux = $dx / $length;
        $uy = $dy / $length;
        $baseX = $to->x - ($ux * $usableHeadLength);
        $baseY = $to->y - ($uy * $usableHeadLength);
        $perpX = -$uy;
        $perpY = $ux;
        $halfHeadWidth = $headWidth / 2;
        $leftX = $baseX + ($perpX * $halfHeadWidth);
        $leftY = $baseY + ($perpY * $halfHeadWidth);
        $rightX = $baseX - ($perpX * $halfHeadWidth);
        $rightY = $baseY - ($perpY * $halfHeadWidth);

        $this->addLine($from, new Position($baseX, $baseY), $strokeWidth, $color, $opacity);
        $this->addPolygon(
            [
                [$to->x, $to->y],
                [$leftX, $leftY],
                [$rightX, $rightY],
            ],
            null,
            null,
            $color,
            $opacity,
        );

        return $this;
    }

    public function addStar(
        float $centerX,
        float $centerY,
        int $points,
        float $outerRadius,
        float $innerRadius,
        ?float $strokeWidth = 1.0,
        ?Color $strokeColor = null,
        ?Color $fillColor = null,
        ?Opacity $opacity = null,
    ): self {
        if ($points < 3) {
            throw new InvalidArgumentException('Star requires at least three points.');
        }

        if ($outerRadius <= 0) {
            throw new InvalidArgumentException('Star outer radius must be greater than zero.');
        }

        if ($innerRadius <= 0) {
            throw new InvalidArgumentException('Star inner radius must be greater than zero.');
        }

        if ($innerRadius >= $outerRadius) {
            throw new InvalidArgumentException('Star inner radius must be smaller than the outer radius.');
        }

        $starPoints = [];
        $step = M_PI / $points;
        $startAngle = -M_PI / 2;

        for ($index = 0; $index < $points * 2; $index++) {
            $radius = $index % 2 === 0 ? $outerRadius : $innerRadius;
            $angle = $startAngle + ($index * $step);
            $starPoints[] = [
                $centerX + (cos($angle) * $radius),
                $centerY + (sin($angle) * $radius),
            ];
        }

        return $this->addPolygon($starPoints, $strokeWidth, $strokeColor, $fillColor, $opacity);
    }

    public function addLink(
        Rect $box,
        string $url,
        ?string $accessibleName = null,
    ): self {
        if (str_starts_with($url, '#')) {
            return $this->addInternalLink($box, substr($url, 1), $accessibleName);
        }

        if ($url === '') {
            throw new InvalidArgumentException('Link URL must not be empty.');
        }

        return $this->addLinkTarget($box, LinkTarget::externalUrl($url), alternativeDescription: $accessibleName);
    }

    public function addInternalLink(
        Rect $box,
        string $destination,
        ?string $accessibleName = null,
    ): self {
        if ($destination === '') {
            throw new InvalidArgumentException('Link destination must not be empty.');
        }

        return $this->addLinkTarget($box, LinkTarget::namedDestination($destination), alternativeDescription: $accessibleName);
    }

    private function addLinkTarget(
        Rect $box,
        LinkTarget $target,
        ?StructElem $linkStructElem = null,
        ?string $alternativeDescription = null,
    ): self {
        $profile = $this->document->getProfile();

        if ($profile->requiresTaggedLinkAnnotations() && $linkStructElem === null) {
            if ($alternativeDescription === null || $alternativeDescription === '') {
                throw new InvalidArgumentException(sprintf(
                    'Profile %s requires an accessible name for standalone link annotations.',
                    $profile->name(),
                ));
            }

            $linkStructElem = $this->document->createStructElem(StructureTag::Link);
            $linkStructElem->setPage($this);
        }

        $this->pageAnnotations()->addLinkAnnotation($box, $target, $linkStructElem, $alternativeDescription);

        return $this;
    }

    /**
     * @param string|list<TextSegment> $text
     * @return string|list<TextSegment>
     */
    private function bindLinkToTextContent(string | array $text, ?LinkTarget $link): string | array
    {
        if ($link === null || !$this->shouldBindHighLevelComponentLinkToText($link)) {
            return $text;
        }

        if (is_string($text)) {
            return [new TextSegment($text, link: $link)];
        }

        return array_map(
            static fn (TextSegment $segment): TextSegment => $segment->link !== null
                ? $segment
                : new TextSegment(
                    $segment->text,
                    $segment->color,
                    $segment->opacity,
                    $link,
                    $segment->bold,
                    $segment->italic,
                    $segment->underline,
                    $segment->strikethrough,
                ),
            $text,
        );
    }

    private function shouldBindHighLevelComponentLinkToText(?LinkTarget $link): bool
    {
        return $link !== null
            && $this->document->getProfile()->requiresTaggedLinkAnnotations();
    }

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
        PageAnnotation & IndirectObject $parent,
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

    public function addImage(
        Image $image,
        Position $position,
        ?float $width = null,
        ?float $height = null,
        ImageOptions $options = new ImageOptions(),
    ): self {
        if ($width !== null && $width <= 0) {
            throw new InvalidArgumentException('Image width must be greater than zero.');
        }

        if ($height !== null && $height <= 0) {
            throw new InvalidArgumentException('Image height must be greater than zero.');
        }

        if ($options->structureTag !== null) {
            $this->document->ensureStructureEnabled();
        }

        $width ??= $image->getWidth();
        $height ??= $image->getHeight();

        if ($width <= 0 || $height <= 0) {
            throw new InvalidArgumentException('Image dimensions must be greater than zero.');
        }

        if ($image->getSoftMask() !== null) {
            $this->document->assertAllowsTransparency();
        }

        $imageObject = $this->createImageObject($image);
        $resourceName = $this->resources->addImage($imageObject);
        $artifactContext = $options->structureTag === null && $this->document->isRenderingArtifactContext();
        $this->assertAllowsImageAccessibility($options, $artifactContext);
        $artifactTag = $artifactContext ? 'Artifact' : null;
        $contentTag = $options->structureTag !== null
            ? $options->structureTag->value
            : $artifactTag;
        $markedContentId = $options->structureTag !== null ? $this->markedContentId++ : null;

        $this->contents->addElement(new DrawImage(
            $resourceName,
            $position->x,
            $position->y,
            $width,
            $height,
            $markedContentId,
            $contentTag,
        ));

        if ($options->structureTag !== null && $markedContentId !== null) {
            $structElem = $this->document->createStructElem(
                $options->structureTag,
                $markedContentId,
                $this,
                $options->parentStructElem,
            );

            if ($options->altText !== null && $options->altText !== '') {
                $structElem->setAltText($options->altText);
            }
        }

        return $this;
    }

    private function assertAllowsImageAccessibility(ImageOptions $options, bool $artifactContext): void
    {
        $profile = $this->document->getProfile();

        if (!$profile->requiresTaggedImages()) {
            return;
        }

        if ($artifactContext) {
            return;
        }

        if ($options->structureTag !== StructureTag::Figure) {
            throw new InvalidArgumentException(sprintf(
                'Profile %s requires images to be tagged as Figure or rendered as artifacts in the current implementation.',
                $profile->name(),
            ));
        }

        if (!$profile->requiresFigureAltText()) {
            return;
        }

        if ($options->altText !== null && $options->altText !== '') {
            return;
        }

        throw new InvalidArgumentException(sprintf(
            'Profile %s requires alt text for Figure images in the current implementation.',
            $profile->name(),
        ));
    }

    public function addTextField(
        string $name,
        Rect $box,
        ?string $value = null,
        string $baseFont = 'Helvetica',
        int $size = 12,
        bool $multiline = false,
        ?Color $textColor = null,
        ?FormFieldFlags $flags = null,
        ?string $defaultValue = null,
        ?string $accessibleName = null,
        ?FormFieldLabel $fieldLabel = null,
    ): self {
        $resolvedAccessibleName = $this->resolveFormFieldAccessibleName($name, $accessibleName, $fieldLabel);
        $acroForm = $this->document->ensureTextFieldAcroForm();
        $annotation = $this->formWidgetFactory()->createTextField(
            $name,
            $box,
            $value,
            $baseFont,
            $size,
            $multiline,
            $textColor,
            $flags,
            $defaultValue,
            $resolvedAccessibleName,
        );

        $formStructElem = $this->bindAccessibleFormField($annotation, $resolvedAccessibleName, $fieldLabel !== null);
        $this->renderFormFieldLabel($fieldLabel, $formStructElem);
        $acroForm->addField($annotation);
        $this->pageAnnotations()->add($annotation);

        return $this;
    }

    public function addCheckbox(
        string $name,
        Position $position,
        float $size,
        bool $checked = false,
        ?string $accessibleName = null,
        ?FormFieldLabel $fieldLabel = null,
    ): self {
        $resolvedAccessibleName = $this->resolveFormFieldAccessibleName($name, $accessibleName, $fieldLabel);
        $acroForm = $this->document->ensureCheckboxAcroForm();
        $annotation = $this->formWidgetFactory()->createCheckbox($name, $position, $size, $checked, $resolvedAccessibleName);

        $formStructElem = $this->bindAccessibleFormField($annotation, $resolvedAccessibleName, $fieldLabel !== null);
        $this->renderFormFieldLabel($fieldLabel, $formStructElem);
        $acroForm->addField($annotation);
        $this->pageAnnotations()->add($annotation);

        return $this;
    }

    public function addRadioButton(
        string $name,
        string $value,
        Position $position,
        float $size,
        bool $checked = false,
        ?string $accessibleName = null,
        ?FormFieldLabel $fieldLabel = null,
    ): self {
        $resolvedAccessibleName = $this->resolveRadioButtonAccessibleName($value, $accessibleName, $fieldLabel);
        [$group, $annotation] = $this->formWidgetFactory()->createRadioButton($name, $value, $position, $size, $checked, $resolvedAccessibleName);
        $groupAccessibleName = $this->resolveRadioButtonGroupAccessibleName($name);

        if ($groupAccessibleName !== null) {
            $group->withTooltip($groupAccessibleName);
        }

        $group->addWidget($annotation, $value, $checked);
        $formStructElem = $this->bindAccessibleFormField($annotation, $resolvedAccessibleName, $fieldLabel !== null);
        $this->renderFormFieldLabel($fieldLabel, $formStructElem);
        $this->pageAnnotations()->add($annotation);

        return $this;
    }

    /**
     * @param array<string, string> $options
     */
    public function addComboBox(
        string $name,
        Rect $box,
        array $options,
        ?string $value = null,
        string $baseFont = 'Helvetica',
        int $size = 12,
        ?Color $textColor = null,
        ?FormFieldFlags $flags = null,
        ?string $defaultValue = null,
        ?string $accessibleName = null,
        ?FormFieldLabel $fieldLabel = null,
    ): self {
        $resolvedAccessibleName = $this->resolveFormFieldAccessibleName($name, $accessibleName, $fieldLabel);
        $acroForm = $this->document->ensureComboBoxAcroForm();
        $annotation = $this->formWidgetFactory()->createComboBox(
            $name,
            $box,
            $options,
            $value,
            $baseFont,
            $size,
            $textColor,
            $flags,
            $defaultValue,
            $resolvedAccessibleName,
        );

        $formStructElem = $this->bindAccessibleFormField($annotation, $resolvedAccessibleName, $fieldLabel !== null);
        $this->renderFormFieldLabel($fieldLabel, $formStructElem);
        $acroForm->addField($annotation);
        $this->pageAnnotations()->add($annotation);

        return $this;
    }

    /**
     * @param array<string, string> $options
     * @param list<string>|string|null $value
     * @param list<string>|string|null $defaultValue
     */
    public function addListBox(
        string $name,
        Rect $box,
        array $options,
        string | array | null $value = null,
        string $baseFont = 'Helvetica',
        int $size = 12,
        ?Color $textColor = null,
        ?FormFieldFlags $flags = null,
        string | array | null $defaultValue = null,
        ?string $accessibleName = null,
        ?FormFieldLabel $fieldLabel = null,
    ): self {
        $resolvedAccessibleName = $this->resolveFormFieldAccessibleName($name, $accessibleName, $fieldLabel);
        $acroForm = $this->document->ensureListBoxAcroForm();
        $annotation = $this->formWidgetFactory()->createListBox(
            $name,
            $box,
            $options,
            $value,
            $baseFont,
            $size,
            $textColor,
            $flags,
            $defaultValue,
            $resolvedAccessibleName,
        );

        $formStructElem = $this->bindAccessibleFormField($annotation, $resolvedAccessibleName, $fieldLabel !== null);
        $this->renderFormFieldLabel($fieldLabel, $formStructElem);
        $acroForm->addField($annotation);
        $this->pageAnnotations()->add($annotation);

        return $this;
    }

    public function addSignatureField(
        string $name,
        Rect $box,
        ?string $accessibleName = null,
        ?FormFieldLabel $fieldLabel = null,
    ): self {
        $resolvedAccessibleName = $this->resolveFormFieldAccessibleName($name, $accessibleName, $fieldLabel);
        $acroForm = $this->document->ensureSignatureFieldAcroForm();
        $annotation = $this->formWidgetFactory()->createSignatureField($name, $box, $resolvedAccessibleName);

        $formStructElem = $this->bindAccessibleFormField($annotation, $resolvedAccessibleName, $fieldLabel !== null);
        $this->renderFormFieldLabel($fieldLabel, $formStructElem);
        $acroForm->addField($annotation);
        $this->pageAnnotations()->add($annotation);

        return $this;
    }

    public function addPushButton(
        string $name,
        string $label,
        Rect $box,
        string $baseFont = 'Helvetica',
        int $size = 12,
        ?Color $textColor = null,
        ?ButtonAction $action = null,
        ?string $accessibleName = null,
        ?FormFieldLabel $fieldLabel = null,
    ): self {
        if ($action instanceof SetOcgStateAction) {
            $this->document->assertAllowsOptionalContentGroups();
        }

        $resolvedAccessibleName = $this->resolvePushButtonAccessibleName($name, $label, $accessibleName, $fieldLabel);
        $acroForm = $this->document->ensurePushButtonAcroForm();
        $annotation = $this->formWidgetFactory()->createPushButton(
            $name,
            $label,
            $box,
            $baseFont,
            $size,
            $textColor,
            $action,
            $resolvedAccessibleName,
        );

        $formStructElem = $this->bindAccessibleFormField($annotation, $resolvedAccessibleName, $fieldLabel !== null);
        $this->renderFormFieldLabel($fieldLabel, $formStructElem);
        $acroForm->addField($annotation);
        $this->pageAnnotations()->add($annotation);

        return $this;
    }

    public function render(): string
    {
        $dictionary = new DictionaryType([
            'Type' => new NameType('Page'),
            'Parent' => new ReferenceType($this->document->pages),
            'MediaBox' => new ArrayType([0, 0, $this->width, $this->height]),
            'Resources' => new ReferenceType($this->resources),
            'Contents' => new ReferenceType($this->contents),
        ]);

        if ($this->markedContentId > 0 && $this->document->hasStructure()) {
            $dictionary->add('StructParents', $this->structParentId);
        }

        $annotations = $this->getAnnotations();

        if ($annotations !== []) {
            $dictionary->add(
                'Annots',
                new ArrayType(array_map(
                    static fn (IndirectObject $annotation): ReferenceType => new ReferenceType($annotation),
                    $annotations,
                )),
            );

            if ($this->document->getProfile()->requiresPageAnnotationTabOrder()) {
                $dictionary->add('Tabs', new NameType('S'));
            }
        }

        return $this->id . ' 0 obj' . PHP_EOL
            . $dictionary->render() . PHP_EOL
            . 'endobj' . PHP_EOL;
    }

    public function getWidth(): float
    {
        return $this->width;
    }

    public function getHeight(): float
    {
        return $this->height;
    }

    public function getDocument(): Document
    {
        return $this->document;
    }

    /**
     * @return list<IndirectObject&PageAnnotation>
     */
    public function getAnnotations(): array
    {
        return $this->pageAnnotations?->all() ?? [];
    }

    private function resolveFont(string $baseFont): FontDefinition
    {
        foreach ($this->document->getFonts() as $registeredFont) {
            if ($registeredFont->getBaseFont() === $baseFont) {
                return $registeredFont;
            }
        }

        if ($this->document->getProfile()->requiresEmbeddedUnicodeFonts()) {
            throw new InvalidArgumentException(sprintf(
                "Profile %s requires embedded Unicode fonts in the current implementation. Font '%s' is not registered.",
                $this->document->getProfile()->name(),
                $baseFont,
            ));
        }

        throw new InvalidArgumentException("Font '$baseFont' is not registered.");
    }

    private function encodeText(FontDefinition $font, string $baseFont, string $text): string
    {
        if (!$font->supportsText($text)) {
            throw new InvalidArgumentException("Font '$baseFont' does not support the provided text.");
        }

        return $font->encodeText($text);
    }

    private function resolveMarkedContentStructureTag(TextOptions $options): ?StructureTag
    {
        $profile = $this->document->getProfile();

        if ($options->link === null || !$profile->requiresTaggedLinkAnnotations()) {
            return $options->structureTag;
        }

        return StructureTag::Link;
    }

    private function attachTextToStructure(TextOptions $options, StructureTag $tag, int $markedContentId, string $text): StructElem
    {
        $profile = $this->document->getProfile();

        if ($options->link === null || !$profile->requiresTaggedLinkAnnotations()) {
            if ($options->parentStructElem !== null && $options->parentStructElem->tag() === $tag->value) {
                $options->parentStructElem->setMarkedContent($markedContentId, $this);
                $this->document->registerMarkedContentStructElem($this->structParentId, $options->parentStructElem);

                return $options->parentStructElem;
            }

            return $this->document->createStructElem($tag, $markedContentId, $this, $options->parentStructElem);
        }

        if ($options->structureTag === null || $options->structureTag === StructureTag::Link) {
            $linkStructElem = $this->document->createStructElem(StructureTag::Link, $markedContentId, $this, $options->parentStructElem);

            return $this->applyLinkAlternativeDescription($linkStructElem, $text);
        }

        $containerStructElem = $this->document->createStructElem($options->structureTag, parent: $options->parentStructElem);
        $linkStructElem = $this->document->createStructElem(StructureTag::Link, $markedContentId, $this, $containerStructElem);

        return $this->applyLinkAlternativeDescription($linkStructElem, $text);
    }

    private function resolveLinkAlternativeDescription(string $text): ?string
    {
        if (!$this->document->getProfile()->requiresLinkAnnotationAlternativeDescriptions()) {
            return null;
        }

        return $text !== '' ? $text : null;
    }

    private function applyLinkAlternativeDescription(StructElem $linkStructElem, string $text): StructElem
    {
        $alternativeDescription = $this->resolveLinkAlternativeDescription($text);

        if ($alternativeDescription !== null) {
            $linkStructElem->setAltText($alternativeDescription);
        }

        return $linkStructElem;
    }

    private function registerFontResource(FontDefinition $font): string
    {
        return $this->resources->addFont($font);
    }

    private function updateUnicodeFontWidths(FontDefinition $font): void
    {
        if (
            !$font instanceof UnicodeFont
            || $font->descendantFont->cidToGidMap === null
            || $font->descendantFont->fontDescriptor === null
        ) {
            return;
        }

        $fontParser = new OpenTypeFontParser($font->descendantFont->fontDescriptor->fontFile->data);
        $widths = [];

        foreach ($font->getCodePointMap() as $cid => $codePointHex) {
            $utf16 = hex2bin($codePointHex);
            /** @var string $utf16 */
            $character = mb_convert_encoding($utf16, 'UTF-8', 'UTF-16BE');
            $glyphId = $fontParser->getGlyphIdForCharacter($character);
            $widths[$cid] = $fontParser->getAdvanceWidthForGlyphId($glyphId);
        }

        $font->descendantFont->setWidths($widths);
    }

    /**
     * @return list<string>
     */
    /**
     * @param string|list<TextSegment> $text
     */
    public function countParagraphLines(
        string | array $text,
        string $baseFont,
        int $size,
        float $maxWidth,
        ?int $maxLines = null,
        TextOverflow $overflow = TextOverflow::CLIP,
    ): int {
        return $this->textLayoutEngine()->countParagraphLines($text, $baseFont, $size, $maxWidth, $maxLines, $overflow);
    }

    public function measureTextWidth(string $text, string $baseFont, int $size): float
    {
        if ($size <= 0) {
            throw new InvalidArgumentException('Text size must be greater than zero.');
        }

        return $this->resolveFont($baseFont)->measureTextWidth($text, $size);
    }

    /**
     * @param array<int, TextSegment> $line
     */
    private function calculateAlignedOffset(
        array $line,
        string $baseFont,
        int $size,
        float $maxWidth,
        HorizontalAlign $align,
    ): float {
        if ($align === HorizontalAlign::LEFT || $align === HorizontalAlign::JUSTIFY) {
            return 0.0;
        }

        $line = $this->textLayoutEngine()->trimTrailingWhitespaceFromLine($line);

        $lineWidth = 0.0;

        foreach ($line as $segment) {
            $segmentFontName = $this->resolveStyledBaseFont($baseFont, $segment);
            $segmentFont = $this->resolveFont($segmentFontName);
            $lineWidth += $segmentFont->measureTextWidth($segment->text, $size);
        }

        $remainingWidth = max(0.0, $maxWidth - $lineWidth);

        if ($align === HorizontalAlign::CENTER) {
            return $remainingWidth / 2;
        }

        return $remainingWidth;
    }

    /**
     * @param array<int, TextSegment> $line
     */
    private function calculateJustifiedWordSpacing(
        array $line,
        string $baseFont,
        int $size,
        float $maxWidth,
    ): float {
        $lineWidth = 0.0;
        $spaceCount = 0;
        $pieces = $this->splitSegmentsIntoWordPieces($line);

        foreach ($line as $segment) {
            $segmentFontName = $this->resolveStyledBaseFont($baseFont, $segment);
            $segmentFont = $this->resolveFont($segmentFontName);
            $lineWidth += $segmentFont->measureTextWidth($segment->text, $size);
        }

        foreach ($pieces as $index => $piece) {
            if ($index === 0) {
                continue;
            }

            $spaceCount += $piece['leadingSpaces'];
        }

        if ($spaceCount <= 0) {
            return 0.0;
        }

        return max(0.0, $maxWidth - $lineWidth) / $spaceCount;
    }

    /**
     * @param array<int, TextSegment> $line
     */
    private function renderJustifiedLine(
        self $page,
        array $line,
        float $x,
        float $y,
        string $baseFont,
        int $size,
        ?StructureTag $tag,
        float $maxWidth,
        ?StructElem $parentStructElem,
    ): void {
        $pieces = $this->splitSegmentsIntoWordPieces($line);
        $extraWordSpacing = $this->calculateJustifiedWordSpacing($line, $baseFont, $size, $maxWidth);
        $cursorX = $x;
        $isFirstWord = true;

        foreach ($pieces as $piece) {
            $segment = $piece['segment'];
            $segmentFontName = $this->resolveStyledBaseFont($baseFont, $segment);
            $segmentFont = $this->resolveFont($segmentFontName);

            if (!$isFirstWord) {
                $spaceWidth = $segmentFont->measureTextWidth(str_repeat(' ', $piece['leadingSpaces']), $size);
                $cursorX += $spaceWidth + ($extraWordSpacing * $piece['leadingSpaces']);
            }

            $page->addText(
                $segment->text,
                new Position($cursorX, $y),
                $segmentFontName,
                $size,
                new TextOptions(
                    structureTag: $tag,
                    parentStructElem: $parentStructElem,
                    color: $segment->color,
                    opacity: $segment->opacity,
                    underline: $segment->underline,
                    strikethrough: $segment->strikethrough,
                    link: $segment->link,
                ),
            );

            $cursorX += $segmentFont->measureTextWidth($segment->text, $size);
            $isFirstWord = false;
        }
    }

    private function resolveTextBoxStartY(
        float $y,
        float $height,
        int $size,
        float $lineHeight,
        int $lineCount,
        VerticalAlign $verticalAlign,
        float $paddingTop,
        float $paddingBottom,
    ): float {
        $availableHeight = $height - $paddingTop - $paddingBottom;
        $lineOffset = max(0, $lineCount - 1) * $lineHeight;
        $blockHeight = $size + $lineOffset;

        return match ($verticalAlign) {
            VerticalAlign::TOP => $y + $paddingBottom + $availableHeight - $size,
            VerticalAlign::MIDDLE => $y + $paddingBottom + (($availableHeight - $blockHeight) / 2) + $lineOffset,
            VerticalAlign::BOTTOM => $y + $paddingBottom + $lineOffset,
        };
    }

    /**
     * @param array<int, TextSegment> $segments
     * @return list<array{segment: TextSegment, leadingSpaces: int}>
     */
    private function splitSegmentsIntoWordPieces(array $segments): array
    {
        $pieces = [];

        foreach ($segments as $segment) {
            $leadingSpaces = 0;

            foreach (preg_split('/( +)/', $segment->text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [] as $part) {
                if (trim($part) === '') {
                    $leadingSpaces += strlen($part);
                    continue;
                }

                $pieces[] = [
                    'segment' => new TextSegment(
                        $part,
                        $segment->color,
                        $segment->opacity,
                        $segment->link,
                        $segment->bold,
                        $segment->italic,
                        $segment->underline,
                        $segment->strikethrough,
                    ),
                    'leadingSpaces' => $leadingSpaces,
                ];

                $leadingSpaces = 0;
            }
        }

        return $pieces;
    }

    /**
     * @return array{0: float, 1: float}
     */
    private function resolveDecorationInsets(FontDefinition $font, string $text, int $size): array
    {
        if ($text === '') {
            return [0.0, 0.0];
        }

        $leadingSpaces = strspn($text, ' ');
        $trailingSpaces = strlen($text) - strlen(rtrim($text, ' '));

        $leadingInset = $leadingSpaces > 0
            ? $font->measureTextWidth(str_repeat(' ', $leadingSpaces), $size)
            : 0.0;

        $trailingInset = $trailingSpaces > 0
            ? $font->measureTextWidth(str_repeat(' ', $trailingSpaces), $size)
            : 0.0;

        return [$leadingInset, $trailingInset];
    }

    /**
     * Lazily builds the internal text layout helper.
     */
    private function textLayoutEngine(): TextLayoutEngine
    {
        return $this->textLayoutEngine ??= new TextLayoutEngine(
            fn (string $baseFont): FontDefinition => $this->resolveFont($baseFont),
            fn (string $baseFont, TextSegment $segment): string => $this->resolveStyledBaseFont($baseFont, $segment),
        );
    }

    private function pageAnnotations(): PageAnnotations
    {
        return $this->pageAnnotations ??= new PageAnnotations(
            $this,
            fn (string $baseFont): FontDefinition => $this->resolveFont($baseFont),
            fn (FontDefinition $font): string => $this->registerFontResource($font),
        );
    }

    /**
     * Lazily builds the internal form widget factory.
     */
    private function formWidgetFactory(): FormWidgetFactory
    {
        return $this->formWidgetFactory ??= new FormWidgetFactory(
            $this,
            fn (): int => $this->document->getUniqObjectId(),
            fn (): AcroForm => $this->document->ensureTextFieldAcroForm(),
            fn (): AcroForm => $this->document->ensurePushButtonAcroForm(),
            fn (): AcroForm => $this->document->ensureRadioButtonAcroForm(),
            fn (): AcroForm => $this->document->ensureComboBoxAcroForm(),
            fn (): AcroForm => $this->document->ensureListBoxAcroForm(),
            fn (string $baseFont): FontDefinition => $this->resolveFont($baseFont),
        );
    }

    private function bindAccessibleFormField(
        Annotation\TextFieldAnnotation | Annotation\CheckboxAnnotation | Annotation\PushButtonAnnotation | Annotation\RadioButtonWidgetAnnotation | Annotation\ComboBoxAnnotation | Annotation\ListBoxAnnotation | Annotation\SignatureFieldAnnotation $annotation,
        ?string $accessibleName,
        bool $hasVisibleLabel = false,
    ): ?StructElem {
        $profile = $this->document->getProfile();

        if (!$profile->requiresTaggedFormFields()) {
            return null;
        }

        $labelParentStructElem = null;

        if ($hasVisibleLabel) {
            $labelParentStructElem = $this->document->createStructElem(StructureTag::Division);
            $labelParentStructElem->setPage($this);
        }

        $formStructElem = $this->document->createStructElem(
            StructureTag::Form,
            parent: $labelParentStructElem,
        );
        $formStructElem->setPage($this);

        $structParentId = $this->document->getNextStructParentId();
        $annotation->withStructParent($structParentId);
        $formStructElem->addObjectReference($annotation, $this);

        if ($profile->requiresFormFieldAlternativeDescriptions() && $accessibleName !== null && $accessibleName !== '') {
            $formStructElem->setAltText($accessibleName);
        }

        $this->document->registerObjectStructElem($structParentId, $formStructElem);

        return $labelParentStructElem;
    }

    private function renderFormFieldLabel(?FormFieldLabel $fieldLabel, ?StructElem $formStructElem): void
    {
        if ($fieldLabel === null) {
            return;
        }

        $this->addText(
            $fieldLabel->text,
            $fieldLabel->position,
            $fieldLabel->fontName,
            $fieldLabel->size,
            new TextOptions(
                structureTag: $formStructElem !== null ? StructureTag::Paragraph : null,
                parentStructElem: $formStructElem,
                color: $fieldLabel->color,
            ),
        );
    }

    private function resolveFormFieldAccessibleName(string $name, ?string $accessibleName, ?FormFieldLabel $fieldLabel = null): ?string
    {
        if ($accessibleName !== null && $accessibleName !== '') {
            return $accessibleName;
        }

        if ($this->document->getProfile()->requiresFormFieldAlternativeDescriptions()) {
            return $fieldLabel !== null ? $fieldLabel->text : $name;
        }

        return null;
    }

    private function resolvePushButtonAccessibleName(string $name, string $label, ?string $accessibleName, ?FormFieldLabel $fieldLabel = null): ?string
    {
        if ($accessibleName !== null && $accessibleName !== '') {
            return $accessibleName;
        }

        if ($this->document->getProfile()->requiresFormFieldAlternativeDescriptions()) {
            if ($fieldLabel !== null) {
                return $fieldLabel->text;
            }

            return $label !== '' ? $label : $name;
        }

        return null;
    }

    private function resolveRadioButtonAccessibleName(string $value, ?string $accessibleName, ?FormFieldLabel $fieldLabel = null): ?string
    {
        if ($accessibleName !== null && $accessibleName !== '') {
            return $accessibleName;
        }

        if ($this->document->getProfile()->requiresFormFieldAlternativeDescriptions()) {
            return $fieldLabel !== null ? $fieldLabel->text : $value;
        }

        return null;
    }

    private function resolveRadioButtonGroupAccessibleName(string $name): ?string
    {
        if ($this->document->getProfile()->requiresFormFieldAlternativeDescriptions()) {
            return $name;
        }

        return null;
    }

    private function resolveStyledBaseFont(string $baseFont, TextSegment $segment): string
    {
        if (!$segment->bold && !$segment->italic) {
            return $baseFont;
        }

        $standardVariant = StandardFontName::resolveVariant($baseFont, $segment->bold, $segment->italic);

        if ($standardVariant !== null) {
            $this->registerFontIfNeeded($standardVariant);

            return $standardVariant;
        }

        foreach ($this->buildVariantCandidates($baseFont, $segment->bold, $segment->italic) as $candidate) {
            if ($this->hasRegisteredFont($candidate) || FontRegistry::has($candidate, $this->document->getFontConfig())) {
                $this->registerFontIfNeeded($candidate);

                return $candidate;
            }
        }

        return $baseFont;
    }

    /**
     * @return list<string>
     */
    private function buildVariantCandidates(string $baseFont, bool $bold, bool $italic): array
    {
        if (!$bold && !$italic) {
            return [$baseFont];
        }

        if ($bold && $italic) {
            $suffix = ['BoldItalic', 'BoldOblique'];
        } elseif ($bold) {
            $suffix = ['Bold'];
        } else {
            $suffix = ['Italic', 'Oblique'];
        }

        $candidates = [];

        foreach ($suffix as $variantSuffix) {
            if (str_ends_with($baseFont, '-Regular')) {
                $candidates[] = substr($baseFont, 0, -strlen('-Regular')) . '-' . $variantSuffix;
                continue;
            }

            if (str_ends_with($baseFont, '-Roman')) {
                $candidates[] = substr($baseFont, 0, -strlen('-Roman')) . '-' . $variantSuffix;
                continue;
            }

            $candidates[] = $baseFont . '-' . $variantSuffix;
        }

        return array_values(array_unique($candidates));
    }

    private function hasRegisteredFont(string $baseFont): bool
    {
        return array_any(
            $this->document->getFonts(),
            static fn (FontDefinition $registeredFont): bool => $registeredFont->getBaseFont() === $baseFont,
        );
    }

    private function registerFontIfNeeded(string $baseFont): void
    {
        if ($this->hasRegisteredFont($baseFont)) {
            return;
        }

        $this->document->registerFont($baseFont);
    }

    private function buildEllipsePath(float $centerX, float $centerY, float $radiusX, float $radiusY): PathBuilder
    {
        $controlOffsetX = $radiusX * 0.5522847498307936;
        $controlOffsetY = $radiusY * 0.5522847498307936;

        return $this->addPath()
            ->moveTo($centerX, $centerY + $radiusY)
            ->curveTo(
                $centerX + $controlOffsetX,
                $centerY + $radiusY,
                $centerX + $radiusX,
                $centerY + $controlOffsetY,
                $centerX + $radiusX,
                $centerY,
            )
            ->curveTo(
                $centerX + $radiusX,
                $centerY - $controlOffsetY,
                $centerX + $controlOffsetX,
                $centerY - $radiusY,
                $centerX,
                $centerY - $radiusY,
            )
            ->curveTo(
                $centerX - $controlOffsetX,
                $centerY - $radiusY,
                $centerX - $radiusX,
                $centerY - $controlOffsetY,
                $centerX - $radiusX,
                $centerY,
            )
            ->curveTo(
                $centerX - $radiusX,
                $centerY + $controlOffsetY,
                $centerX - $controlOffsetX,
                $centerY + $radiusY,
                $centerX,
                $centerY + $radiusY,
            )
            ->close();
    }

    private function finishClosedPath(
        PathBuilder $path,
        ?float $strokeWidth,
        ?Color $strokeColor,
        ?Color $fillColor,
        ?Opacity $opacity,
    ): self {
        if ($strokeWidth !== null && $fillColor !== null) {
            $path->fillAndStroke($strokeWidth, $strokeColor, $fillColor, $opacity);

            return $this;
        }

        if ($fillColor !== null) {
            $path->fill($fillColor, $opacity);

            return $this;
        }

        if ($strokeWidth === null) {
            throw new InvalidArgumentException('Closed path requires either a stroke or a fill.');
        }

        $path->stroke($strokeWidth, $strokeColor, $opacity);

        return $this;
    }

    public function resolveGraphicsStateName(?Opacity $opacity): ?string
    {
        if ($opacity === null) {
            return null;
        }

        $this->document->assertAllowsTransparency();

        return $this->resources->addOpacity($opacity);
    }

    public function addGraphicElement(Element $element): void
    {
        if ($this->document->isRenderingArtifactContext()) {
            $this->contents->addElement(new Raw('/Artifact BMC'));
            $this->contents->addElement($element);
            $this->contents->addElement(new Raw('EMC'));

            return;
        }

        $this->assertAllowsGraphicElements();
        $this->contents->addElement($element);
    }

    /**
     * @param callable(): void $renderer
     */
    public function renderDecorativeContent(callable $renderer): void
    {
        if ($this->document->getProfile()->requiresTaggedPdf()) {
            $this->document->renderInArtifactContext($renderer);

            return;
        }

        $renderer();
    }

    private function resolveComponentTextStructureTag(): ?StructureTag
    {
        if (!$this->document->getProfile()->requiresTaggedPdf()) {
            return null;
        }

        return StructureTag::Paragraph;
    }

    private function assertAllowsGraphicElements(): void
    {
        $profile = $this->document->getProfile();

        if (!$profile->requiresArtifactGraphicElements()) {
            return;
        }

        throw new InvalidArgumentException(sprintf(
            'Profile %s requires lines, shapes and paths to be rendered as artifacts in the current implementation.',
            $profile->name(),
        ));
    }

    private function createImageObject(Image $image): ImageObject
    {
        $softMask = $image->getSoftMask();

        return new ImageObject(
            $this->document->getUniqObjectId(),
            $image,
            $softMask !== null ? $this->createImageObject($softMask) : null,
        );
    }
}
