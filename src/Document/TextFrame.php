<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use InvalidArgumentException;
use Kalle\Pdf\Graphics\Color;
use Kalle\Pdf\Graphics\Opacity;
use Kalle\Pdf\Layout\BulletType;
use Kalle\Pdf\Layout\HorizontalAlign;
use Kalle\Pdf\Layout\TextOverflow;

final class TextFrame
{
    private const DEFAULT_BULLET_INDENT = 14.0;

    private Page $page;
    private float $cursorY;

    public function __construct(
        Page $page,
        private readonly float $x,
        float $y,
        private readonly float $width,
        private readonly float $bottomMargin = 20.0,
    ) {
        $this->page = $page;
        $this->cursorY = $y;
    }

    /**
     * @param string|list<TextSegment> $text
     */
    public function paragraph(
        string | array $text,
        string $baseFont,
        int $size,
        ?string $tag = null,
        ?float $lineHeight = null,
        ?float $spacingAfter = null,
        ?Color $color = null,
        ?Opacity $opacity = null,
        HorizontalAlign $align = HorizontalAlign::LEFT,
        ?int $maxLines = null,
        TextOverflow $overflow = TextOverflow::CLIP,
    ): self {
        $lineHeight ??= $size * 1.2;
        $spacingAfter ??= $lineHeight;

        return $this->flowParagraph(
            text: $text,
            x: $this->x,
            width: $this->width,
            baseFont: $baseFont,
            size: $size,
            tag: $tag,
            lineHeight: $lineHeight,
            spacingAfter: $spacingAfter,
            color: $color,
            opacity: $opacity,
            align: $align,
            maxLines: $maxLines,
            overflow: $overflow,
        );
    }

    /**
     * @param list<string|list<TextSegment>> $items
     */
    public function bulletList(
        array $items,
        string $baseFont,
        int $size,
        ?string $tag = null,
        ?float $lineHeight = null,
        ?float $spacingAfter = null,
        ?float $itemSpacing = null,
        ?Color $color = null,
        ?Opacity $opacity = null,
        BulletType $bulletType = BulletType::DISC,
        ?Color $bulletColor = null,
        ?float $bulletIndent = null,
    ): self {
        $lineHeight ??= $size * 1.2;
        $spacingAfter ??= $lineHeight;
        $itemSpacing ??= $size * 0.4;
        $bulletIndent ??= self::DEFAULT_BULLET_INDENT;

        if ($items === []) {
            return $this;
        }

        if ($bulletIndent <= 0) {
            throw new InvalidArgumentException('Bullet indent must be greater than zero.');
        }

        if ($this->width <= $bulletIndent) {
            throw new InvalidArgumentException('Bullet indent must be smaller than the text frame width.');
        }

        return $this->renderList(
            items: $items,
            markerRenderer: static fn (int $index): string => $bulletType->value,
            baseFont: $baseFont,
            size: $size,
            tag: $tag,
            lineHeight: $lineHeight,
            spacingAfter: $spacingAfter,
            itemSpacing: $itemSpacing,
            color: $color,
            opacity: $opacity,
            markerColor: $bulletColor ?? $color,
            markerIndent: $bulletIndent,
        );
    }

    /**
     * @param list<string|list<TextSegment>> $items
     */
    public function numberedList(
        array $items,
        string $baseFont,
        int $size,
        ?string $tag = null,
        ?float $lineHeight = null,
        ?float $spacingAfter = null,
        ?float $itemSpacing = null,
        ?Color $color = null,
        ?Opacity $opacity = null,
        ?Color $numberColor = null,
        ?float $numberIndent = null,
        int $startAt = 1,
    ): self {
        $lineHeight ??= $size * 1.2;
        $spacingAfter ??= $lineHeight;
        $itemSpacing ??= $size * 0.4;
        $numberIndent ??= self::DEFAULT_BULLET_INDENT;

        if ($items === []) {
            return $this;
        }

        if ($numberIndent <= 0) {
            throw new InvalidArgumentException('Number indent must be greater than zero.');
        }

        if ($this->width <= $numberIndent) {
            throw new InvalidArgumentException('Number indent must be smaller than the text frame width.');
        }

        if ($startAt <= 0) {
            throw new InvalidArgumentException('Numbered lists must start at 1 or greater.');
        }

        return $this->renderList(
            items: $items,
            markerRenderer: static fn (int $index): string => ($startAt + $index) . '.',
            baseFont: $baseFont,
            size: $size,
            tag: $tag,
            lineHeight: $lineHeight,
            spacingAfter: $spacingAfter,
            itemSpacing: $itemSpacing,
            color: $color,
            opacity: $opacity,
            markerColor: $numberColor ?? $color,
            markerIndent: $numberIndent,
        );
    }

