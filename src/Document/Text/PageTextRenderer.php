<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document\Text;

use Closure;
use InvalidArgumentException;
use Kalle\Pdf\Document\Geometry\Position;
use Kalle\Pdf\Document\Geometry\Rect;
use Kalle\Pdf\Document\LinkTarget;
use Kalle\Pdf\Document\Page;
use Kalle\Pdf\Document\PageFonts;
use Kalle\Pdf\Document\PageGraphics;
use Kalle\Pdf\Document\PageLinks;
use Kalle\Pdf\Element\Text as TextElement;
use Kalle\Pdf\Font\FontDefinition;
use Kalle\Pdf\Graphics\Color;
use Kalle\Pdf\Graphics\Opacity;
use Kalle\Pdf\Layout\HorizontalAlign;
use Kalle\Pdf\Layout\TextOverflow;
use Kalle\Pdf\Layout\VerticalAlign;
use Kalle\Pdf\Structure\StructElem;

/**
 * @internal Coordinates text layout and rendering for a page.
 */
final class PageTextRenderer
{
    private const float DEFAULT_LINE_HEIGHT_FACTOR = 1.2;
    private const float DEFAULT_BOTTOM_MARGIN = 20.0;

    private ?TextLayoutEngine $textLayoutEngine = null;

    /**
     * @param Closure(): int $nextMarkedContentId
     */
    public function __construct(
        private readonly Page $page,
        private readonly PageFonts $pageFonts,
        private readonly PageLinks $pageLinks,
        private readonly PageGraphics $pageGraphics,
        private readonly Closure $nextMarkedContentId,
    ) {
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
    ): Page {
        $lineHeight ??= $size * self::DEFAULT_LINE_HEIGHT_FACTOR;
        $bottomMargin ??= self::DEFAULT_BOTTOM_MARGIN;

        if ($maxWidth <= 0) {
            throw new InvalidArgumentException('Paragraph width must be greater than zero.');
        }

        if ($lineHeight <= 0) {
            throw new InvalidArgumentException('Line height must be greater than zero.');
        }

        $page = $this->page;
        $currentY = $y;
        $topMargin = $this->page->getHeight() - $y;

        foreach ($lines as $line) {
            if ($currentY < $bottomMargin) {
                $page = $this->page->getDocument()->addPage($this->page->getWidth(), $this->page->getHeight());
                $currentY = $this->page->getHeight() - $topMargin;
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
        return ($this->nextMarkedContentId)();
    }

    private function resolveStyledBaseFont(string $baseFont, TextSegment $segment): string
    {
        return $this->pageFonts->resolveStyledBaseFont($baseFont, $segment);
    }

    private function encodeText(FontDefinition $font, string $baseFont, string $text): string
    {
        if (!$font->supportsText($text)) {
            throw new InvalidArgumentException("Font '$baseFont' does not support the provided text.");
        }

        return $font->encodeText($text);
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
    ): Page {
        $currentY = $y;

        foreach ($lines as $line) {
            $this->renderTextLine($this->page, $line, $x, $currentY, $maxWidth, $baseFont, $size, $tag, $parentStructElem, $align);
            $currentY -= $lineHeight;
        }

        return $this->page;
    }

    /**
     * @param array{segments: array<int, TextSegment>, justify: bool} $line
     */
    private function renderTextLine(
        Page $page,
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
        Page $page,
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

    private function textLayoutEngine(): TextLayoutEngine
    {
        return $this->textLayoutEngine ??= new TextLayoutEngine(
            fn (string $baseFont): FontDefinition => $this->resolveFont($baseFont),
            fn (string $baseFont, TextSegment $segment): string => $this->resolveStyledBaseFont($baseFont, $segment),
        );
    }
}
