<?php

declare(strict_types=1);

namespace Kalle\Pdf;

use Kalle\Pdf\Form\FormFieldFlags;
use Kalle\Pdf\Form\FormFieldLabel;
use Kalle\Pdf\Geometry\Position;
use Kalle\Pdf\Geometry\Rect;
use Kalle\Pdf\Graphics\Color;
use Kalle\Pdf\Graphics\Opacity;
use Kalle\Pdf\Image;
use Kalle\Pdf\Internal\Action\ButtonAction;
use Kalle\Pdf\Internal\Document\OptionalContent\OptionalContentGroup;
use Kalle\Pdf\Internal\Layout\Table\Table as InternalTable;
use Kalle\Pdf\Internal\Page\Annotation\PageAnnotation;
use Kalle\Pdf\Internal\Page\Annotation\Style\AnnotationBorderStyle;
use Kalle\Pdf\Internal\Page\Annotation\Style\LineEndingStyle;
use Kalle\Pdf\Internal\Page\Content\PathBuilder;
use Kalle\Pdf\Internal\Page\Page as InternalPage;
use Kalle\Pdf\Internal\PageRegistry;
use Kalle\Pdf\Layout\TextOverflow;
use Kalle\Pdf\Model\Document\FileSpecification;
use Kalle\Pdf\Model\Page\ImageOptions;
use Kalle\Pdf\Navigation\LinkTarget;
use Kalle\Pdf\Object\IndirectObject;
use Kalle\Pdf\Style\BadgeStyle;
use Kalle\Pdf\Style\CalloutStyle;
use Kalle\Pdf\Style\PanelStyle;
use Kalle\Pdf\Text\FlowTextOptions;
use Kalle\Pdf\Text\TextBoxOptions;
use Kalle\Pdf\Text\TextOptions;
use Kalle\Pdf\Text\TextSegment;

/**
 * Public facade for page operations exposed to library users.
 */
