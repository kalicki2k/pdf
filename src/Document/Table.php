<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use InvalidArgumentException;
use Kalle\Pdf\Document\Table\Layout\CellLayoutResolver;
use Kalle\Pdf\Document\Table\Layout\PreparedTableCell;
use Kalle\Pdf\Document\Table\Layout\PreparedTableRow;
use Kalle\Pdf\Document\Table\Layout\RowGroupHeightResolver;
use Kalle\Pdf\Document\Table\Layout\RowPreparer;
use Kalle\Pdf\Document\Table\PendingRowspanCell;
use Kalle\Pdf\Document\Table\Rendering\CellRenderOptions;
use Kalle\Pdf\Document\Table\Rendering\CellRenderResult;
use Kalle\Pdf\Document\Table\Rendering\PreparedCellRenderer;
use Kalle\Pdf\Document\Table\Style\HeaderStyle;
use Kalle\Pdf\Document\Table\Style\RowStyle;
use Kalle\Pdf\Document\Table\Style\TableBorder;
use Kalle\Pdf\Document\Table\Style\TablePadding;
use Kalle\Pdf\Document\Table\Style\TableStyle;
use Kalle\Pdf\Document\Table\Support\ResolvedTableCellStyle;
use Kalle\Pdf\Document\Table\Support\TableStyleResolver;
use Kalle\Pdf\Document\Table\TableCell;
use Kalle\Pdf\Document\Table\TableGroupPageFit;
use Kalle\Pdf\Document\Text\TextSegment;
use Kalle\Pdf\Graphics\Color;
use Kalle\Pdf\Layout\VerticalAlign;

final class Table
{
    private const DEFAULT_LINE_HEIGHT_FACTOR = 1.2;
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
    private readonly TableStyleResolver $styleResolver;
    private readonly RowGroupHeightResolver $rowGroupHeightResolver;
    private readonly PreparedCellRenderer $preparedCellRenderer;

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
        $this->styleResolver = new TableStyleResolver();
        $this->rowGroupHeightResolver = new RowGroupHeightResolver();
        $this->preparedCellRenderer = new PreparedCellRenderer(
            $this->styleResolver,
            new CellLayoutResolver($this->x, $this->columnWidths),
            new \Kalle\Pdf\Document\Table\Rendering\CellBoxRenderer($this->styleResolver),
        );
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
        $this->style = $this->styleResolver->mergeTableStyle($this->style, $style);

