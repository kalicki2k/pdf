<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use Kalle\Pdf\Document\Action\ButtonAction;
use Kalle\Pdf\Document\Annotation\AnnotationBorderStyle;
use Kalle\Pdf\Document\Annotation\LineEndingStyle;
use Kalle\Pdf\Document\Annotation\PageAnnotation;
use Kalle\Pdf\Document\Annotation\PageAnnotations;
use Kalle\Pdf\Document\Form\FormFieldFlags;
use Kalle\Pdf\Document\Form\FormFieldLabel;
use Kalle\Pdf\Document\Form\PageForms;
use Kalle\Pdf\Document\Geometry\Position;
use Kalle\Pdf\Document\Geometry\Rect;
use Kalle\Pdf\Document\Style\BadgeStyle;
use Kalle\Pdf\Document\Style\CalloutStyle;
use Kalle\Pdf\Document\Style\PanelStyle;
use Kalle\Pdf\Document\Text\FlowTextOptions;
use Kalle\Pdf\Document\Text\PageParagraphRenderer;
use Kalle\Pdf\Document\Text\PageTextElementRenderer;
use Kalle\Pdf\Document\Text\StructureTag;
use Kalle\Pdf\Document\Text\TextBoxOptions;
use Kalle\Pdf\Document\Text\TextFrame;
use Kalle\Pdf\Document\Text\TextOptions;
use Kalle\Pdf\Document\Text\TextSegment;
use Kalle\Pdf\Element\Element;
use Kalle\Pdf\Element\Image;
use Kalle\Pdf\Element\Raw;
use Kalle\Pdf\Font\FontDefinition;
use Kalle\Pdf\Graphics\Color;
use Kalle\Pdf\Graphics\Opacity;
use Kalle\Pdf\Layout\HorizontalAlign;
use Kalle\Pdf\Layout\TextOverflow;
use Kalle\Pdf\Object\IndirectObject;
use Kalle\Pdf\Structure\StructElem;

/**
 * @internal Internal page implementation. Use Kalle\Pdf\Page from the public API.
 */
final class Page extends IndirectObject
{
    private const float DEFAULT_BOTTOM_MARGIN = 20.0;

