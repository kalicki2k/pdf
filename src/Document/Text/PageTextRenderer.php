<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document\Text;

use InvalidArgumentException;
use Kalle\Pdf\Document\Geometry\Position;
use Kalle\Pdf\Document\Geometry\Rect;
use Kalle\Pdf\Document\LinkTarget;
use Kalle\Pdf\Document\Page;
use Kalle\Pdf\Document\PageFonts;
use Kalle\Pdf\Document\PageGraphics;
use Kalle\Pdf\Document\PageLinks;
use Kalle\Pdf\Document\PageMarkedContentIds;
use Kalle\Pdf\Element\Text as TextElement;
use Kalle\Pdf\Font\FontDefinition;
use Kalle\Pdf\Graphics\Color;
use Kalle\Pdf\Graphics\Opacity;
use Kalle\Pdf\Layout\HorizontalAlign;
use Kalle\Pdf\Layout\TextOverflow;
use Kalle\Pdf\Structure\StructElem;

/**
 * @internal Coordinates text layout and rendering for a page.
 */
final class PageTextRenderer
{
    private const float DEFAULT_LINE_HEIGHT_FACTOR = 1.2;
    private const float DEFAULT_BOTTOM_MARGIN = 20.0;
    private readonly PageTextBlockRenderer $blockRenderer;

    public function __construct(
        private readonly Page $page,
        private readonly PageFonts $pageFonts,
        private readonly PageLinks $pageLinks,
        private readonly PageGraphics $pageGraphics,
        private readonly PageMarkedContentIds $pageMarkedContentIds,
        private readonly TextLayoutEngine $textLayoutEngine,
    ) {
        $this->blockRenderer = new PageTextBlockRenderer(
            $page,
            new PageTextLineRenderer($pageFonts, $textLayoutEngine),
        );
    }

    public function addText(
        string $text,
        Position $position,
        string $fontName = 'Helvetica',
        int $size = 12,
        TextOptions $options = new TextOptions(),
    ): Page {
        $structureTag = $this->resolveMarkedContentStructureTag($options);
        $artifactTag = $structureTag === null && $this->page->getDocument()->isRenderingArtifactContext()
            ? 'Artifact'
            : null;
        $contentTag = $structureTag !== null
            ? $structureTag->value
            : $artifactTag;

        if ($structureTag !== null) {
            $this->page->getDocument()->ensureStructureEnabled();
        }

        $font = $this->resolveFont($fontName);
        $markedContentId = $structureTag !== null ? $this->nextMarkedContentId() : null;
        $encodedText = $this->encodeText($font, $fontName, $text);
        $resourceFontName = $this->registerFontResource($font);
        $textWidth = $font->measureTextWidth($text, $size);
        [$leadingDecorationInset, $trailingDecorationInset] = $this->resolveDecorationInsets($font, $text, $size);
        $colorOperator = $options->color?->renderNonStrokingOperator();
        $graphicsStateName = $this->resolveGraphicsStateName($options->opacity);

        $this->updateUnicodeFontWidths($font);

        $this->page->contents->addElement(new TextElement(
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

        return $this->page;
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
    ): Page {
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
    ): Page {
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

        $startY = $this->blockRenderer->resolveTextBoxStartY(
            $box->y,
            $box->height,
            $size,
            $lineHeight,
            count($lines),
            $options->verticalAlign,
            $options->padding->top,
            $options->padding->bottom,
        );

        return $this->blockRenderer->renderTextLines(
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
        return $this->textLayoutEngine->layoutParagraphLines(
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
    ): Page {
        $lineHeight ??= $size * self::DEFAULT_LINE_HEIGHT_FACTOR;
        $bottomMargin ??= self::DEFAULT_BOTTOM_MARGIN;

        if ($maxWidth <= 0) {
            throw new InvalidArgumentException('Paragraph width must be greater than zero.');
        }

        if ($lineHeight <= 0) {
            throw new InvalidArgumentException('Line height must be greater than zero.');
        }

        return $this->blockRenderer->renderParagraphLines(
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
        return $this->textLayoutEngine->countParagraphLines($text, $baseFont, $size, $maxWidth, $maxLines, $overflow);
    }

    private function resolveFont(string $baseFont): FontDefinition
    {
        return $this->pageFonts->resolveFont($baseFont);
    }

    private function registerFontResource(FontDefinition $font): string
    {
        return $this->pageFonts->registerFontResource($font);
    }

    private function updateUnicodeFontWidths(FontDefinition $font): void
    {
        $this->pageFonts->updateUnicodeFontWidths($font);
    }

    private function resolveMarkedContentStructureTag(TextOptions $options): ?StructureTag
    {
        return $this->pageLinks->resolveMarkedContentStructureTag($options);
    }

    private function attachTextToStructure(TextOptions $options, StructureTag $tag, int $markedContentId, string $text): StructElem
    {
        return $this->pageLinks->attachTextToStructure($options, $tag, $markedContentId, $text);
    }

    private function resolveLinkAlternativeDescription(string $text): ?string
    {
        return $this->pageLinks->resolveLinkAlternativeDescription($text);
    }

    private function addLinkTarget(
        Rect $box,
        LinkTarget $target,
        ?StructElem $linkStructElem = null,
        ?string $alternativeDescription = null,
    ): void {
        $this->pageLinks->addLinkTarget($box, $target, $linkStructElem, $alternativeDescription);
    }

    private function resolveGraphicsStateName(?Opacity $opacity): ?string
    {
        return $this->pageGraphics->resolveGraphicsStateName($opacity);
    }

    private function nextMarkedContentId(): int
    {
        return $this->pageMarkedContentIds->next();
    }

    private function encodeText(FontDefinition $font, string $baseFont, string $text): string
    {
        if (!$font->supportsText($text)) {
            throw new InvalidArgumentException("Font '$baseFont' does not support the provided text.");
        }

        return $font->encodeText($text);
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

}
