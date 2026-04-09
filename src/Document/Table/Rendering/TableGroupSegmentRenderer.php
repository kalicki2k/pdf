<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document\Table\Rendering;

use Kalle\Pdf\Document\Page;
use Kalle\Pdf\Document\Table\Layout\PreparedTableCell;
use Kalle\Pdf\Document\Table\Layout\PreparedTableRow;
use Kalle\Pdf\Document\Table\PendingRowspanCell;
use Kalle\Pdf\Document\Table\Style\FooterStyle;
use Kalle\Pdf\Document\Table\Style\HeaderStyle;
use Kalle\Pdf\Document\Table\Style\RowStyle;
use Kalle\Pdf\Document\Table\Style\TableStyle;
use Kalle\Pdf\Document\Table\Support\ResolvedTableCellStyle;
use Kalle\Pdf\Document\Table\Support\TableStyleResolver;
use Kalle\Pdf\Document\Table\TableCell;
use Kalle\Pdf\Document\Text\TextSegment;
use Kalle\Pdf\Structure\StructElem;

/**
 * @internal Renders table row-group segments and tracks rowspan continuations across page breaks.
 */
final class TableGroupSegmentRenderer
{
    public function __construct(
        private readonly TableStyleResolver $styleResolver,
        private readonly TableStructElemFactory $structElemFactory = new TableStructElemFactory(),
    ) {
    }

    /**
     * @param list<PreparedTableRow> $preparedRows
     * @param list<float> $rowHeights
     * @param list<PendingRowspanCell> $pendingRowspanCells
     */
    public function render(
        Page $page,
        array $preparedRows,
        array $rowHeights,
        int $rowCount,
        float $cursorY,
        array $pendingRowspanCells,
        TableRenderContext $context,
    ): TableGroupSegmentRenderResult {
        $lineHeight = $context->lineHeight();
        $rowTopY = $cursorY;
        $segmentRowHeights = array_slice($rowHeights, 0, $rowCount);
        /** @var list<list<array{segments: array<int, TextSegment>, justify: bool}>> $continuationLines */
        $continuationLines = [];

        foreach ($pendingRowspanCells as $pendingRowspanCell) {
            $result = $this->renderPendingRowspanCellSegment(
                $page,
                $pendingRowspanCell,
                $rowCount,
                $segmentRowHeights,
                $rowTopY,
                $lineHeight,
                $context->baseFont,
                $context->fontSize,
                $context->style,
                $context->preparedCellRenderer,
            );
            $page = $result->page;
            $continuationLines[] = $result->remainingLines;
        }

        for ($rowIndex = 0; $rowIndex < $rowCount; $rowIndex++) {
            $preparedRow = $preparedRows[$rowIndex];
            $rowStructElem = $this->structElemFactory->createRow($page, $context->tableStructElem);

            foreach ($preparedRow->cells as $preparedCell) {
                $result = $this->renderPreparedCellSegment(
                    $page,
                    $preparedCell,
                    $preparedRow->header,
                    $preparedRow->footer,
                    $rowIndex,
                    $rowCount,
                    $segmentRowHeights,
                    $rowTopY,
                    $lineHeight,
                    $context->style,
                    $context->rowStyle,
                    $context->headerStyle,
                    $context->footerStyle,
                    $context->baseFont,
                    $context->fontSize,
                    $context->preparedCellRenderer,
                    $this->structElemFactory->createCell($page, $preparedCell->cell, $preparedRow->header, $rowStructElem),
                );
                $page = $result->page;
                $continuationLines[] = $result->remainingLines;
            }

            $rowTopY -= $segmentRowHeights[$rowIndex];
        }

        return new TableGroupSegmentRenderResult(
            $page,
            $rowTopY,
            $this->buildPendingRowspanContinuations(
                $preparedRows,
                $rowCount,
                $pendingRowspanCells,
                $continuationLines,
                $context->style,
                $context->rowStyle,
                $context->headerStyle,
                $context->footerStyle,
            ),
        );
    }