    private ?PageComponents $pageComponents = null;
    private ?PageFonts $pageFonts = null;
    private ?PageGraphics $pageGraphics = null;
    private ?PageImages $pageImages = null;
    private ?PageLinks $pageLinks = null;
    private ?PageObjectRenderer $pageObjectRenderer = null;
    private ?PageAnnotations $pageAnnotations = null;
    private ?PageForms $pageForms = null;
    private ?PageTextElementRenderer $pageTextElementRenderer = null;
    private ?PageParagraphRenderer $pageParagraphRenderer = null;
    private PageMarkedContentIds $pageMarkedContentIds;
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
        $this->pageMarkedContentIds = new PageMarkedContentIds();
    }

    public function addText(
        string $text,
        Position $position,
        string $fontName = 'Helvetica',
        int $size = 12,
        TextOptions $options = new TextOptions(),
    ): self {
        return $this->pageTextElementRenderer()->render($text, $position, $fontName, $size, $options);
    }

    /**
     * @param callable(self): void $renderer
     */
    public function layer(string | OptionalContentGroup $layer, callable $renderer, bool $visibleByDefault = true): self
    {
        $group = is_string($layer)
            ? $this->document->addLayer($layer, $visibleByDefault)
            : $this->document->addLayer($layer->getName(), $layer->isVisibleByDefault());
        $resourceName = $this->addPropertyResource($group);

        $this->addContentElement(new Raw("/OC /$resourceName BDC"));

        try {
            $renderer($this);
        } finally {
            $this->addContentElement(new Raw('EMC'));
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
        return $this->pageComponents()->addBadge($text, $position, $baseFont, $size, $style, $link);
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
        return $this->pageComponents()->addPanel($body, $x, $y, $width, $height, $title, $bodyFont, $style, $titleFont, $link);
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
        return $this->pageComponents()->addCallout($body, $x, $y, $width, $height, $pointerX, $pointerY, $title, $bodyFont, $style, $titleFont, $link);
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
        return $this->pageParagraphRenderer()->addFlowText($text, $position, $maxWidth, $fontName, $size, $options);
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
        return $this->pageParagraphRenderer()->addTextBox($text, $box, $fontName, $size, $options);
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
        return $this->pageParagraphRenderer()->layoutParagraphLines(
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
        return $this->pageParagraphRenderer()->renderParagraphLines(
            $lines,
            $x,
            $y,
            $maxWidth,
            $baseFont,
            $size,
            $tag,
            $parentStructElem,
            $lineHeight,
            $bottomMargin,
            $align,
        );
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
        return $this->pageGraphics()->addPath();
    }

    public function addLine(
        Position $from,
        Position $to,
        float $width = 1.0,
        ?Color $color = null,
        ?Opacity $opacity = null,
    ): self {
        return $this->pageGraphics()->addLine($from, $to, $width, $color, $opacity);
    }

    public function addRectangle(
        Rect $box,
        ?float $strokeWidth = 1.0,
        ?Color $strokeColor = null,
        ?Color $fillColor = null,
        ?Opacity $opacity = null,
    ): self {
        return $this->pageGraphics()->addRectangle($box, $strokeWidth, $strokeColor, $fillColor, $opacity);
    }

    public function addRoundedRectangle(
        Rect $box,
        float $radius,
        ?float $strokeWidth = 1.0,
        ?Color $strokeColor = null,
        ?Color $fillColor = null,
        ?Opacity $opacity = null,
    ): self {
        return $this->pageGraphics()->addRoundedRectangle($box, $radius, $strokeWidth, $strokeColor, $fillColor, $opacity);
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
        return $this->pageGraphics()->addCircle($centerX, $centerY, $radius, $strokeWidth, $strokeColor, $fillColor, $opacity);
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
        return $this->pageGraphics()->addEllipse($centerX, $centerY, $radiusX, $radiusY, $strokeWidth, $strokeColor, $fillColor, $opacity);
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
        return $this->pageGraphics()->addPolygon($points, $strokeWidth, $strokeColor, $fillColor, $opacity);
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
        return $this->pageGraphics()->addArrow($from, $to, $strokeWidth, $color, $opacity, $headLength, $headWidth);
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
        return $this->pageGraphics()->addStar($centerX, $centerY, $points, $outerRadius, $innerRadius, $strokeWidth, $strokeColor, $fillColor, $opacity);
    }

    public function addLink(
        Rect $box,
        string $url,
        ?string $accessibleName = null,
    ): self {
        return $this->pageLinks()->addLink($box, $url, $accessibleName);
    }

    public function addInternalLink(
        Rect $box,
        string $destination,
        ?string $accessibleName = null,
    ): self {
        return $this->pageLinks()->addInternalLink($box, $destination, $accessibleName);
    }

    public function addFileAttachment(
        Rect $box,
        FileSpecification $file,
        string $icon = 'PushPin',
        ?string $contents = null,
    ): self {
        return $this->pageAnnotations()->addFileAttachmentAnnotation($box, $file, $icon, $contents);
    }

    public function addTextAnnotation(
        Rect $box,
        string $contents,
        ?string $title = null,
        string $icon = 'Note',
        bool $open = false,
    ): self {
        return $this->pageAnnotations()->addTextAnnotation($box, $contents, $title, $icon, $open);
    }

    public function addPopupAnnotation(
        PageAnnotation & IndirectObject $parent,
        Rect $box,
        bool $open = false,
    ): self {
        return $this->pageAnnotations()->addPopupAnnotation($parent, $box, $open);
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
        return $this->pageAnnotations()->addFreeTextAnnotation(
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
        ?Color $color = null,
        ?string $contents = null,
        ?string $title = null,
    ): self {
        return $this->pageAnnotations()->addHighlightAnnotation($box, $color, $contents, $title);
    }

    public function addUnderlineAnnotation(
        Rect $box,
        ?Color $color = null,
        ?string $contents = null,
        ?string $title = null,
    ): self {
        return $this->pageAnnotations()->addUnderlineAnnotation($box, $color, $contents, $title);
    }

    public function addStrikeOutAnnotation(
        Rect $box,
        ?Color $color = null,
        ?string $contents = null,
        ?string $title = null,
    ): self {
        return $this->pageAnnotations()->addStrikeOutAnnotation($box, $color, $contents, $title);
    }

    public function addSquigglyAnnotation(
        Rect $box,
        ?Color $color = null,
        ?string $contents = null,
        ?string $title = null,
    ): self {
        return $this->pageAnnotations()->addSquigglyAnnotation($box, $color, $contents, $title);
    }

    public function addStampAnnotation(
        Rect $box,
        string $icon = 'Draft',
        ?Color $color = null,
        ?string $contents = null,
        ?string $title = null,
    ): self {
        return $this->pageAnnotations()->addStampAnnotation($box, $icon, $color, $contents, $title);
    }

    public function addSquareAnnotation(
        Rect $box,
        ?Color $borderColor = null,
        ?Color $fillColor = null,
        ?string $contents = null,
        ?string $title = null,
        ?AnnotationBorderStyle $borderStyle = null,
    ): self {
        return $this->pageAnnotations()->addSquareAnnotation(
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
        ?Color $borderColor = null,
        ?Color $fillColor = null,
        ?string $contents = null,
        ?string $title = null,
        ?AnnotationBorderStyle $borderStyle = null,
    ): self {
        return $this->pageAnnotations()->addCircleAnnotation(
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
        ?Color $color = null,
        ?string $contents = null,
        ?string $title = null,
    ): self {
        return $this->pageAnnotations()->addInkAnnotation($box, $paths, $color, $contents, $title);
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
        return $this->pageAnnotations()->addLineAnnotation(
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
        ?Color $color = null,
        ?string $contents = null,
        ?string $title = null,
        ?LineEndingStyle $startStyle = null,
        ?LineEndingStyle $endStyle = null,
        ?string $subject = null,
        ?AnnotationBorderStyle $borderStyle = null,
    ): self {
        return $this->pageAnnotations()->addPolyLineAnnotation(
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
        ?Color $borderColor = null,
        ?Color $fillColor = null,
        ?string $contents = null,
        ?string $title = null,
        ?string $subject = null,
        ?AnnotationBorderStyle $borderStyle = null,
    ): self {
        return $this->pageAnnotations()->addPolygonAnnotation(
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
        ?string $contents = null,
        ?string $title = null,
        string $symbol = 'None',
    ): self {
        return $this->pageAnnotations()->addCaretAnnotation($box, $contents, $title, $symbol);
    }

    public function addImage(
        Image $image,
        Position $position,
        ?float $width = null,
        ?float $height = null,
        ImageOptions $options = new ImageOptions(),
    ): self {
        return $this->pageImages()->addImage($image, $position, $width, $height, $options);
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
        return $this->pageForms()->addTextField(
            $name,
            $box,
            $value,
            $baseFont,
            $size,
            $multiline,
            $textColor,
            $flags,
            $defaultValue,
            $accessibleName,
            $fieldLabel,
        );
    }

    public function addCheckbox(
        string $name,
        Position $position,
        float $size,
        bool $checked = false,
        ?string $accessibleName = null,
        ?FormFieldLabel $fieldLabel = null,
    ): self {
        return $this->pageForms()->addCheckbox($name, $position, $size, $checked, $accessibleName, $fieldLabel);
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
        return $this->pageForms()->addRadioButton($name, $value, $position, $size, $checked, $accessibleName, $fieldLabel);
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
        return $this->pageForms()->addComboBox(
            $name,
            $box,
            $options,
            $value,
            $baseFont,
            $size,
            $textColor,
            $flags,
            $defaultValue,
            $accessibleName,
            $fieldLabel,
        );
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
        return $this->pageForms()->addListBox(
            $name,
            $box,
            $options,
            $value,
            $baseFont,
            $size,
            $textColor,
            $flags,
            $defaultValue,
            $accessibleName,
            $fieldLabel,
        );
    }

    public function addSignatureField(
        string $name,
        Rect $box,
        ?string $accessibleName = null,
        ?FormFieldLabel $fieldLabel = null,
    ): self {
        return $this->pageForms()->addSignatureField($name, $box, $accessibleName, $fieldLabel);
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
        return $this->pageForms()->addPushButton(
            $name,
            $label,
            $box,
            $baseFont,
            $size,
            $textColor,
            $action,
            $accessibleName,
            $fieldLabel,
        );
    }

    public function render(): string
    {
        return $this->pageObjectRenderer()->render($this->pageMarkedContentIds->hasAllocatedIds());
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

    public function getResources(): Resources
    {
        return $this->resources;
    }

    public function getContents(): Contents
    {
        return $this->contents;
    }

    public function addContentElement(Element $element): void
    {
        $this->contents->addElement($element);
    }

    public function addFontResource(FontDefinition $font): string
    {
        return $this->resources->addFont($font);
    }

    public function addImageResource(ImageObject $image): string
    {
        return $this->resources->addImage($image);
    }

    public function addPropertyResource(OptionalContentGroup $group): string
    {
        return $this->resources->addProperty($group);
    }

    public function addOpacityResource(Opacity $opacity): string
    {
        return $this->resources->addOpacity($opacity);
    }

    /**
     * @return list<IndirectObject&PageAnnotation>
     */
    public function getAnnotations(): array
    {
        return $this->pageAnnotations?->all() ?? [];
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
        return $this->pageParagraphRenderer()->countParagraphLines($text, $baseFont, $size, $maxWidth, $maxLines, $overflow);
    }

    public function measureTextWidth(string $text, string $baseFont, int $size): float
    {
        return $this->pageFonts()->measureTextWidth($text, $baseFont, $size);
    }

    private function pageFonts(): PageFonts
    {
        return $this->pageFonts ??= PageFonts::forPage($this);
    }

    private function pageObjectRenderer(): PageObjectRenderer
    {
        return $this->pageObjectRenderer ??= PageObjectRenderer::forPage($this);
    }

    private function pageGraphics(): PageGraphics
    {
        return $this->pageGraphics ??= PageGraphics::forPage($this);
    }

    private function pageComponents(): PageComponents
    {
        return $this->pageComponents ??= PageComponents::forPage($this, $this->pageLinks());
    }

    private function pageAnnotations(): PageAnnotations
    {
        return $this->pageAnnotations ??= PageAnnotations::forPage($this, $this->pageFonts());
    }

    private function pageImages(): PageImages
    {
        return $this->pageImages ??= PageImages::forPage($this, $this->pageMarkedContentIds);
    }

    private function pageLinks(): PageLinks
    {
        return $this->pageLinks ??= PageLinks::forPage($this, $this->pageAnnotations());
    }

    private function pageForms(): PageForms
    {
        return $this->pageForms ??= PageForms::forPage($this, $this->pageAnnotations(), $this->pageFonts());
    }

    private function pageTextElementRenderer(): PageTextElementRenderer
    {
        return $this->pageTextElementRenderer ??= PageTextElementRenderer::forPage(
            $this,
            $this->pageFonts(),
            $this->pageLinks(),
            $this->pageGraphics(),
            $this->pageMarkedContentIds,
        );
    }

    private function pageParagraphRenderer(): PageParagraphRenderer
    {
        return $this->pageParagraphRenderer ??= PageParagraphRenderer::forPage($this, $this->pageFonts());
    }

    public function resolveGraphicsStateName(?Opacity $opacity): ?string
    {
        return $this->pageGraphics()->resolveGraphicsStateName($opacity);
    }

    public function addGraphicElement(Element $element): void
    {
        $this->pageGraphics()->addGraphicElement($element);
    }

    /**
     * @param callable(): void $renderer
     */
    public function renderDecorativeContent(callable $renderer): void
    {
        $this->pageGraphics()->renderDecorativeContent($renderer);
    }
}