    /**
     * @param list<string|list<TextSegment>> $items
     * @param \Closure(int): string $markerRenderer
     */
    private function renderList(
        array $items,
        \Closure $markerRenderer,
        string $baseFont,
        int $size,
        ?string $tag,
        float $lineHeight,
        float $spacingAfter,
        float $itemSpacing,
        ?Color $color,
        ?Opacity $opacity,
        ?Color $markerColor,
        float $markerIndent,
    ): self {
        foreach ($items as $index => $item) {
            if ($this->cursorY < $this->bottomMargin + $lineHeight) {
                $topMargin = $this->page->getHeight() - $this->cursorY;
                $this->page = $this->page->getDocument()->addPage($this->page->getWidth(), $this->page->getHeight());
                $this->cursorY = $this->page->getHeight() - $topMargin;
            }

            $this->page->addText(
                text: $markerRenderer($index),
                x: $this->x,
                y: $this->cursorY,
                baseFont: $baseFont,
                size: $size,
                tag: $tag,
                color: $markerColor,
                opacity: $opacity,
            );

            $this->flowParagraph(
                text: $item,
                x: $this->x + $markerIndent,
                width: $this->width - $markerIndent,
                baseFont: $baseFont,
                size: $size,
                tag: $tag,
                lineHeight: $lineHeight,
                spacingAfter: $index === array_key_last($items) ? $spacingAfter : $itemSpacing,
                color: $color,
                opacity: $opacity,
            );
        }

        return $this;
    }

    /**
     * @param string|list<TextSegment> $text
     */
    private function flowParagraph(
        string | array $text,
        float $x,
        float $width,
        string $baseFont,
        int $size,
        ?string $tag = null,
        ?float $lineHeight = null,
        ?float $spacingAfter = null,
        ?Color $color = null,
        ?Opacity $opacity = null,
        HorizontalAlign $align = HorizontalAlign::LEFT,
        ?int $maxLines = null,
        TextOverflow $overflow = TextOverflow::CLIP,
    ): self {
        $lineHeight ??= $size * 1.2;
        $spacingAfter ??= $lineHeight;

        $this->page = $this->page->addParagraph(
            text: $text,
            x: $x,
            y: $this->cursorY,
            maxWidth: $width,
            baseFont: $baseFont,
            size: $size,
            tag: $tag,
            lineHeight: $lineHeight,
            bottomMargin: $this->bottomMargin,
            color: $color,
            opacity: $opacity,
            align: $align,
            maxLines: $maxLines,
            overflow: $overflow,
        );

        $lineCount = $this->page->countParagraphLines($text, $baseFont, $size, $width, $maxLines, $overflow);
        $consumedHeight = ($lineCount * $lineHeight) + $spacingAfter;
        $topMargin = $this->page->getHeight() - $this->cursorY;
        $availableHeight = $this->page->getHeight() - $topMargin - $this->bottomMargin;

        if ($availableHeight > 0) {
            $pagesAdvanced = (int) floor(max(0.0, $consumedHeight - 0.00001) / $availableHeight);
            $remainingHeight = $consumedHeight - ($pagesAdvanced * $availableHeight);
            $this->cursorY = ($this->page->getHeight() - $topMargin) - $remainingHeight;
        } else {
            $this->cursorY -= $consumedHeight;
        }

        if ($this->cursorY < $this->bottomMargin) {
            $this->page = $this->page->getDocument()->addPage($this->page->getWidth(), $this->page->getHeight());
            $this->cursorY = $this->page->getHeight() - $topMargin;
        }

        return $this;
    }

    /**
     * @param string|list<TextSegment> $text
     */
    public function heading(
        string | array $text,
        string $baseFont,
        int $size,
        ?string $tag = null,
        ?float $spacingAfter = null,
        ?Color $color = null,
        ?Opacity $opacity = null,
        HorizontalAlign $align = HorizontalAlign::LEFT,
        ?int $maxLines = null,
        TextOverflow $overflow = TextOverflow::CLIP,
    ): self {
        return $this->paragraph(
            text: $text,
            baseFont: $baseFont,
            size: $size,
            tag: $tag,
            lineHeight: $size * 1.2,
            spacingAfter: $spacingAfter ?? $size * 0.8,
            color: $color,
            opacity: $opacity,
            align: $align,
            maxLines: $maxLines,
            overflow: $overflow,
        );
    }

    public function spacer(float $height): self
    {
        $this->cursorY -= $height;

        if ($this->cursorY < $this->bottomMargin) {
            $topMargin = $this->page->getHeight() - ($this->cursorY + $height);
            $this->page = $this->page->getDocument()->addPage($this->page->getWidth(), $this->page->getHeight());
            $this->cursorY = $this->page->getHeight() - $topMargin;
        }

        return $this;
    }

    public function getPage(): Page
    {
        return $this->page;
    }

    public function getCursorY(): float
    {
        return $this->cursorY;
    }
}
