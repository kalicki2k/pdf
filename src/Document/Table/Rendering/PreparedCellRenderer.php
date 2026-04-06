<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document\Table\Rendering;

use Kalle\Pdf\Document\Page;
use Kalle\Pdf\Document\Table\Layout\CellLayoutResolver;
use Kalle\Pdf\Document\Table\Layout\PreparedTableCell;
use Kalle\Pdf\Document\Table\Style\HeaderStyle;
use Kalle\Pdf\Document\Table\Style\RowStyle;
use Kalle\Pdf\Document\Table\Style\TableStyle;
use Kalle\Pdf\Document\Table\Support\TableTextMetrics;
use Kalle\Pdf\Document\Table\Support\ResolvedTableCellStyle;
use Kalle\Pdf\Document\Table\Support\TableStyleResolver;
use Kalle\Pdf\Document\Text\TextSegment;

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
    ): Page {
        return $this->renderResolved(
            $page,
            $preparedCell,
            $this->styleResolver->resolveCellStyle($tableStyle, $rowStyle, $headerStyle, $preparedCell->cell, $header),
            $rowIndex,
            $rowHeights,
            $rowTopY,
            $lineHeight,
            $baseFont,
            $fontSize,
            $tableStyle,
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

        if ($allLines === []) {
            return new CellRenderResult($page, []);
        }

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
                null,
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
            null,
            $lineHeight,
            $layout->bottomLimitY,
            $resolvedStyle->horizontalAlign,
        );

        return new CellRenderResult($page, $remainingLines);
    }
}