final readonly class Page
{
    /**
     * @internal Public pages are created by Document::addPage().
     */
    public function __construct(private InternalPage $page)
    {
        PageRegistry::register($this, $page);
    }

    /**
     * Writes a single text fragment at the given position.
     */
    public function addText(
        string $text,
        Position $position,
        string $fontName = 'Helvetica',
        int $size = 12,
        TextOptions $options = new TextOptions(),
    ): self {
        $this->page->addText($text, $position, $fontName, $size, $options);

        return $this;
    }

    /**
     * Runs drawing commands inside an optional content layer.
     *
     * @param callable(self): void $renderer
     */
    public function layer(string | OptionalContentGroup $layer, callable $renderer, bool $visibleByDefault = true): self
    {
        $this->page->layer(
            $layer,
            static function (InternalPage $page) use ($renderer): void {
                $renderer(new self($page));
            },
            $visibleByDefault,
        );

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
        $this->page->addBadge($text, $position, $baseFont, $size, $style, $link);

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
        $this->page->addPanel($body, $x, $y, $width, $height, $title, $bodyFont, $style, $titleFont, $link);

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
        $this->page->addCallout($body, $x, $y, $width, $height, $pointerX, $pointerY, $title, $bodyFont, $style, $titleFont, $link);

        return $this;
    }

    /**
     * @param string|list<TextSegment> $text
     */
    public function addFlowText(string | array $text, Position $position, float $maxWidth, string $fontName = 'Helvetica', int $size = 12, FlowTextOptions $options = new FlowTextOptions()): self
    {
        $this->page->addFlowText($text, $position, $maxWidth, $fontName, $size, $options);

        return $this;
    }

    /**
     * @param string|list<TextSegment> $text
     */
    public function addTextBox(string | array $text, Rect $box, string $fontName = 'Helvetica', int $size = 12, TextBoxOptions $options = new TextBoxOptions()): self
    {
        $this->page->addTextBox($text, $box, $fontName, $size, $options);

        return $this;
    }

    /**
     * Creates a text frame for flowing text across pages.
     */
    public function createTextFrame(Position $position, float $width, float $bottomMargin = 20.0): TextFrame
    {
        return new TextFrame($this->page->createTextFrame($position, $width, $bottomMargin));
    }

    /**
     * Creates a table builder anchored on this page.
     *
     * @param list<float|int> $columnWidths
     */
    public function createTable(Position $position, float $width, array $columnWidths, float $bottomMargin = 20.0): Table
    {
        return new Table($this->page->createTable($position, $width, $columnWidths, $bottomMargin));
    }

    /**
     * Starts a path builder for custom vector drawing.
     */
    public function addPath(): PathBuilder
    {
        return $this->page->addPath();
    }

    public function addLine(Position $from, Position $to, float $width = 1.0, ?Color $color = null, ?Opacity $opacity = null): self
    {
        $this->page->addLine($from, $to, $width, $color, $opacity);

        return $this;
    }

    public function addRectangle(Rect $box, ?float $strokeWidth = 1.0, ?Color $strokeColor = null, ?Color $fillColor = null, ?Opacity $opacity = null): self
    {
        $this->page->addRectangle($box, $strokeWidth, $strokeColor, $fillColor, $opacity);

        return $this;
    }

    public function addRoundedRectangle(Rect $box, float $radius, ?float $strokeWidth = 1.0, ?Color $strokeColor = null, ?Color $fillColor = null, ?Opacity $opacity = null): self
    {
        $this->page->addRoundedRectangle($box, $radius, $strokeWidth, $strokeColor, $fillColor, $opacity);

        return $this;
    }

    public function addCircle(float $centerX, float $centerY, float $radius, ?float $strokeWidth = 1.0, ?Color $strokeColor = null, ?Color $fillColor = null, ?Opacity $opacity = null): self
    {
        $this->page->addCircle($centerX, $centerY, $radius, $strokeWidth, $strokeColor, $fillColor, $opacity);

        return $this;
    }

    public function addEllipse(float $centerX, float $centerY, float $radiusX, float $radiusY, ?float $strokeWidth = 1.0, ?Color $strokeColor = null, ?Color $fillColor = null, ?Opacity $opacity = null): self
    {
        $this->page->addEllipse($centerX, $centerY, $radiusX, $radiusY, $strokeWidth, $strokeColor, $fillColor, $opacity);

        return $this;
    }

    /**
     * @param list<array{0: float|int, 1: float|int}> $points
     */
    public function addPolygon(array $points, ?float $strokeWidth = 1.0, ?Color $strokeColor = null, ?Color $fillColor = null, ?Opacity $opacity = null): self
    {
        $this->page->addPolygon($points, $strokeWidth, $strokeColor, $fillColor, $opacity);

        return $this;
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
        $this->page->addArrow($from, $to, $strokeWidth, $color, $opacity, $headLength, $headWidth);

        return $this;
    }

    public function addStar(float $centerX, float $centerY, int $points, float $outerRadius, float $innerRadius, ?float $strokeWidth = 1.0, ?Color $strokeColor = null, ?Color $fillColor = null, ?Opacity $opacity = null): self
    {
        $this->page->addStar($centerX, $centerY, $points, $outerRadius, $innerRadius, $strokeWidth, $strokeColor, $fillColor, $opacity);

        return $this;
    }

    public function addLink(Rect $box, string $url, ?string $accessibleName = null): self
    {
        $this->page->addLink($box, $url, $accessibleName);

        return $this;
    }

    public function addInternalLink(Rect $box, string $destination, ?string $accessibleName = null): self
    {
        $this->page->addInternalLink($box, $destination, $accessibleName);

        return $this;
    }

    public function addFileAttachment(Rect $box, FileSpecification $file, string $icon = 'PushPin', ?string $contents = null): self
    {
        $this->page->addFileAttachment($box, $file, $icon, $contents);

        return $this;
    }

    public function addTextAnnotation(Rect $box, string $contents, ?string $title = null, string $icon = 'Note', bool $open = false): self
    {
        $this->page->addTextAnnotation($box, $contents, $title, $icon, $open);

        return $this;
    }

    public function addPopupAnnotation(PageAnnotation & IndirectObject $parent, Rect $box, bool $open = false): self
    {
        $this->page->addPopupAnnotation($parent, $box, $open);

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
        $this->page->addFreeTextAnnotation($box, $contents, $baseFont, $size, $textColor, $borderColor, $fillColor, $title);

        return $this;
    }

    public function addHighlightAnnotation(Rect $box, ?Color $color = null, ?string $contents = null, ?string $title = null): self
    {
        $this->page->addHighlightAnnotation($box, $color, $contents, $title);

        return $this;
    }

    public function addUnderlineAnnotation(Rect $box, ?Color $color = null, ?string $contents = null, ?string $title = null): self
    {
        $this->page->addUnderlineAnnotation($box, $color, $contents, $title);

        return $this;
    }

    public function addStrikeOutAnnotation(Rect $box, ?Color $color = null, ?string $contents = null, ?string $title = null): self
    {
        $this->page->addStrikeOutAnnotation($box, $color, $contents, $title);

        return $this;
    }

    public function addSquigglyAnnotation(Rect $box, ?Color $color = null, ?string $contents = null, ?string $title = null): self
    {
        $this->page->addSquigglyAnnotation($box, $color, $contents, $title);

        return $this;
    }

    public function addStampAnnotation(Rect $box, string $icon = 'Draft', ?Color $color = null, ?string $contents = null, ?string $title = null): self
    {
        $this->page->addStampAnnotation($box, $icon, $color, $contents, $title);

        return $this;
    }

    public function addSquareAnnotation(Rect $box, ?Color $borderColor = null, ?Color $fillColor = null, ?string $contents = null, ?string $title = null, ?AnnotationBorderStyle $borderStyle = null): self
    {
        $this->page->addSquareAnnotation($box, $borderColor, $fillColor, $contents, $title, $borderStyle);

        return $this;
    }

    public function addCircleAnnotation(Rect $box, ?Color $borderColor = null, ?Color $fillColor = null, ?string $contents = null, ?string $title = null, ?AnnotationBorderStyle $borderStyle = null): self
    {
        $this->page->addCircleAnnotation($box, $borderColor, $fillColor, $contents, $title, $borderStyle);

        return $this;
    }

    /**
     * @param list<list<array{0: float, 1: float}>> $paths
     */
    public function addInkAnnotation(Rect $box, array $paths, ?Color $color = null, ?string $contents = null, ?string $title = null): self
    {
        $this->page->addInkAnnotation($box, $paths, $color, $contents, $title);

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
        $this->page->addLineAnnotation($from, $to, $color, $contents, $title, $startStyle, $endStyle, $subject, $borderStyle);

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
        $this->page->addPolyLineAnnotation($vertices, $color, $contents, $title, $startStyle, $endStyle, $subject, $borderStyle);

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
        $this->page->addPolygonAnnotation($vertices, $borderColor, $fillColor, $contents, $title, $subject, $borderStyle);

        return $this;
    }

    public function addCaretAnnotation(Rect $box, ?string $contents = null, ?string $title = null, string $symbol = 'None'): self
    {
        $this->page->addCaretAnnotation($box, $contents, $title, $symbol);

        return $this;
    }

    public function addImage(Image $image, Position $position, ?float $width = null, ?float $height = null, ImageOptions $options = new ImageOptions()): self
    {
        $this->page->addImage($image, $position, $width, $height, $options);

        return $this;
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
        $this->page->addTextField($name, $box, $value, $baseFont, $size, $multiline, $textColor, $flags, $defaultValue, $accessibleName, $fieldLabel);

        return $this;
    }

    public function addCheckbox(string $name, Position $position, float $size, bool $checked = false, ?string $accessibleName = null, ?FormFieldLabel $fieldLabel = null): self
    {
        $this->page->addCheckbox($name, $position, $size, $checked, $accessibleName, $fieldLabel);

        return $this;
    }

    public function addRadioButton(string $name, string $value, Position $position, float $size, bool $checked = false, ?string $accessibleName = null, ?FormFieldLabel $fieldLabel = null): self
    {
        $this->page->addRadioButton($name, $value, $position, $size, $checked, $accessibleName, $fieldLabel);

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
        $this->page->addComboBox($name, $box, $options, $value, $baseFont, $size, $textColor, $flags, $defaultValue, $accessibleName, $fieldLabel);

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
        $this->page->addListBox($name, $box, $options, $value, $baseFont, $size, $textColor, $flags, $defaultValue, $accessibleName, $fieldLabel);

        return $this;
    }

    public function addSignatureField(string $name, Rect $box, ?string $accessibleName = null, ?FormFieldLabel $fieldLabel = null): self
    {
        $this->page->addSignatureField($name, $box, $accessibleName, $fieldLabel);

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
        $this->page->addPushButton($name, $label, $box, $baseFont, $size, $textColor, $action, $accessibleName, $fieldLabel);

        return $this;
    }

    /**
     * Returns the page width in PDF points.
     */
    public function getWidth(): float
    {
        return $this->page->getWidth();
    }

    /**
     * Returns the page height in PDF points.
     */
    public function getHeight(): float
    {
        return $this->page->getHeight();
    }

    /**
     * Counts wrapped paragraph lines for the given text and width.
     *
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
        return $this->page->countParagraphLines($text, $baseFont, $size, $maxWidth, $maxLines, $overflow);
    }

    /**
     * Measures the width of a text fragment for the given font and size.
     */
    public function measureTextWidth(string $text, string $baseFont, int $size): float
    {
        return $this->page->measureTextWidth($text, $baseFont, $size);
    }

}
