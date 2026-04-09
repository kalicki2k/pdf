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
use Kalle\Pdf\Document\Table\Rendering\TablePendingRenderState;
use Kalle\Pdf\Document\Table\Style\FooterStyle;
use Kalle\Pdf\Document\Table\Style\HeaderStyle;
use Kalle\Pdf\Document\Table\Style\RowStyle;
use Kalle\Pdf\Document\Table\Style\TableBorder;
use Kalle\Pdf\Document\Table\Style\TablePadding;
use Kalle\Pdf\Document\Table\Style\TableStyle;
use Kalle\Pdf\Document\Table\Support\ResolvedTableCellStyle;
use Kalle\Pdf\Document\Table\Support\TableStyleResolver;
use Kalle\Pdf\Document\Table\Support\TableTextMetrics;
use Kalle\Pdf\Document\Table\TableCaption;
use Kalle\Pdf\Document\Table\TableCell;
use Kalle\Pdf\Document\Table\TableGroupPageFit;
use Kalle\Pdf\Document\Table\TableHeaderScope;
use Kalle\Pdf\Document\Text\StructureTag;
use Kalle\Pdf\Document\Text\TextSegment;
use Kalle\Pdf\Graphics\Color;
use Kalle\Pdf\Layout\VerticalAlign;
use Kalle\Pdf\Structure\StructElem;

final class Table
{
    private const DEFAULT_LINE_HEIGHT_FACTOR = 1.2;
    private const DEFAULT_CONTINUATION_TOP_MARGIN = 40.0;

    /** @var list<list<string|list<TextSegment>|TableCell>> */
    private array $repeatingHeaderRows = [];
    /** @var list<list<string|list<TextSegment>|TableCell>> */
    private array $footerRows = [];
    /** @var list<int> */
    private array $activeRowspans = [];
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
    private ?FooterStyle $footerStyle = null;
    private readonly TableStyleResolver $styleResolver;
    private readonly TableTextMetrics $textMetrics;
    private readonly RowGroupHeightResolver $rowGroupHeightResolver;
    private readonly PreparedCellRenderer $preparedCellRenderer;
    private readonly TablePendingRenderState $pendingRenderState;
    private readonly ?StructElem $tableStructElem;
    private ?TableCaption $caption = null;
    private bool $captionRendered = false;
    private bool $footersRendered = false;
    private bool $rowsAdded = false;
    private bool $bodyRowsAdded = false;
    private bool $footerRowsAdded = false;