        return $this;
    }

    public function rowStyle(RowStyle $style): self
    {
        $this->rowStyle = $this->styleResolver->mergeRowStyle($this->rowStyle, $style);

        return $this;
    }

    public function headerStyle(HeaderStyle $style): self
    {
        $this->headerStyle = $this->styleResolver->mergeHeaderStyle($this->headerStyle, $style);

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
        $rowHeights = $this->rowGroupHeightResolver->resolve($pendingGroupRows);
        $isBodyGroup = array_any(
            $pendingGroupRows,
            static fn (PreparedTableRow $row): bool => $row->header === false,
        );
        $remainingRows = $pendingGroupRows;
        $remainingRowHeights = $rowHeights;
        $deferredLeadingSplit = false;

        while ($remainingRows !== []) {
            $pageFit = $this->resolvePendingGroupPageFit($remainingRowHeights, $isBodyGroup);
            $fittingRowCount = $pageFit->fittingRowCountOnCurrentPage;

            if ($fittingRowCount === 0) {
                $this->moveToNextPageForPendingGroup($pageFit);
                $pageFit = $this->resolvePendingGroupPageFit($remainingRowHeights, false);
                $fittingRowCount = $pageFit->fittingRowCountOnCurrentPage;

                if ($fittingRowCount === 0) {
                    throw new InvalidArgumentException('Table rows must fit on a fresh page.');
                }
            }

            if (
                !$deferredLeadingSplit
                && $this->shouldDeferLeadingSplitToNextPage($remainingRows, $remainingRowHeights, $fittingRowCount)
            ) {
                $this->moveToNextPageForPendingGroup(new TableGroupPageFit($isBodyGroup && $this->headerRows !== [], 0));
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
            $this->moveToNextPageForPendingGroup(new TableGroupPageFit($isBodyGroup && $this->headerRows !== [], 0));
        }

        $this->pendingGroupRows = [];
        $this->pendingRowspanCells = [];
    }

    /**
     * @param list<float> $rowHeights
     */
    private function resolvePendingGroupPageFit(array $rowHeights, bool $repeatHeaders): TableGroupPageFit
    {
        $groupHeight = array_sum($rowHeights);
        $remainingCurrentPageHeight = $this->cursorY - $this->bottomMargin;
        $fullPageAvailableHeight = $this->page->getHeight() - $this->topMargin - $this->bottomMargin;
        $headerRepeatHeight = 0.0;

        if ($repeatHeaders && $this->headerRows !== []) {
            $preparedHeaderRows = $this->prepareRowGroup($this->headerRows, true);
            $headerRepeatHeight = array_sum($this->rowGroupHeightResolver->resolve($preparedHeaderRows));
        }

        $availableFreshPageHeight = $fullPageAvailableHeight - $headerRepeatHeight;

        return new TableGroupPageFit(
            repeatHeaders: $repeatHeaders && $this->headerRows !== [],
            fittingRowCountOnCurrentPage: $this->countFittingRows($rowHeights, $remainingCurrentPageHeight),
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
        $headerHeights = $this->rowGroupHeightResolver->resolve($preparedHeaderRows);
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
            $result = $this->renderPendingRowspanCellSegment($pendingRowspanCell, $rowCount, $segmentRowHeights, $rowTopY, $lineHeight);
            $this->page = $result->page;
            $continuationLines[] = $result->remainingLines;
        }

        for ($rowIndex = 0; $rowIndex < $rowCount; $rowIndex++) {
            $preparedRow = $preparedRows[$rowIndex];

            foreach ($preparedRow->cells as $preparedCell) {
                $result = $this->renderPreparedCellSegment($preparedCell, $preparedRow->header, $rowIndex, $rowCount, $segmentRowHeights, $rowTopY, $lineHeight);
                $this->page = $result->page;
                $continuationLines[] = $result->remainingLines;
            }

            $rowTopY -= $segmentRowHeights[$rowIndex];
        }

        $this->cursorY = $rowTopY;
        $this->pendingRowspanCells = $this->buildPendingRowspanContinuations($preparedRows, $rowCount, $continuationLines);
    }

    /**
     * @param list<float> $rowHeights
     */
    private function renderPendingRowspanCellSegment(
        PendingRowspanCell $pendingRowspanCell,
        int $rowCount,
        array $rowHeights,
        float $rowTopY,
        float $lineHeight,
    ): CellRenderResult {
        $visibleRowspan = min($pendingRowspanCell->remainingRows, $rowCount);

        return $this->preparedCellRenderer->renderSegment(
            $this->page,
            $pendingRowspanCell->cell,
            $pendingRowspanCell->style,
            0,
            $rowHeights,
            $rowTopY,
            $lineHeight,
            $this->baseFont,
            $this->fontSize,
            $this->style,
            new CellRenderOptions(
                visibleRowspan: $visibleRowspan,
                renderText: $pendingRowspanCell->remainingLines !== [],
                renderTopBorder: false,
                renderBottomBorder: $pendingRowspanCell->remainingRows <= $rowCount,
                remainingLines: $pendingRowspanCell->remainingLines,
            ),
        );
    }

    /**
     * @param list<float> $rowHeights
     */
    private function renderPreparedCellSegment(
        PreparedTableCell $preparedCell,
        bool $header,
        int $rowIndex,
        int $rowCount,
        array $rowHeights,
        float $rowTopY,
        float $lineHeight,
    ): CellRenderResult {
        $visibleRowspan = min($preparedCell->cell->rowspan, $rowCount - $rowIndex);

        return $this->preparedCellRenderer->renderSegment(
            $this->page,
            $preparedCell,
            $this->resolveEffectiveCellStyle($preparedCell->cell, $header),
            $rowIndex,
            $rowHeights,
            $rowTopY,
            $lineHeight,
            $this->baseFont,
            $this->fontSize,
            $this->style,
            new CellRenderOptions(
                visibleRowspan: $visibleRowspan,
                renderBottomBorder: $visibleRowspan === $preparedCell->cell->rowspan,
            ),
        );
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

                if ($remainingRows <= 0 || $remainingLines === []) {
                    continue;
                }

                $continuations[] = new PendingRowspanCell(
                    $preparedCell,
                    $this->resolveEffectiveCellStyle($preparedCell->cell, $preparedRow->header),
                    $remainingRows,
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

    private function hasActiveRowspans(): bool
    {
        return array_any(
            $this->activeRowspans,
            static fn (int $remainingRows): bool => $remainingRows > 0,
        );
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
                $this->page = $this->preparedCellRenderer->render(
                    $this->page,
                    $preparedCell,
                    $preparedRow->header,
                    $rowIndex,
                    $rowHeights,
                    $rowTopY,
                    $lineHeight,
                    $this->style,
                    $this->rowStyle,
                    $this->headerStyle,
                    $this->baseFont,
                    $this->fontSize,
                );
            }

            $rowTopY -= $rowHeights[$rowIndex];
        }

        $this->cursorY = $rowTopY;
    }

    private function resolveEffectiveCellStyle(TableCell $cell, bool $header): ResolvedTableCellStyle
    {
        return $this->styleResolver->resolveCellStyle(
            $this->style,
            $this->rowStyle,
            $this->headerStyle,
            $cell,
            $header,
        );
    }

    /**
     * @param list<string|list<TextSegment>|TableCell> $cells
     * @return array{cells: list<PreparedTableCell>, nextRowspans: list<int>}
     */
    private function prepareRow(array $cells, bool $header): array
    {
        return $this->createRowPreparer()->prepareRow($cells, $this->activeRowspans, $header);
    }

    private function createRowPreparer(): RowPreparer
    {
        return new RowPreparer(
            $this->page,
            $this->columnWidths,
            $this->baseFont,
            $this->fontSize,
            $this->lineHeightFactor,
            $this->style,
            $this->rowStyle,
            $this->headerStyle,
            $this->styleResolver,
        );
    }
}
