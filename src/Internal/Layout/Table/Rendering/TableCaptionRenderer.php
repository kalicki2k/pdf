<?php

declare(strict_types=1);

namespace Kalle\Pdf\Internal\Layout\Table\Rendering;

use Kalle\Pdf\Internal\Layout\Table\Definition\TableCaption;
use Kalle\Pdf\Internal\Page\Page;
use Kalle\Pdf\Internal\TaggedPdf\StructElem;
use Kalle\Pdf\Internal\TaggedPdf\StructureTag;
use Kalle\Pdf\Text\TextSegment;

/**
 * @internal Renders table captions and resolves any required page break.
 */
final class TableCaptionRenderer
{
    public function render(
        TableCaption $caption,
        Page $page,
        float $cursorY,
        float $topMargin,
        float $bottomMargin,
        float $x,
        float $width,
        float $firstRowHeight,
        string $baseFont,
        int $fontSize,
        float $lineHeightFactor,
        ?StructElem $tableStructElem,
    ): TableCaptionRenderResult {
        [$captionLines, $captionFont, $captionSize, $captionLineHeight] = $this->resolveLayout(
            $caption,
            $page,
            $width,
            $baseFont,
            $fontSize,
            $lineHeightFactor,
        );
        $captionHeight = (count($captionLines) * $captionLineHeight) + $caption->spacingAfter;
        $availableHeight = $cursorY - $bottomMargin;

        if ($captionHeight > $availableHeight || ($captionHeight + $firstRowHeight) > $availableHeight) {
            $page = $page->getDocument()->addPage($page->getWidth(), $page->getHeight());
            $cursorY = $page->getHeight() - $topMargin;
        }

        $captionStructElem = $this->createCaptionStructElem($page, $tableStructElem);
        $page = $page->renderParagraphLines(
            $captionLines,
            $x,
            $cursorY,
            $width,
            $captionFont,
            $captionSize,
            $captionStructElem !== null ? StructureTag::Paragraph : null,
            $captionStructElem,
            $captionLineHeight,
            $bottomMargin,
        );
        $cursorY -= (count($captionLines) * $captionLineHeight) + $caption->spacingAfter;

        return new TableCaptionRenderResult($page, $cursorY);
    }

    /**
     * @return array{0: list<array{segments: array<int, TextSegment>, justify: bool}>, 1: string, 2: int, 3: float}
     */
    private function resolveLayout(
        TableCaption $caption,
        Page $page,
        float $width,
        string $baseFont,
        int $fontSize,
        float $lineHeightFactor,
    ): array {
        $captionFont = $caption->fontName ?? $baseFont;
        $captionSize = $caption->size ?? $fontSize;
        $captionLineHeight = $captionSize * $lineHeightFactor;

        return [
            $page->layoutParagraphLines(
                $caption->text,
                $captionFont,
                $captionSize,
                $width,
                $caption->color,
            ),
            $captionFont,
            $captionSize,
            $captionLineHeight,
        ];
    }

    private function createCaptionStructElem(Page $page, ?StructElem $tableStructElem): ?StructElem
    {
        if ($tableStructElem === null) {
            return null;
        }

        return $page->getDocument()->createStructElem(StructureTag::Caption, parent: $tableStructElem);
    }
}
