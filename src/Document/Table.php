<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use InvalidArgumentException;
use Kalle\Pdf\Graphics\Color;
use Kalle\Pdf\Graphics\Opacity;
use Kalle\Pdf\Layout\HorizontalAlign;
use Kalle\Pdf\Layout\VerticalAlign;
use Kalle\Pdf\Styles\CellStyle;
use Kalle\Pdf\Styles\HeaderStyle;
use Kalle\Pdf\Styles\RowStyle;
use Kalle\Pdf\Styles\TableBorder;
use Kalle\Pdf\Styles\TablePadding;
use Kalle\Pdf\Styles\TableStyle;

final class Table
{
    private const DEFAULT_LINE_HEIGHT_FACTOR = 1.2;
    private const CELL_BOTTOM_EPSILON = 0.01;
    private const DEFAULT_CONTINUATION_TOP_MARGIN = 40.0;

    /** @var list<list<string|list<TextSegment>|TableCell>> */
    private array $headerRows = [];
    /** @var list<int> */
    private array $activeRowspans = [];
    /** @var list<PreparedTableRow> */
    private array $pendingGroupRows = [];
    /** @var list<PendingRowspanCell> */
    private array $pendingRowspanCells = [];
    private readonly float $topMargin;
    private readonly float $continuationTopMargin;
    private Page $page;
    private float $cursorY;
    private string $baseFont = 'Helvetica';
    private int $fontSize = 12;
    private float $lineHeightFactor = self::DEFAULT_LINE_HEIGHT_FACTOR;
    private TableStyle $style;
    private ?RowStyle $rowStyle = null;
    private ?HeaderStyle $headerStyle = null;

    /**
     * @param list<float|int> $columnWidths
     */
    public function __construct(
        Page $page,
        private readonly float $x,
        float $y,
        float $width,
        private readonly array $columnWidths,
        private readonly float $bottomMargin = 20.0,
    ) {
        if ($width <= 0) {
            throw new InvalidArgumentException('Table width must be greater than zero.');
        }

        if ($columnWidths === []) {
            throw new InvalidArgumentException('Table requires at least one column.');
        }

        foreach ($columnWidths as $columnWidth) {
            if ((float) $columnWidth <= 0) {
                throw new InvalidArgumentException('Table column widths must be greater than zero.');
            }
        }

        $totalColumnWidth = array_sum(array_map(static fn (float | int $value): float => (float) $value, $columnWidths));

        if (abs($totalColumnWidth - $width) > 0.001) {
            throw new InvalidArgumentException('Table column widths must add up to the table width.');
        }

        if ($bottomMargin < 0) {
            throw new InvalidArgumentException('Table bottom margin must be zero or greater.');
        }

        $this->page = $page;
        $this->cursorY = $y;
        $this->topMargin = $page->getHeight() - $y;
        $this->continuationTopMargin = min($this->topMargin, self::DEFAULT_CONTINUATION_TOP_MARGIN);
        $this->activeRowspans = array_fill(0, count($columnWidths), 0);
        $this->style = new TableStyle(
            padding: TablePadding::all(6.0),
            border: TableBorder::all(color: Color::gray(0.75)),
            verticalAlign: VerticalAlign::TOP,
        );
        $this->headerStyle = new HeaderStyle(fillColor: Color::gray(0.92));
    }

    public function font(string $baseFont, int $size): self
    {
        if ($baseFont === '') {
            throw new InvalidArgumentException('Table base font must not be empty.');
        }

        if ($size <= 0) {
            throw new InvalidArgumentException('Table font size must be greater than zero.');
        }

        $this->baseFont = $baseFont;
        $this->fontSize = $size;

        return $this;
    }

    public function style(TableStyle $style): self
    {
        $this->style = new TableStyle(
            padding: $style->padding ?? $this->style->padding,
            border: $style->border ?? $this->style->border,
            verticalAlign: $style->verticalAlign ?? $this->style->verticalAlign,
            fillColor: $style->fillColor ?? $this->style->fillColor,
            textColor: $style->textColor ?? $this->style->textColor,
        );

        return $this;
    }

    public function rowStyle(RowStyle $style): self
    {
        $this->rowStyle = $this->mergeRowStyle($this->rowStyle, $style);

        return $this;
    }

    public function headerStyle(HeaderStyle $style): self
    {
        $this->headerStyle = $this->mergeHeaderStyle($this->headerStyle, $style);

        return $this;
    }

