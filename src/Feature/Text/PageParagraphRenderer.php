<?php

declare(strict_types=1);

namespace Kalle\Pdf\Feature\Text;

use InvalidArgumentException;
use Kalle\Pdf\Geometry\Position;
use Kalle\Pdf\Geometry\Rect;
use Kalle\Pdf\Graphics\Color;
use Kalle\Pdf\Graphics\Opacity;
use Kalle\Pdf\Internal\Page\Page;
use Kalle\Pdf\Internal\Page\Resources\PageFonts;
use Kalle\Pdf\Layout\HorizontalAlign;
use Kalle\Pdf\Layout\TextOverflow;
use Kalle\Pdf\Structure\StructElem;

/**
 * @internal Coordinates multi-line text layout and rendering for a page.
 */
final class PageParagraphRenderer
{
    private const float DEFAULT_LINE_HEIGHT_FACTOR = 1.2;
    private const float DEFAULT_BOTTOM_MARGIN = 20.0;

    public function __construct(
        private readonly TextLayoutEngine $textLayoutEngine,
        private readonly PageTextBlockRenderer $blockRenderer,
    ) {
    }

    public static function forPage(Page $page, PageFonts $pageFonts): self
    {
        $textLayoutEngine = TextLayoutEngine::forPageFonts($pageFonts);

        return new self(
            $textLayoutEngine,
            new PageTextBlockRenderer(
                $page,
                new PageTextLineRenderer($pageFonts, $textLayoutEngine),
            ),
        );
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
        $layout = FlowTextLayout::fromOptions(
            $maxWidth,
            $size,
            $options,
            self::DEFAULT_LINE_HEIGHT_FACTOR,
            self::DEFAULT_BOTTOM_MARGIN,
        );

        $lines = $this->layoutParagraphLines(
            $text,
            $fontName,
            $size,
            $maxWidth,
            $options->color,
            $options->opacity,
            $layout->maxLines,
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
            $layout->lineHeight,
            $layout->bottomMargin,
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
        $layout = TextBoxLayout::fromOptions(
            $box,
            $size,
            $options,
            self::DEFAULT_LINE_HEIGHT_FACTOR,
        );

        $lines = $this->layoutParagraphLines(
            $text,
            $fontName,
            $size,
            $layout->contentWidth,
            $options->color,
            $options->opacity,
            $layout->maxLines,
            $options->overflow,
        );

        return $this->blockRenderer->renderTextLines(
            $lines,
            $layout->contentX,
            $layout->resolveStartY($size, count($lines)),
            $layout->contentWidth,
            $fontName,
            $size,
            $options->structureTag,
            $options->parentStructElem,
            $layout->lineHeight,
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
}
