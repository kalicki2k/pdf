<?php

declare(strict_types=1);

namespace Kalle\Pdf\Layout\Text;

use Kalle\Pdf\Layout\Text\Input\TextSegment;
use Kalle\Pdf\Layout\Value\HorizontalAlign;
use Kalle\Pdf\Page;
use Kalle\Pdf\TaggedPdf\StructElem;
use Kalle\Pdf\TaggedPdf\StructureTag;

/**
 * @internal Renders multi-line text blocks onto one or more pages.
 */
final class PageTextBlockRenderer
{
    public function __construct(
        private readonly Page $page,
        private readonly PageTextLineRenderer $lineRenderer,
    ) {
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
        ?StructureTag $tag,
        ?StructElem $parentStructElem,
        float $lineHeight,
        float $bottomMargin,
        HorizontalAlign $align,
    ): Page {
        $page = $this->page;
        $currentY = $y;
        $topMargin = $this->page->getHeight() - $y;

        foreach ($lines as $line) {
            if ($currentY < $bottomMargin) {
                $page = $this->page->getDocument()->addPage($this->page->getWidth(), $this->page->getHeight());
                $currentY = $this->page->getHeight() - $topMargin;
            }

            $this->lineRenderer->render($page, $line, $x, $currentY, $maxWidth, $baseFont, $size, $tag, $parentStructElem, $align);
            $currentY -= $lineHeight;
        }

        return $page;
    }

    /**
     * @param list<array{segments: array<int, TextSegment>, justify: bool}> $lines
     */
    public function renderTextLines(
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
            $this->lineRenderer->render($this->page, $line, $x, $currentY, $maxWidth, $baseFont, $size, $tag, $parentStructElem, $align);
            $currentY -= $lineHeight;
        }

        return $this->page;
    }

}