    /**
     * @param list<float> $rowHeights
     */
    private function renderPendingRowspanCellSegment(
        Page $page,
        PendingRowspanCell $pendingRowspanCell,
        int $rowCount,
        array $rowHeights,
        float $rowTopY,
        float $lineHeight,
        string $baseFont,
        int $fontSize,
        TableStyle $style,
        PreparedCellRenderer $preparedCellRenderer,
        ?StructElem $cellStructElem = null,
    ): CellRenderResult {
        $visibleRowspan = min($pendingRowspanCell->remainingRows, $rowCount);

        return $preparedCellRenderer->renderSegment(
            $page,
            $pendingRowspanCell->cell,
            $pendingRowspanCell->style,
            0,
            $rowHeights,
            $rowTopY,
            $lineHeight,
            $baseFont,
            $fontSize,
            $style,
            new CellRenderOptions(
                visibleRowspan: $visibleRowspan,
                renderText: $pendingRowspanCell->remainingLines !== [],
                renderTopBorder: true,
                remainingLines: $pendingRowspanCell->remainingLines,
            ),
            $cellStructElem,
        );
    }

    /**
     * @param list<float> $rowHeights
     */
    private function renderPreparedCellSegment(
        Page $page,
        PreparedTableCell $preparedCell,
        bool $header,
        bool $footer,
        int $rowIndex,
        int $rowCount,
        array $rowHeights,
        float $rowTopY,
        float $lineHeight,
        TableStyle $style,
        ?RowStyle $rowStyle,
        ?HeaderStyle $headerStyle,
        ?FooterStyle $footerStyle,
        string $baseFont,
        int $fontSize,
        PreparedCellRenderer $preparedCellRenderer,
        ?StructElem $cellStructElem = null,
    ): CellRenderResult {
        $visibleRowspan = min($preparedCell->cell->rowspan, $rowCount - $rowIndex);

        return $preparedCellRenderer->renderSegment(
            $page,
            $preparedCell,
            $this->resolveEffectiveCellStyle($preparedCell->cell, $header, $footer, $style, $rowStyle, $headerStyle, $footerStyle),
            $rowIndex,
            $rowHeights,
            $rowTopY,
            $lineHeight,
            $baseFont,
            $fontSize,
            $style,
            new CellRenderOptions(
                visibleRowspan: $visibleRowspan,
            ),
            $cellStructElem,
        );
    }

    /**
     * @param list<PreparedTableRow> $preparedRows
     * @param list<PendingRowspanCell> $pendingRowspanCells
     * @param list<list<array{segments: array<int, TextSegment>, justify: bool}>> $continuationLines
     * @return list<PendingRowspanCell>
     */
    private function buildPendingRowspanContinuations(
        array $preparedRows,
        int $renderedRowCount,
        array $pendingRowspanCells,
        array $continuationLines,
        TableStyle $style,
        ?RowStyle $rowStyle,
        ?HeaderStyle $headerStyle,
        ?FooterStyle $footerStyle,
    ): array {
        $continuations = [];
        $continuationIndex = 0;

        foreach ($pendingRowspanCells as $pendingRowspanCell) {
            $remainingRows = $pendingRowspanCell->remainingRows - $renderedRowCount;
            $remainingLines = $continuationLines[$continuationIndex] ?? [];
            $continuationIndex++;

            if ($remainingRows > 0) {
                $continuations[] = new PendingRowspanCell(
                    $pendingRowspanCell->cell,
                    $pendingRowspanCell->style,
                    $remainingRows,
                    $remainingLines,
                );
            }
        }

        foreach ($preparedRows as $rowIndex => $preparedRow) {
            if ($rowIndex >= $renderedRowCount) {
                break;
            }

            foreach ($preparedRow->cells as $preparedCell) {
                $visibleRowspan = min($preparedCell->cell->rowspan, $renderedRowCount - $rowIndex);
                $remainingRows = $preparedCell->cell->rowspan - $visibleRowspan;
                $remainingLines = $continuationLines[$continuationIndex] ?? [];
                $continuationIndex++;

                if ($remainingRows <= 0) {
                    continue;
                }

                $continuations[] = new PendingRowspanCell(
                    $preparedCell,
                    $this->resolveEffectiveCellStyle(
                        $preparedCell->cell,
                        $preparedRow->header,
                        $preparedRow->footer,
                        $style,
                        $rowStyle,
                        $headerStyle,
                        $footerStyle,
                    ),
                    $remainingRows,
                    $remainingLines,
                );
            }
        }

        return $continuations;
    }

    private function resolveEffectiveCellStyle(
        TableCell $cell,
        bool $header,
        bool $footer,
        TableStyle $style,
        ?RowStyle $rowStyle,
        ?HeaderStyle $headerStyle,
        ?FooterStyle $footerStyle,
    ): ResolvedTableCellStyle {
        return $this->styleResolver->resolveCellStyle(
            $style,
            $rowStyle,
            $headerStyle,
            $cell,
            $header,
            $footerStyle,
            $footer,
        );
    }
}
