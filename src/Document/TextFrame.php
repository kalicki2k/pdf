<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use Kalle\Pdf\Graphics\Color;
use Kalle\Pdf\Graphics\Opacity;

final class TextFrame
{
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
        string|array $text,
        string $baseFont,
        int $size,
        ?string $tag = null,
        ?float $lineHeight = null,
        ?float $spacingAfter = null,
        ?Color $color = null,
        ?Opacity $opacity = null,
    ): self {
        $lineHeight ??= $size * 1.2;
        $spacingAfter ??= $lineHeight;

        $this->page = $this->page->addParagraph(
            text: $text,
            x: $this->x,
            y: $this->cursorY,
            maxWidth: $this->width,
            baseFont: $baseFont,
            size: $size,
            tag: $tag,
            lineHeight: $lineHeight,
            bottomMargin: $this->bottomMargin,
            color: $color,
            opacity: $opacity,
        );

        $lineCount = $this->page->countParagraphLines($text, $baseFont, $size, $this->width);
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
        string|array $text,
        string $baseFont,
        int $size,
        ?string $tag = null,
        ?float $spacingAfter = null,
        ?Color $color = null,
        ?Opacity $opacity = null,
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
