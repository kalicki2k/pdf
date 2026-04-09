<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document\Text;

use Kalle\Pdf\Document\Geometry\Position;
use Kalle\Pdf\Document\Geometry\Rect;
use Kalle\Pdf\Document\Page;
use Kalle\Pdf\Document\PageFonts;
use Kalle\Pdf\Document\PageGraphics;
use Kalle\Pdf\Document\PageLinks;
use Kalle\Pdf\Document\PageMarkedContentIds;
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
    private readonly PageTextElementRenderer $textElementRenderer;
    private readonly PageParagraphRenderer $paragraphRenderer;

    public function __construct(
        Page $page,
        PageFonts $pageFonts,
        PageLinks $pageLinks,
        PageGraphics $pageGraphics,
        PageMarkedContentIds $pageMarkedContentIds,
        TextLayoutEngine $textLayoutEngine,
    ) {
        $this->textElementRenderer = new PageTextElementRenderer(
            $page,
            $pageFonts,
            $pageLinks,
            $pageGraphics,
            $pageMarkedContentIds,
        );
        $this->paragraphRenderer = new PageParagraphRenderer(
            $textLayoutEngine,
            new PageTextBlockRenderer(
                $page,
                new PageTextLineRenderer($pageFonts, $textLayoutEngine),
            ),
        );
    }

    public function addText(
        string $text,
        Position $position,
        string $fontName = 'Helvetica',
        int $size = 12,
        TextOptions $options = new TextOptions(),
    ): Page {
        return $this->textElementRenderer->render($text, $position, $fontName, $size, $options);
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
        return $this->paragraphRenderer->addFlowText($text, $position, $maxWidth, $fontName, $size, $options);
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
        return $this->paragraphRenderer->addTextBox($text, $box, $fontName, $size, $options);
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
        return $this->paragraphRenderer->layoutParagraphLines($text, $baseFont, $size, $maxWidth, $color, $opacity, $maxLines, $overflow);
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
        return $this->paragraphRenderer->renderParagraphLines(
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
        return $this->paragraphRenderer->countParagraphLines($text, $baseFont, $size, $maxWidth, $maxLines, $overflow);
    }

}
