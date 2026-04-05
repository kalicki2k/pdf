<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document\Text;

use InvalidArgumentException;
use Kalle\Pdf\Document\Geometry\Position;
use Kalle\Pdf\Document\Page;
use Kalle\Pdf\Graphics\Color;
use Kalle\Pdf\Graphics\Opacity;
use Kalle\Pdf\Layout\BulletType;
use Kalle\Pdf\Layout\HorizontalAlign;
use Kalle\Pdf\Layout\TextOverflow;

final class TextFrame
{
    private const DEFAULT_BULLET_INDENT = 14.0;
    private const DEFAULT_TEXT_SPACING_FACTOR = 1.2;

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

    public function addText(
        string $text,
        string $fontName,
        int $size,
        TextOptions $options = new TextOptions(),
        ?float $spacingAfter = null,
    ): self {
        $spacingAfter ??= $size * self::DEFAULT_TEXT_SPACING_FACTOR;

        if ($this->cursorY < $this->bottomMargin + $size) {
            $topMargin = $this->page->getHeight() - $this->cursorY;
            $this->page = $this->page->getDocument()->addPage($this->page->getWidth(), $this->page->getHeight());
            $this->cursorY = $this->page->getHeight() - $topMargin;
        }

        $this->page->addText(
            text: $text,
            position: new Position($this->x, $this->cursorY),
            fontName: $fontName,
            size: $size,
            options: $options,
        );

        $this->cursorY -= $spacingAfter;

        if ($this->cursorY < $this->bottomMargin) {
            $topMargin = $this->page->getHeight() - ($this->cursorY + $spacingAfter);
            $this->page = $this->page->getDocument()->addPage($this->page->getWidth(), $this->page->getHeight());
            $this->cursorY = $this->page->getHeight() - $topMargin;
        }

        return $this;
    }

    /**
     * @param string|list<TextSegment> $text
     */
    public function addParagraph(
        string | array $text,
        string $fontName,
        int $size,
        ParagraphOptions $options = new ParagraphOptions(),
    ): self {
        $lineHeight = $options->lineHeight ?? $size * 1.2;
        $spacingAfter = $options->spacingAfter ?? $lineHeight;

        return $this->flowParagraph(
            text: $text,
            x: $this->x,
            width: $this->width,
            fontName: $fontName,
            size: $size,
            options: new ParagraphOptions(
                structureTag: $options->structureTag,
                lineHeight: $lineHeight,
                spacingAfter: $spacingAfter,
                color: $options->color,
                opacity: $options->opacity,
                align: $options->align,
                maxLines: $options->maxLines,
                overflow: $options->overflow,
            ),
            spacingAfter: $spacingAfter,
        );
    }

    /**
     * @param list<string|list<TextSegment>> $items
     */
    public function addBulletList(
        array $items,
        string $fontName,
        int $size,
        BulletType $bulletType = BulletType::DISC,
        ListOptions $options = new ListOptions(),
    ): self {
        $lineHeight = $options->lineHeight ?? $size * 1.2;
        $spacingAfter = $options->spacingAfter ?? $lineHeight;
        $itemSpacing = $options->itemSpacing ?? $size * 0.4;
        $markerIndent = $options->markerIndent ?? self::DEFAULT_BULLET_INDENT;

        if ($items === []) {
            return $this;
        }

        if ($markerIndent <= 0) {
            throw new InvalidArgumentException('Bullet indent must be greater than zero.');
        }

        if ($this->width <= $markerIndent) {
            throw new InvalidArgumentException('Bullet indent must be smaller than the text frame width.');
        }

        return $this->renderList(
            items: $items,
            markerRenderer: static fn (int $index): string => $bulletType->value,
            fontName: $fontName,
            size: $size,
            options: new ListOptions(
                structureTag: $options->structureTag,
                lineHeight: $lineHeight,
                spacingAfter: $spacingAfter,
                itemSpacing: $itemSpacing,
                color: $options->color,
                opacity: $options->opacity,
                markerColor: $options->markerColor ?? $options->color,
                markerIndent: $markerIndent,
            ),
        );
    }

