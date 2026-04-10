<?php

declare(strict_types=1);

namespace Kalle\Pdf\Internal\Layout\Table\Rendering;

use Kalle\Pdf\Internal\Layout\Table\Layout\CellLayoutResolver;
use Kalle\Pdf\Internal\Layout\Table\Layout\PreparedTableCell;
use Kalle\Pdf\Internal\Layout\Table\Style\FooterStyle;
use Kalle\Pdf\Internal\Layout\Table\Style\HeaderStyle;
use Kalle\Pdf\Internal\Layout\Table\Style\RowStyle;
use Kalle\Pdf\Internal\Layout\Table\Style\TableStyle;
use Kalle\Pdf\Internal\Layout\Table\Support\ResolvedTableCellStyle;
use Kalle\Pdf\Internal\Layout\Table\Support\TableStyleResolver;
use Kalle\Pdf\Internal\Layout\Table\Support\TableTextMetrics;
use Kalle\Pdf\Internal\Layout\Text\Input\TextSegment;
use Kalle\Pdf\Page;
use Kalle\Pdf\TaggedPdf\StructElem;
use Kalle\Pdf\TaggedPdf\StructureTag;

final readonly class PreparedCellRenderer
{
    public function __construct(
        private TableStyleResolver $styleResolver,
        private CellLayoutResolver $cellLayoutResolver,
        private CellBoxRenderer $cellBoxRenderer,
        private TableTextMetrics $textMetrics,
    ) {
    }

    /**
     * @param list<float> $rowHeights
     */
    public function render(
        Page $page,
        PreparedTableCell $preparedCell,
        bool $header,
        int $rowIndex,
        array $rowHeights,
        float $rowTopY,
        float $lineHeight,
        TableStyle $tableStyle,
        ?RowStyle $rowStyle,
        ?HeaderStyle $headerStyle,
        string $baseFont,
        int $fontSize,
        ?StructElem $parentStructElem = null,
        ?FooterStyle $footerStyle = null,
        bool $footer = false,
    ): Page {
        return $this->renderResolved(
            $page,
            $preparedCell,
            $this->styleResolver->resolveCellStyle(
                $tableStyle,
                $rowStyle,
                $headerStyle,
                $preparedCell->cell,
                $header,
                $footerStyle,
                $footer,
            ),
            $rowIndex,
            $rowHeights,
            $rowTopY,
            $lineHeight,
            $baseFont,
            $fontSize,
            $tableStyle,
            parentStructElem: $parentStructElem,
        )->page;
    }

    /**
     * @param list<float> $rowHeights
     */
    public function renderSegment(
        Page $page,
        PreparedTableCell $preparedCell,
        ResolvedTableCellStyle $resolvedStyle,
        int $rowIndex,
        array $rowHeights,
        float $rowTopY,
        float $lineHeight,
        string $baseFont,
        int $fontSize,
        TableStyle $tableStyle,
        ?CellRenderOptions $options = null,
        ?StructElem $parentStructElem = null,
    ): CellRenderResult {
        $options ??= new CellRenderOptions();

        return $this->renderResolved(
            $page,
            $preparedCell,
            $resolvedStyle,
            $rowIndex,
            $rowHeights,
            $rowTopY,
            $lineHeight,
            $baseFont,
            $fontSize,
            $tableStyle,
            $options,
            $parentStructElem,
        );
    }

    /**
     * @param list<float> $rowHeights
     */
    private function renderResolved(
        Page $page,
        PreparedTableCell $preparedCell,
        ResolvedTableCellStyle $resolvedStyle,
        int $rowIndex,
        array $rowHeights,
        float $rowTopY,
        float $lineHeight,
        string $baseFont,
        int $fontSize,
        TableStyle $tableStyle,
        CellRenderOptions $options = new CellRenderOptions(),
        ?StructElem $parentStructElem = null,
    ): CellRenderResult {
        $visibleRowspan = $options->visibleRowspan ?? $preparedCell->cell->rowspan;
        $layout = $this->cellLayoutResolver->resolve(
            $preparedCell,
            $rowIndex,
            $rowHeights,
            $rowTopY,
            $resolvedStyle->verticalAlign,
            $fontSize,
            $visibleRowspan,
        );

        $this->cellBoxRenderer->render(
            $page,
            $layout->x,
            $layout->bottomY,
            $layout->width,
            $layout->height,
            $resolvedStyle->fillColor,
            $tableStyle->border,
            $resolvedStyle->rowBorder,
            $resolvedStyle->cellBorder,
            $options->renderTopBorder,
            true,
            $options->renderBottomBorder,
            true,
        );

        if (!$options->renderText) {
            return new CellRenderResult($page, $options->remainingLines);
        }

        $availableTextHeight = $layout->height - $resolvedStyle->padding->vertical();

        if ($availableTextHeight <= 0) {
            return new CellRenderResult($page, $options->remainingLines);
        }

        $maxLines = $this->textMetrics->resolveFittingLineCount($availableTextHeight, $lineHeight, $fontSize);
        $allLines = $options->remainingLines !== []
            ? $options->remainingLines
            : $page->layoutParagraphLines(
                $preparedCell->cell->text,
                $baseFont,
                $fontSize,
                $layout->textWidth,
                $resolvedStyle->textColor,
                $resolvedStyle->opacity,
            );

        if ($options->remainingLines === [] && $visibleRowspan < $preparedCell->cell->rowspan && count($allLines) > 1 && $maxLines < 2) {
            return new CellRenderResult($page, $allLines);
        }

        if ($visibleRowspan === $preparedCell->cell->rowspan && count($allLines) <= $maxLines) {
            $page = $page->renderParagraphLines(
                $allLines,
                $layout->textX,
                $layout->textY,
                $layout->textWidth,
                $baseFont,
                $fontSize,
                $parentStructElem !== null ? StructureTag::Paragraph : null,
                $parentStructElem,
                $lineHeight,
                $layout->bottomLimitY,
                $resolvedStyle->horizontalAlign,
            );

            return new CellRenderResult($page, []);
        }

        $visibleLines = array_slice($allLines, 0, $maxLines);
        $remainingLines = array_slice($allLines, $maxLines);
        $page = $page->renderParagraphLines(
            $visibleLines,
            $layout->textX,
            $rowTopY - $resolvedStyle->padding->top - $fontSize,
            $layout->textWidth,
            $baseFont,
            $fontSize,
            $parentStructElem !== null ? StructureTag::Paragraph : null,
            $parentStructElem,
            $lineHeight,
            $layout->bottomLimitY,
            $resolvedStyle->horizontalAlign,
        );

        return new CellRenderResult($page, $remainingLines);
    }
}