    /**
     * @param list<float|int> $columnWidths
     */
    public function __construct(
        Page $page,
        private readonly float $x,
        float $y,
        private readonly float $width,
        private readonly array $columnWidths,
        private readonly float $bottomMargin = 20.0,
    ) {
        if ($this->width <= 0) {
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

        if (abs($totalColumnWidth - $this->width) > 0.001) {
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
        $this->textMetrics = new TableTextMetrics();
        $this->rowGroupHeightResolver = new RowGroupHeightResolver();
        $this->preparedCellRenderer = new PreparedCellRenderer(
            $this->styleResolver,
            new CellLayoutResolver($this->x, $this->columnWidths),
            new \Kalle\Pdf\Document\Table\Rendering\CellBoxRenderer($this->styleResolver),
            $this->textMetrics,
        );
        $this->pendingRenderState = new TablePendingRenderState();
        $this->tableStructElem = $page->getDocument()->getProfile()->requiresTaggedPdf()
            ? $page->getDocument()->createStructElem(StructureTag::Table)
            : null;
        $this->style = new TableStyle(
            padding: TablePadding::all(6.0),
            border: TableBorder::all(color: Color::gray(0.75)),
            verticalAlign: VerticalAlign::TOP,
        );
        $this->headerStyle = new HeaderStyle(fillColor: Color::gray(0.92));
        $page->getDocument()->registerDeferredRenderFinalizer($this->finalize(...));
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

    public function footerStyle(FooterStyle $style): self
    {
        $this->footerStyle = $this->styleResolver->mergeFooterStyle($this->footerStyle, $style);

        return $this;
    }

    public function caption(TableCaption $caption): self
    {
        if ($this->captionRendered || $this->rowsAdded) {
            throw new InvalidArgumentException('Table caption must be configured before rows are added.');
        }

        $this->caption = $caption;

        return $this;
    }

    /**
     * @param list<string|list<TextSegment>|TableCell> $cells
     */
    public function addRow(array $cells): self
    {
        $this->bodyRowsAdded = true;

        return $this->addTypedRow($cells, false);
    }

    /**
     * @param list<string|list<TextSegment>|TableCell> $cells
     */
    public function addHeaderRow(array $cells, bool $repeat = true): self
    {
        if ($this->bodyRowsAdded || $this->footerRowsAdded) {
            throw new InvalidArgumentException('Header rows must be added before body or footer rows.');
        }

        if ($repeat) {
            $this->repeatingHeaderRows[] = $cells;
        }

        return $this->addTypedRow($cells, true);
    }

    /**
     * @param list<string|list<TextSegment>|TableCell> $cells
     */
    public function addFooterRow(array $cells): self
    {
        $this->rowsAdded = true;
        $this->footerRowsAdded = true;
        $this->footerRows[] = $cells;

        return $this;
    }

    /**
     * @param list<string|list<TextSegment>|TableCell> $cells
     */
    private function addTypedRow(array $cells, bool $header): self
    {
        $this->rowsAdded = true;

        $preparedRow = $this->prepareRow($cells, $header);
        $this->pendingRenderState->addRow(new PreparedTableRow($preparedRow['cells'], $header));
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
        $pendingGroupRows = $this->pendingRenderState->rows();
        $rowHeights = $this->rowGroupHeightResolver->resolve($pendingGroupRows);
        $this->renderCaptionIfNeeded($rowHeights);
        $isBodyGroup = array_any(
            $pendingGroupRows,
            static fn (PreparedTableRow $row): bool => $row->header === false,
        );
        $repeatHeaders = $isBodyGroup && $this->repeatingHeaderRows !== [];
        $remainingRows = $pendingGroupRows;
        $remainingRowHeights = $rowHeights;
        $deferredLeadingSplit = false;

        while ($remainingRows !== []) {
            $pageFit = $this->resolvePendingGroupPageFit($remainingRowHeights, $repeatHeaders);
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
                $this->moveToNextPageForPendingGroup(new TableGroupPageFit($repeatHeaders, 0));
                $deferredLeadingSplit = true;
                continue;
            }

            if ($fittingRowCount >= count($remainingRows) && !$this->pendingRenderState->hasPendingRowspanCells()) {
                $this->renderPendingGroup($remainingRows, $remainingRowHeights);
                break;
            }

            $this->renderPendingGroupSegment($remainingRows, $remainingRowHeights, $fittingRowCount);

            if ($fittingRowCount >= count($remainingRows)) {
                break;
            }

            $remainingRows = array_slice($remainingRows, $fittingRowCount);
            $remainingRowHeights = array_slice($remainingRowHeights, $fittingRowCount);
            $this->moveToNextPageForPendingGroup(new TableGroupPageFit($repeatHeaders, 0));
        }

        $this->pendingRenderState->clear();
    }

    /**
     * @param list<float> $rowHeights
     */
    private function resolvePendingGroupPageFit(array $rowHeights, bool $repeatHeaders): TableGroupPageFit
    {
        $remainingCurrentPageHeight = $this->cursorY - $this->bottomMargin;
        $headerRepeatHeight = 0.0;

        if ($repeatHeaders && $this->repeatingHeaderRows !== []) {
            $preparedHeaderRows = $this->prepareRowGroup(
                $this->repeatingHeaderRows,
                true,
                false,
                'Header rowspans must be completed within the repeated header rows.',
            );
            $headerRepeatHeight = array_sum($this->rowGroupHeightResolver->resolve($preparedHeaderRows));
        }

        return new TableGroupPageFit(
            repeatHeaders: $repeatHeaders && $this->repeatingHeaderRows !== [],
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

        $preparedHeaderRows = $this->prepareRowGroup(
            $this->repeatingHeaderRows,
            true,
            false,
            'Header rowspans must be completed within the repeated header rows.',
        );
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

        foreach ($this->pendingRenderState->pendingRowspanCells() as $pendingRowspanCell) {
            $result = $this->renderPendingRowspanCellSegment($pendingRowspanCell, $rowCount, $segmentRowHeights, $rowTopY, $lineHeight);
            $this->page = $result->page;
            $continuationLines[] = $result->remainingLines;
        }

        for ($rowIndex = 0; $rowIndex < $rowCount; $rowIndex++) {
            $preparedRow = $preparedRows[$rowIndex];
            $rowStructElem = $this->createTableRowStructElem();

            foreach ($preparedRow->cells as $preparedCell) {
                $result = $this->renderPreparedCellSegment(
                    $preparedCell,
                    $preparedRow->header,
                    $preparedRow->footer,
                    $rowIndex,
                    $rowCount,
                    $segmentRowHeights,
                    $rowTopY,
                    $lineHeight,
                    $this->createTableCellStructElem($preparedCell->cell, $preparedRow->header, $rowStructElem),
                );
                $this->page = $result->page;
                $continuationLines[] = $result->remainingLines;
            }

            $rowTopY -= $segmentRowHeights[$rowIndex];
        }

        $this->cursorY = $rowTopY;
        $this->pendingRenderState->replacePendingRowspanCells(
            $this->buildPendingRowspanContinuations($preparedRows, $rowCount, $continuationLines),
        );
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
        ?StructElem $cellStructElem = null,
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
                renderTopBorder: true,
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
        bool $footer,
        int $rowIndex,
        int $rowCount,
        array $rowHeights,
        float $rowTopY,
        float $lineHeight,
        ?StructElem $cellStructElem = null,
    ): CellRenderResult {
        $visibleRowspan = min($preparedCell->cell->rowspan, $rowCount - $rowIndex);

        return $this->preparedCellRenderer->renderSegment(
            $this->page,
            $preparedCell,
            $this->resolveEffectiveCellStyle($preparedCell->cell, $header, $footer),
            $rowIndex,
            $rowHeights,
            $rowTopY,
            $lineHeight,
            $this->baseFont,
            $this->fontSize,
            $this->style,
            new CellRenderOptions(
                visibleRowspan: $visibleRowspan,
            ),
            $cellStructElem,
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

        foreach ($this->pendingRenderState->pendingRowspanCells() as $pendingRowspanCell) {
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
                    $this->resolveEffectiveCellStyle($preparedCell->cell, $preparedRow->header, $preparedRow->footer),
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
        if ($this->pendingRenderState->hasPendingRowspanCells() || $fittingRowCount !== 1 || $preparedRows === []) {
            return false;
        }

        $firstRow = $preparedRows[0];

        foreach ($firstRow->cells as $preparedCell) {
            if ($preparedCell->cell->rowspan <= 1) {
                continue;
            }

            return true;
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
    private function prepareRowGroup(array $rows, bool $header, bool $footer, string $rowspanErrorMessage): array
    {
        $previousRowspans = $this->activeRowspans;
        $this->activeRowspans = array_fill(0, count($this->columnWidths), 0);
        $preparedRows = [];

        foreach ($rows as $row) {
            $preparedRow = $this->prepareRow($row, $header, $footer);
            $preparedRows[] = new PreparedTableRow($preparedRow['cells'], $header, $footer);
            $this->activeRowspans = $preparedRow['nextRowspans'];
        }

        if ($this->hasActiveRowspans()) {
            throw new InvalidArgumentException($rowspanErrorMessage);
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
            $rowStructElem = $this->createTableRowStructElem();

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
                    $this->createTableCellStructElem($preparedCell->cell, $preparedRow->header, $rowStructElem),
                    $this->footerStyle,
                    $preparedRow->footer,
                );
            }

            $rowTopY -= $rowHeights[$rowIndex];
        }

        $this->cursorY = $rowTopY;
    }

    private function finalize(): void
    {
        if ($this->footersRendered || $this->footerRows === []) {
            return;
        }

        if ($this->hasActiveRowspans()) {
            throw new InvalidArgumentException('Rowspan groups must be completed before footer rows are rendered.');
        }

        $preparedFooterRows = $this->prepareRowGroup(
            $this->footerRows,
            false,
            true,
            'Footer rowspans must be completed within the footer rows.',
        );
        $footerHeights = $this->rowGroupHeightResolver->resolve($preparedFooterRows);
        $footerHeight = array_sum($footerHeights);
        $availableHeight = $this->cursorY - $this->bottomMargin;

        if ($footerHeight > $availableHeight) {
            $this->page = $this->page->getDocument()->addPage($this->page->getWidth(), $this->page->getHeight());
            $this->cursorY = $this->page->getHeight() - $this->continuationTopMargin;

            if ($footerHeight > ($this->cursorY - $this->bottomMargin)) {
                throw new InvalidArgumentException('Table footer rows must fit on a fresh page.');
            }
        }

        $this->renderPendingGroup($preparedFooterRows, $footerHeights);
        $this->footersRendered = true;
    }

    private function resolveEffectiveCellStyle(TableCell $cell, bool $header, bool $footer = false): ResolvedTableCellStyle
    {
        return $this->styleResolver->resolveCellStyle(
            $this->style,
            $this->rowStyle,
            $this->headerStyle,
            $cell,
            $header,
            $this->footerStyle,
            $footer,
        );
    }

    /**
     * @param list<string|list<TextSegment>|TableCell> $cells
     * @return array{cells: list<PreparedTableCell>, nextRowspans: list<int>}
     */
    private function prepareRow(array $cells, bool $header, bool $footer = false): array
    {
        return $this->createRowPreparer()->prepareRow($cells, $this->activeRowspans, $header, $footer);
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
            $this->textMetrics,
            $this->footerStyle,
        );
    }

    private function createTableRowStructElem(): ?StructElem
    {
        if ($this->tableStructElem === null) {
            return null;
        }

        return $this->page->getDocument()->createStructElem(StructureTag::TableRow, parent: $this->tableStructElem);
    }

    private function createTableCellStructElem(TableCell $cell, bool $header, ?StructElem $rowStructElem): ?StructElem
    {
        if ($rowStructElem === null) {
            return null;
        }

        $headerScope = $this->resolveTableCellHeaderScope($cell, $header);

        $structElem = $this->page->getDocument()->createStructElem(
            $headerScope === null ? StructureTag::TableDataCell : StructureTag::TableHeaderCell,
            parent: $rowStructElem,
        );

        if ($headerScope !== null) {
            $structElem->setScope($headerScope->value);
        }

        if ($cell->rowspan > 1) {
            $structElem->setRowSpan($cell->rowspan);
        }

        if ($cell->colspan > 1) {
            $structElem->setColSpan($cell->colspan);
        }

        return $structElem;
    }

    private function resolveTableCellHeaderScope(TableCell $cell, bool $header): ?TableHeaderScope
    {
        if ($cell->headerScope !== null) {
            return $cell->headerScope;
        }

        return $header ? TableHeaderScope::Column : null;
    }

    /**
     * @param list<float> $rowHeights
     */
    private function renderCaptionIfNeeded(array $rowHeights): void
    {
        $caption = $this->caption;

        if ($caption === null || $this->captionRendered) {
            return;
        }

        [$captionLines, $captionFont, $captionSize, $captionLineHeight] = $this->resolveCaptionLayout();
        $captionHeight = (count($captionLines) * $captionLineHeight) + $caption->spacingAfter;
        $availableHeight = $this->cursorY - $this->bottomMargin;
        $firstRowHeight = $rowHeights[0] ?? 0.0;

        if ($captionHeight > $availableHeight || ($captionHeight + $firstRowHeight) > $availableHeight) {
            $this->moveToNextPageForCaption();
        }

        $captionStructElem = $this->createCaptionStructElem();
        $this->page = $this->page->renderParagraphLines(
            $captionLines,
            $this->x,
            $this->cursorY,
            $this->width,
            $captionFont,
            $captionSize,
            $captionStructElem !== null ? StructureTag::Paragraph : null,
            $captionStructElem,
            $captionLineHeight,
            $this->bottomMargin,
        );
        $this->cursorY -= (count($captionLines) * $captionLineHeight) + $caption->spacingAfter;
        $this->captionRendered = true;
    }

    /**
     * @return array{0: list<array{segments: array<int, TextSegment>, justify: bool}>, 1: string, 2: int, 3: float}
     */
    private function resolveCaptionLayout(): array
    {
        $caption = $this->caption;

        if ($caption === null) {
            throw new InvalidArgumentException('Table caption is not configured.');
        }

        $captionFont = $caption->fontName ?? $this->baseFont;
        $captionSize = $caption->size ?? $this->fontSize;
        $captionLineHeight = $captionSize * $this->lineHeightFactor;

        return [
            $this->page->layoutParagraphLines(
                $caption->text,
                $captionFont,
                $captionSize,
                $this->width,
                $caption->color,
            ),
            $captionFont,
            $captionSize,
            $captionLineHeight,
        ];
    }

    private function createCaptionStructElem(): ?StructElem
    {
        if ($this->tableStructElem === null) {
            return null;
        }

        return $this->page->getDocument()->createStructElem(StructureTag::Caption, parent: $this->tableStructElem);
    }

    private function moveToNextPageForCaption(): void
    {
        $this->page = $this->page->getDocument()->addPage($this->page->getWidth(), $this->page->getHeight());
        $this->cursorY = $this->page->getHeight() - $this->topMargin;
    }
}