    /**
     * @param list<string|list<TextSegment>|TableCell> $cells
     */
    public function addRow(array $cells, bool $header = false): self
    {
        if ($header) {
            $this->headerRows[] = $cells;
        }

        $preparedRow = $this->prepareRow($cells, $header);
        $this->pendingGroupRows[] = new PreparedTableRow($preparedRow['cells'], $header);
        $this->activeRowspans = $preparedRow['nextRowspans'];

        if (!$this->hasActiveRowspans()) {
            $this->flushPendingGroup();
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

    private function flushPendingGroup(): void
    {
        if ($this->pendingGroupRows === []) {
            return;
        }

        $pendingGroupRows = $this->pendingGroupRows;
        $rowHeights = $this->resolvePendingGroupRowHeights($pendingGroupRows);
        $isBodyGroup = array_any(
            $pendingGroupRows,
            static fn (PreparedTableRow $row): bool => $row->header === false,
        );
        $remainingRows = $pendingGroupRows;
        $remainingRowHeights = $rowHeights;
        $deferredLeadingSplit = false;

        while ($remainingRows !== []) {
            $pageFit = $this->resolvePendingGroupPageFit($remainingRows, $remainingRowHeights, $isBodyGroup);
            $fittingRowCount = $pageFit->fittingRowCountOnCurrentPage;

            if ($fittingRowCount === 0) {
                $this->moveToNextPageForPendingGroup($pageFit);
                $pageFit = $this->resolvePendingGroupPageFit($remainingRows, $remainingRowHeights, false);
                $fittingRowCount = $pageFit->fittingRowCountOnCurrentPage;

                if ($fittingRowCount === 0) {
                    throw new InvalidArgumentException('Table rows must fit on a fresh page.');
                }
            }

            if (
                !$deferredLeadingSplit
                && $this->shouldDeferLeadingSplitToNextPage($remainingRows, $remainingRowHeights, $fittingRowCount)
            ) {
                $this->moveToNextPageForPendingGroup(new TableGroupPageFit(false, true, $isBodyGroup && $this->headerRows !== [], 0, 0));
                $deferredLeadingSplit = true;
                continue;
            }

            if ($fittingRowCount >= count($remainingRows) && $this->pendingRowspanCells === []) {
                $this->renderPendingGroup($remainingRows, $remainingRowHeights);
                break;
            }

            $this->renderPendingGroupSegment($remainingRows, $remainingRowHeights, $fittingRowCount);

            if ($fittingRowCount >= count($remainingRows)) {
                break;
            }

            $remainingRows = array_slice($remainingRows, $fittingRowCount);
            $remainingRowHeights = array_slice($remainingRowHeights, $fittingRowCount);
            $this->moveToNextPageForPendingGroup(new TableGroupPageFit(false, true, $isBodyGroup && $this->headerRows !== [], 0, 0));
        }

        $this->pendingGroupRows = [];
        $this->pendingRowspanCells = [];
    }

    /**
     * @param list<PreparedTableRow> $preparedRows
     * @param list<float> $rowHeights
     */
    private function resolvePendingGroupPageFit(array $preparedRows, array $rowHeights, bool $repeatHeaders): TableGroupPageFit
    {
        $groupHeight = array_sum($rowHeights);
        $remainingCurrentPageHeight = $this->cursorY - $this->bottomMargin;
        $fullPageAvailableHeight = $this->page->getHeight() - $this->topMargin - $this->bottomMargin;
        $headerRepeatHeight = 0.0;

        if ($repeatHeaders && $this->headerRows !== []) {
            $preparedHeaderRows = $this->prepareRowGroup($this->headerRows, true);
            $headerRepeatHeight = array_sum($this->resolvePendingGroupRowHeights($preparedHeaderRows));
        }

        $availableFreshPageHeight = $fullPageAvailableHeight - $headerRepeatHeight;

        return new TableGroupPageFit(
            fitsOnCurrentPage: $remainingCurrentPageHeight >= $groupHeight,
            fitsOnFreshPage: $availableFreshPageHeight >= $groupHeight,
            repeatHeaders: $repeatHeaders && $this->headerRows !== [],
            fittingRowCountOnCurrentPage: $this->countFittingRows($rowHeights, $remainingCurrentPageHeight),
            fittingRowCountOnFreshPage: $this->countFittingRows($rowHeights, $availableFreshPageHeight),
        );
    }

    private function moveToNextPageForPendingGroup(TableGroupPageFit $pageFit): void
    {
        $this->page = $this->page->getDocument()->addPage($this->page->getWidth(), $this->page->getHeight());
        $this->cursorY = $this->page->getHeight() - $this->continuationTopMargin;

        if (!$pageFit->repeatHeaders) {
            return;
        }

        $preparedHeaderRows = $this->prepareRowGroup($this->headerRows, true);
        $headerHeights = $this->resolvePendingGroupRowHeights($preparedHeaderRows);
        $this->renderPendingGroup($preparedHeaderRows, $headerHeights);
    }

    /**
     * @param list<PreparedTableRow> $preparedRows
     * @param list<float> $rowHeights
     */
    private function renderPendingGroupSegment(array $preparedRows, array $rowHeights, int $rowCount): void
    {
        $lineHeight = $this->fontSize * $this->lineHeightFactor;
        $rowTopY = $this->cursorY;
        $segmentRowHeights = array_slice($rowHeights, 0, $rowCount);
        /** @var list<list<array{segments: array<int, TextSegment>, justify: bool}>> $continuationLines */
        $continuationLines = [];

        foreach ($this->pendingRowspanCells as $pendingRowspanCell) {
            $visibleRowspan = min($pendingRowspanCell->remainingRows, $rowCount);
            $continuationLines[] = $this->renderPreparedCellSegment(
                $pendingRowspanCell->cell,
                $pendingRowspanCell->style,
                0,
                $segmentRowHeights,
                $rowTopY,
                $lineHeight,
                $visibleRowspan,
                $pendingRowspanCell->remainingLines !== [],
                false,
                $pendingRowspanCell->remainingRows <= $rowCount,
                $pendingRowspanCell->remainingLines,
            );
        }

        for ($rowIndex = 0; $rowIndex < $rowCount; $rowIndex++) {
            $preparedRow = $preparedRows[$rowIndex];

            foreach ($preparedRow->cells as $preparedCell) {
                $resolvedStyle = $this->resolveEffectiveCellStyle($preparedCell->cell, $preparedRow->header);
                $visibleRowspan = min($preparedCell->cell->rowspan, $rowCount - $rowIndex);
                $continuationLines[] = $this->renderPreparedCellSegment(
                    $preparedCell,
                    $resolvedStyle,
                    $rowIndex,
                    $segmentRowHeights,
                    $rowTopY,
                    $lineHeight,
                    $visibleRowspan,
                    true,
                    true,
                    $visibleRowspan === $preparedCell->cell->rowspan,
                );

            }

            $rowTopY -= $segmentRowHeights[$rowIndex];
        }

        $this->cursorY = $rowTopY;
        $this->pendingRowspanCells = $this->buildPendingRowspanContinuations($preparedRows, $rowCount, $continuationLines);
    }

    /**
     * @param list<PreparedTableRow> $preparedRows
     * @param list<list<array{segments: array<int, TextSegment>, justify: bool}>> $continuationLines
     * @return list<PendingRowspanCell>
     */
    private function buildPendingRowspanContinuations(array $preparedRows, int $renderedRowCount, array $continuationLines): array
    {
        $continuations = [];
        $continuationIndex = 0;

        foreach ($this->pendingRowspanCells as $pendingRowspanCell) {
            $remainingRows = $pendingRowspanCell->remainingRows - $renderedRowCount;
            $remainingLines = $continuationLines[$continuationIndex] ?? [];
            $continuationIndex++;

            if ($remainingRows > 0 && $remainingLines !== []) {
                $continuations[] = new PendingRowspanCell(
                    $pendingRowspanCell->cell,
                    $pendingRowspanCell->style,
                    0,
                    $remainingRows,
                    true,
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

                if ($remainingRows <= 0 || $remainingLines === []) {
                    continue;
                }

                $continuations[] = new PendingRowspanCell(
                    $preparedCell,
                    $this->resolveEffectiveCellStyle($preparedCell->cell, $preparedRow->header),
                    0,
                    $remainingRows,
                    true,
                    $remainingLines,
                );
            }
        }

        return $continuations;
    }

    /**
     * @param list<float> $rowHeights
     */
    private function countFittingRows(array $rowHeights, float $availableHeight): int
    {
        $usedHeight = 0.0;
        $fittingRows = 0;

        foreach ($rowHeights as $rowHeight) {
            if (($usedHeight + $rowHeight) > $availableHeight) {
                break;
            }

            $usedHeight += $rowHeight;
            $fittingRows++;
        }

        return $fittingRows;
    }

    /**
     * @param list<PreparedTableRow> $preparedRows
     * @param list<float> $rowHeights
     */
    private function shouldDeferLeadingSplitToNextPage(array $preparedRows, array $rowHeights, int $fittingRowCount): bool
    {
        if ($this->pendingRowspanCells !== [] || $fittingRowCount !== 1 || $preparedRows === []) {
            return false;
        }

        $lineHeight = $this->fontSize * $this->lineHeightFactor;
        $firstRow = $preparedRows[0];

        foreach ($firstRow->cells as $preparedCell) {
            if ($preparedCell->cell->rowspan <= 1) {
                continue;
            }

            $resolvedStyle = $this->resolveEffectiveCellStyle($preparedCell->cell, $firstRow->header);
            $availableTextHeight = $rowHeights[0] - $resolvedStyle->padding->vertical();

            if ($availableTextHeight <= 0) {
                return true;
            }

            $maxLines = (int) floor($availableTextHeight / $lineHeight);
            $lineCount = count($this->page->layoutParagraphLines(
                $preparedCell->cell->text,
                $this->baseFont,
                $this->fontSize,
                $preparedCell->width - $resolvedStyle->padding->horizontal(),
                $resolvedStyle->textColor,
                $resolvedStyle->opacity,
            ));

            if ($lineCount > 1 && $maxLines < 2) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<string|list<TextSegment>|TableCell> $cells
     * @return array{cells: list<PreparedTableCell>, nextRowspans: list<int>}
     */
    private function prepareRow(array $cells, bool $header): array
    {
        $preparedCells = [];
        $lineHeight = $this->fontSize * $this->lineHeightFactor;
        $nextRowspans = array_map(
            static fn (int $remainingRows): int => max(0, $remainingRows - 1),
            $this->activeRowspans,
        );
        $columnIndex = 0;

        foreach ($cells as $cell) {
            while ($columnIndex < count($this->columnWidths) && $this->activeRowspans[$columnIndex] > 0) {
                $columnIndex++;
            }

            $preparedCell = $this->normalizeCell($cell, $header);
            $resolvedStyle = $this->resolveEffectiveCellStyle($preparedCell, $header);
            $padding = $resolvedStyle->padding;
            $columnWidth = $this->resolveColumnSpanWidth($columnIndex, $preparedCell->colspan, $this->activeRowspans);
            $contentWidth = $columnWidth - $padding->horizontal();

            if ($contentWidth <= 0) {
                throw new InvalidArgumentException('Table column width must be greater than the horizontal cell padding.');
            }

            $lineCount = $this->page->countParagraphLines(
                $preparedCell->text,
                $this->baseFont,
                $this->fontSize,
                $contentWidth,
            );

            $contentHeight = $this->fontSize + (max(0, $lineCount - 1) * $lineHeight);
            $cellHeight = $contentHeight + $padding->vertical();
            $preparedCells[] = new PreparedTableCell(
                $preparedCell,
                $columnWidth,
                $columnIndex,
                $cellHeight,
                $contentHeight,
                $resolvedStyle->padding,
            );

            if ($preparedCell->rowspan > 1) {
                for ($offset = 0; $offset < $preparedCell->colspan; $offset++) {
                    $nextRowspans[$columnIndex + $offset] = $preparedCell->rowspan - 1;
                }
            }

            $columnIndex += $preparedCell->colspan;
        }

        while ($columnIndex < count($this->columnWidths) && $this->activeRowspans[$columnIndex] > 0) {
            $columnIndex++;
        }

        if ($columnIndex !== count($this->columnWidths)) {
            throw new InvalidArgumentException('Table row spans must match the number of columns.');
        }

        return ['cells' => $preparedCells, 'nextRowspans' => array_values($nextRowspans)];
    }

    /**
     * @param list<PreparedTableRow> $preparedRows
     * @return list<float>
     */
    private function resolvePendingGroupRowHeights(array $preparedRows): array
    {
        $rowHeights = array_fill(0, count($preparedRows), 0.0);

        foreach ($preparedRows as $rowIndex => $preparedRow) {
            foreach ($preparedRow->cells as $preparedCell) {
                if ($preparedCell->cell->rowspan === 1) {
                    $rowHeights[$rowIndex] = max($rowHeights[$rowIndex], $preparedCell->minHeight);
                }
            }
        }

        foreach ($preparedRows as $rowIndex => $preparedRow) {
            foreach ($preparedRow->cells as $preparedCell) {
                $rowspan = $preparedCell->cell->rowspan;

                if ($rowspan === 1) {
                    continue;
                }

                if (($rowIndex + $rowspan) > count($preparedRows)) {
                    throw new InvalidArgumentException('Rowspan groups must be completed by subsequent rows.');
                }

                $currentHeight = array_sum(array_slice($rowHeights, $rowIndex, $rowspan));
                $missingHeight = $preparedCell->minHeight - $currentHeight;

                if ($missingHeight > 0) {
                    $rowHeights[$rowIndex + $rowspan - 1] += $missingHeight;
                }
            }
        }

        return array_values($rowHeights);
    }

    /**
     * @param string|list<TextSegment>|TableCell $cell
     */
    private function normalizeCell(string | array | TableCell $cell, bool $header): TableCell
    {
        if ($cell instanceof TableCell) {
            if ($cell->rowspan <= 0) {
                throw new InvalidArgumentException('Table cell rowspan must be greater than zero.');
            }

            $style = $cell->style ?? new CellStyle();

            return new TableCell(
                $this->normalizeText($cell->text, $header),
                $cell->colspan,
                $cell->rowspan,
                $style,
            );
        }

        return new TableCell($this->normalizeText($cell, $header));
    }

    private function hasActiveRowspans(): bool
    {
        return array_any(
            $this->activeRowspans,
            static fn (int $remainingRows): bool => $remainingRows > 0,
        );
    }

    /**
     * @param list<int> $activeRowspans
     */
    private function resolveColumnSpanWidth(int $columnIndex, int $colspan, array $activeRowspans): float
    {
        if ($colspan <= 0) {
            throw new InvalidArgumentException('Table cell colspan must be greater than zero.');
        }

        if ($columnIndex >= count($this->columnWidths)) {
            throw new InvalidArgumentException('Table row spans must match the number of columns.');
        }

        $columnSlice = array_slice($this->columnWidths, $columnIndex, $colspan);

        if (count($columnSlice) !== $colspan) {
            throw new InvalidArgumentException('Table cell colspan exceeds the configured table columns.');
        }

        foreach (array_slice($activeRowspans, $columnIndex, $colspan) as $occupied) {
            if ($occupied > 0) {
                throw new InvalidArgumentException('Table row spans must match the number of columns.');
            }
        }

        return array_sum(array_map(static fn (float | int $value): float => (float) $value, $columnSlice));
    }

    /**
     * @param list<list<string|list<TextSegment>|TableCell>> $rows
     * @return list<PreparedTableRow>
     */
    private function prepareRowGroup(array $rows, bool $header): array
    {
        $previousRowspans = $this->activeRowspans;
        $this->activeRowspans = array_fill(0, count($this->columnWidths), 0);
        $preparedRows = [];

        foreach ($rows as $row) {
            $preparedRow = $this->prepareRow($row, $header);
            $preparedRows[] = new PreparedTableRow($preparedRow['cells'], $header);
            $this->activeRowspans = $preparedRow['nextRowspans'];
        }

        if ($this->hasActiveRowspans()) {
            throw new InvalidArgumentException('Header rowspans must be completed within the header rows.');
        }

        $this->activeRowspans = $previousRowspans;

        return $preparedRows;
    }

    /**
     * @param list<PreparedTableRow> $preparedRows
     * @param list<float> $rowHeights
     */
    private function renderPendingGroup(array $preparedRows, array $rowHeights): void
    {
        $lineHeight = $this->fontSize * $this->lineHeightFactor;
        $rowTopY = $this->cursorY;

        foreach ($preparedRows as $rowIndex => $preparedRow) {
            foreach ($preparedRow->cells as $preparedCell) {
                $this->renderPreparedCell($preparedCell, $preparedRow->header, $rowIndex, $rowHeights, $rowTopY, $lineHeight);
            }

            $rowTopY -= $rowHeights[$rowIndex];
        }

        $this->cursorY = $rowTopY;
    }

    /**
     * @param list<float> $rowHeights
     */
    private function renderPreparedCell(
        PreparedTableCell $preparedCell,
        bool $header,
        int $rowIndex,
        array $rowHeights,
        float $rowTopY,
        float $lineHeight,
    ): void {
        $resolvedStyle = $this->resolveEffectiveCellStyle($preparedCell->cell, $header);
        $this->renderPreparedCellSegment(
            $preparedCell,
            $resolvedStyle,
            $rowIndex,
            $rowHeights,
            $rowTopY,
            $lineHeight,
            $preparedCell->cell->rowspan,
            true,
            true,
            true,
        );
    }

    /**
     * @param list<float> $rowHeights
     * @param list<array{segments: array<int, TextSegment>, justify: bool}> $remainingLines
     * @return list<array{segments: array<int, TextSegment>, justify: bool}>
     */
    private function renderPreparedCellSegment(
        PreparedTableCell $preparedCell,
        ResolvedTableCellStyle $resolvedStyle,
        int $rowIndex,
        array $rowHeights,
        float $rowTopY,
        float $lineHeight,
        int $visibleRowspan,
        bool $renderText,
        bool $renderTopBorder,
        bool $renderBottomBorder,
        array $remainingLines = [],
    ): array {
        $geometry = $this->resolvePreparedCellGeometryForHeight($preparedCell, $rowIndex, $rowHeights, $rowTopY, $resolvedStyle, $visibleRowspan);

        $this->renderCellBox(
            $geometry->x,
            $geometry->bottomY,
            $geometry->width,
            $geometry->height,
            $resolvedStyle->fillColor,
            $this->style->border,
            $resolvedStyle->rowBorder,
            $resolvedStyle->cellBorder,
            $renderTopBorder,
            true,
            $renderBottomBorder,
            true,
        );

        if (!$renderText) {
            return $remainingLines;
        }

        $availableTextHeight = $geometry->height - $resolvedStyle->padding->vertical();

        if ($availableTextHeight <= 0) {
            return $remainingLines;
        }

        $maxLines = max(1, (int) floor($availableTextHeight / $lineHeight));
        $allLines = $remainingLines !== []
            ? $remainingLines
            : $this->page->layoutParagraphLines(
                $preparedCell->cell->text,
                $this->baseFont,
                $this->fontSize,
                $geometry->textWidth,
                $resolvedStyle->textColor,
                $resolvedStyle->opacity,
            );

        if ($allLines === []) {
            return [];
        }

        if ($remainingLines === [] && $visibleRowspan < $preparedCell->cell->rowspan && count($allLines) > 1 && $maxLines < 2) {
            return $allLines;
        }

        if ($visibleRowspan === $preparedCell->cell->rowspan && count($allLines) <= $maxLines) {
            $this->page = $this->page->renderParagraphLines(
                $allLines,
                $geometry->textX,
                $geometry->textY,
                $geometry->textWidth,
                $this->baseFont,
                $this->fontSize,
                null,
                $lineHeight,
                $geometry->bottomLimitY,
                $resolvedStyle->horizontalAlign,
            );

            return [];
        }

        $visibleLines = array_slice($allLines, 0, $maxLines);
        $remainingLines = array_slice($allLines, $maxLines);
        $this->page = $this->page->renderParagraphLines(
            $visibleLines,
            $geometry->textX,
            $rowTopY - $resolvedStyle->padding->top - $this->fontSize,
            $geometry->textWidth,
            $this->baseFont,
            $this->fontSize,
            null,
            $lineHeight,
            $geometry->bottomLimitY,
            $resolvedStyle->horizontalAlign,
        );

        return $remainingLines;
    }

    /**
     * @param list<float> $rowHeights
     */
    private function resolvePreparedCellGeometryForHeight(
        PreparedTableCell $preparedCell,
        int $rowIndex,
        array $rowHeights,
        float $rowTopY,
        ResolvedTableCellStyle $resolvedStyle,
        int $visibleRowspan,
    ): PreparedTableCellGeometry {
        $height = array_sum(array_slice($rowHeights, $rowIndex, $visibleRowspan));
        $x = $this->x + $this->calculateColumnOffset($preparedCell->column);
        $bottomY = $rowTopY - $height;
        $padding = $resolvedStyle->padding;

        return new PreparedTableCellGeometry(
            $x,
            $bottomY,
            $preparedCell->width,
            $height,
            $x + $padding->left,
            $this->resolveCellTextStartY(
                $rowTopY,
                $bottomY,
                $height,
                $preparedCell->contentHeight,
                $resolvedStyle->verticalAlign,
                $padding,
            ),
            $preparedCell->width - $padding->horizontal(),
            ($bottomY + $padding->bottom) - self::CELL_BOTTOM_EPSILON,
        );
    }

    private function resolveCellTextStartY(
        float $cellTopY,
        float $cellBottomY,
        float $cellHeight,
        float $contentHeight,
        VerticalAlign $verticalAlign,
        TablePadding $padding,
    ): float {
        $topStartY = $cellTopY - $padding->top - $this->fontSize;
        $bottomStartY = $cellBottomY + $padding->bottom + $contentHeight - $this->fontSize;

        return match ($verticalAlign) {
            VerticalAlign::TOP => $topStartY,
            VerticalAlign::MIDDLE => $bottomStartY + (($topStartY - $bottomStartY) / 2),
            VerticalAlign::BOTTOM => $bottomStartY,
        };
    }

    private function renderCellBox(
        float $x,
        float $y,
        float $width,
        float $height,
        ?Color $fillColor,
        ?TableBorder $defaultBorder,
        ?TableBorder $rowBorder,
        ?TableBorder $cellBorder,
        bool $renderTopBorder = true,
        bool $renderRightBorder = true,
        bool $renderBottomBorder = true,
        bool $renderLeftBorder = true,
    ): void {
        if ($fillColor !== null) {
            $this->page->addRectangle($x, $y, $width, $height, null, null, $fillColor);
        }

        $topBorder = $renderTopBorder ? $this->resolveBorderSide('top', $defaultBorder, $rowBorder, $cellBorder) : null;
        $rightBorder = $renderRightBorder ? $this->resolveBorderSide('right', $defaultBorder, $rowBorder, $cellBorder) : null;
        $bottomBorder = $renderBottomBorder ? $this->resolveBorderSide('bottom', $defaultBorder, $rowBorder, $cellBorder) : null;
        $leftBorder = $renderLeftBorder ? $this->resolveBorderSide('left', $defaultBorder, $rowBorder, $cellBorder) : null;

        if ($topBorder === null && $rightBorder === null && $bottomBorder === null && $leftBorder === null) {
            return;
        }

        if (
            $topBorder !== null
            && $rightBorder !== null
            && $bottomBorder !== null
            && $leftBorder !== null
            && $this->bordersAreEquivalent($topBorder, $rightBorder, $bottomBorder, $leftBorder)
        ) {
            $this->page->addRectangle(
                $x,
                $y,
                $width,
                $height,
                $topBorder->width,
                $topBorder->color,
                null,
                $topBorder->opacity,
            );

            return;
        }

        if ($topBorder !== null) {
            $this->page->addLine($x, $y + $height, $x + $width, $y + $height, $topBorder->width, $topBorder->color, $topBorder->opacity);
        }

        if ($rightBorder !== null) {
            $this->page->addLine($x + $width, $y, $x + $width, $y + $height, $rightBorder->width, $rightBorder->color, $rightBorder->opacity);
        }

        if ($bottomBorder !== null) {
            $this->page->addLine($x, $y, $x + $width, $y, $bottomBorder->width, $bottomBorder->color, $bottomBorder->opacity);
        }

        if ($leftBorder !== null) {
            $this->page->addLine($x, $y, $x, $y + $height, $leftBorder->width, $leftBorder->color, $leftBorder->opacity);
        }
    }

    private function resolveBorderSide(
        string $side,
        ?TableBorder $defaultBorder,
        ?TableBorder $rowBorder,
        ?TableBorder $cellBorder,
    ): ?ResolvedBorderSide {
        $applicableBorders = [];

        foreach ([$cellBorder, $rowBorder, $defaultBorder] as $border) {
            if ($border !== null && $border->isDefinedFor($side)) {
                $applicableBorders[] = $border;
            }
        }

        if ($applicableBorders === []) {
            return null;
        }

        $resolvedBorder = $applicableBorders[0];

        if (!$resolvedBorder->isEnabled($side)) {
            return null;
        }

        return new ResolvedBorderSide(
            $this->firstDefinedBorderWidth($applicableBorders),
            $this->firstDefinedBorderColor($applicableBorders),
            $this->firstDefinedBorderOpacity($applicableBorders),
        );
    }

    private function resolveRowStyle(bool $header): ?RowStyle
    {
        return $header ? $this->headerStyle : $this->rowStyle;
    }

    private function resolveEffectiveCellStyle(TableCell $cell, bool $header): ResolvedTableCellStyle
    {
        $rowStyle = $this->resolveRowStyle($header);
        $cellStyle = $cell->style ?? new CellStyle();
        $rowPadding = $rowStyle instanceof RowStyle ? $rowStyle->padding : null;
        $rowFillColor = $rowStyle instanceof RowStyle ? $rowStyle->fillColor : null;
        $rowTextColor = $rowStyle instanceof RowStyle ? $rowStyle->textColor : null;
        $rowVerticalAlign = $rowStyle instanceof RowStyle ? $rowStyle->verticalAlign : null;
        $rowHorizontalAlign = $rowStyle instanceof RowStyle ? $rowStyle->horizontalAlign : null;
        $rowOpacity = $rowStyle instanceof RowStyle ? $rowStyle->opacity : null;
        $rowBorder = $rowStyle instanceof RowStyle ? $rowStyle->border : null;

        return new ResolvedTableCellStyle(
            $cellStyle->padding ?? $rowPadding ?? $this->style->padding ?? TablePadding::all(0.0),
            $cellStyle->fillColor ?? $rowFillColor ?? $this->style->fillColor,
            $cellStyle->textColor ?? $rowTextColor ?? $this->style->textColor,
            $cellStyle->verticalAlign ?? $rowVerticalAlign ?? $this->style->verticalAlign ?? VerticalAlign::TOP,
            $cellStyle->horizontalAlign ?? $rowHorizontalAlign ?? HorizontalAlign::LEFT,
            $cellStyle->opacity ?? $rowOpacity,
            $rowBorder,
            $cellStyle->border,
        );
    }

    private function mergeRowStyle(?RowStyle $base, RowStyle $override): RowStyle
    {
        return $this->buildMergedRowStyle(RowStyle::class, $base, $override);
    }

    private function mergeHeaderStyle(?HeaderStyle $base, HeaderStyle $override): HeaderStyle
    {
        /** @var HeaderStyle $merged */
        $merged = $this->buildMergedRowStyle(HeaderStyle::class, $base, $override);

        return $merged;
    }

    /**
     * @template T of RowStyle
     * @param class-string<T> $styleClass
     * @param ?T $base
     * @param T $override
     * @return T
     */
    private function buildMergedRowStyle(string $styleClass, ?RowStyle $base, RowStyle $override): RowStyle
    {
        return new $styleClass(
            horizontalAlign: $override->horizontalAlign ?? $base?->horizontalAlign,
            verticalAlign: $override->verticalAlign ?? $base?->verticalAlign,
            padding: $override->padding ?? $base?->padding,
            fillColor: $override->fillColor ?? $base?->fillColor,
            textColor: $override->textColor ?? $base?->textColor,
            opacity: $override->opacity ?? $base?->opacity,
            border: $override->border ?? $base?->border,
        );
    }

    /**
     * @param list<TableBorder> $borders
     */
    private function firstDefinedBorderWidth(array $borders): float
    {
        foreach ($borders as $border) {
            if ($border->width !== null) {
                return $border->width;
            }
        }

        return 1.0;
    }

    /**
     * @param list<TableBorder> $borders
     */
    private function firstDefinedBorderColor(array $borders): ?Color
    {
        foreach ($borders as $border) {
            if ($border->color !== null) {
                return $border->color;
            }
        }

        return null;
    }

    /**
     * @param list<TableBorder> $borders
     */
    private function firstDefinedBorderOpacity(array $borders): ?Opacity
    {
        foreach ($borders as $border) {
            if ($border->opacity !== null) {
                return $border->opacity;
            }
        }

        return null;
    }

    private function bordersAreEquivalent(
        ResolvedBorderSide $top,
        ResolvedBorderSide $right,
        ResolvedBorderSide $bottom,
        ResolvedBorderSide $left,
    ): bool
    {
        return $top == $right && $right == $bottom && $bottom == $left;
    }

    private function calculateColumnOffset(int $columnIndex): float
    {
        return array_sum(array_map(
            static fn (float | int $value): float => (float) $value,
            array_slice($this->columnWidths, 0, $columnIndex),
        ));
    }

    /**
     * @param string|list<TextSegment> $text
     * @return string|list<TextSegment>
     */
    private function normalizeText(string | array $text, bool $header): string | array
    {
        if (!$header) {
            return $text;
        }

        if (is_string($text)) {
            return [new TextSegment(text: $text, bold: true)];
        }

        return array_map(
            static fn (TextSegment $segment): TextSegment => new TextSegment(
                text: $segment->text,
                color: $segment->color,
                opacity: $segment->opacity,
                link: $segment->link,
                bold: true,
                italic: $segment->italic,
                underline: $segment->underline,
                strikethrough: $segment->strikethrough,
            ),
            $text,
        );
    }
}