    /**
     * @param list<string|list<TextSegment>> $items
     */
    public function addNumberedList(
        array $items,
        string $fontName,
        int $size,
        int $startAt = 1,
        ListOptions $options = new ListOptions(),
    ): self {
        $lineHeight = $options->lineHeight ?? $size * 1.2;
        $spacingAfter = $options->spacingAfter ?? $lineHeight;
        $itemSpacing = $options->itemSpacing ?? $size * 0.4;
        $markerIndent = $options->markerIndent ?? self::DEFAULT_BULLET_INDENT;

        if ($items === []) {
            return $this;
        }

        if ($markerIndent <= 0) {
            throw new InvalidArgumentException('Number indent must be greater than zero.');
        }

        if ($this->width <= $markerIndent) {
            throw new InvalidArgumentException('Number indent must be smaller than the text frame width.');
        }

        if ($startAt <= 0) {
            throw new InvalidArgumentException('Numbered lists must start at 1 or greater.');
        }

        return $this->renderList(
            items: $items,
            markerRenderer: static fn (int $index): string => ($startAt + $index) . '.',
            fontName: $fontName,
            size: $size,
            options: new ListOptions(
                structureTag: $options->structureTag,
                lineHeight: $lineHeight,
                spacingAfter: $spacingAfter,
                itemSpacing: $itemSpacing,
                color: $options->color,
                opacity: $options->opacity,
                markerColor: $options->markerColor ?? $options->color,
                markerIndent: $markerIndent,
            ),
        );
    }

    /**
     * @param list<string|list<TextSegment>> $items
     * @param \Closure(int): string $markerRenderer
     */
    private function renderList(
        array $items,
        \Closure $markerRenderer,
        string $fontName,
        int $size,
        ListOptions $options,
    ): self {
        $lineHeight = $options->lineHeight ?? $size * 1.2;
        $spacingAfter = $options->spacingAfter ?? $lineHeight;
        $itemSpacing = $options->itemSpacing ?? $size * 0.4;
        $markerIndent = $options->markerIndent ?? self::DEFAULT_BULLET_INDENT;

        foreach ($items as $index => $item) {
            if ($this->cursorY < $this->bottomMargin + $lineHeight) {
                $topMargin = $this->page->getHeight() - $this->cursorY;
                $this->page = $this->page->getDocument()->addPage($this->page->getWidth(), $this->page->getHeight());
                $this->cursorY = $this->page->getHeight() - $topMargin;
            }

            $this->page->addText(
                text: $markerRenderer($index),
                position: new Position($this->x, $this->cursorY),
                fontName: $fontName,
                size: $size,
                options: new TextOptions(
                    structureTag: $options->structureTag,
                    color: $options->markerColor,
                    opacity: $options->opacity,
                ),
            );

            $this->flowParagraph(
                text: $item,
                x: $this->x + $markerIndent,
                width: $this->width - $markerIndent,
                fontName: $fontName,
                size: $size,
                options: new ParagraphOptions(
                    structureTag: $options->structureTag,
                    lineHeight: $lineHeight,
                    spacingAfter: $index === array_key_last($items) ? $spacingAfter : $itemSpacing,
                    color: $options->color,
                    opacity: $options->opacity,
                ),
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
        string $fontName,
        int $size,
        ?ParagraphOptions $options = null,
        ?float $spacingAfter = null,
    ): self {
        $options ??= new ParagraphOptions();
        $lineHeight = $options->lineHeight ?? $size * 1.2;
        $spacingAfter ??= $options->spacingAfter ?? $lineHeight;

        $this->page = $this->page->addFlowText(
            text: $text,
            x: $x,
            y: $this->cursorY,
            maxWidth: $width,
            fontName: $fontName,
            size: $size,
            options: new FlowTextOptions(
                structureTag: $options->structureTag,
                lineHeight: $lineHeight,
                bottomMargin: $this->bottomMargin,
                color: $options->color,
                opacity: $options->opacity,
                align: $options->align,
                maxLines: $options->maxLines,
                overflow: $options->overflow,
            ),
        );

        $lineCount = $this->page->countParagraphLines($text, $fontName, $size, $width, $options->maxLines, $options->overflow);
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
    public function addHeading(
        string | array $text,
        string $fontName,
        int $size,
        ParagraphOptions $options = new ParagraphOptions(),
    ): self {
        return $this->addParagraph(
            text: $text,
            fontName: $fontName,
            size: $size,
            options: new ParagraphOptions(
                structureTag: $options->structureTag,
                lineHeight: $size * 1.2,
                spacingAfter: $options->spacingAfter ?? $size * 0.8,
                color: $options->color,
                opacity: $options->opacity,
                align: $options->align,
                maxLines: $options->maxLines,
                overflow: $options->overflow,
            ),
        );
    }

    public function addSpacer(float $height): self
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
