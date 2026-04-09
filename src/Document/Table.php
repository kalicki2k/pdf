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
use Kalle\Pdf\Document\Table\Rendering\TableCaptionRenderer;
use Kalle\Pdf\Document\Table\Rendering\TableGroupRenderer;
use Kalle\Pdf\Document\Table\Rendering\TablePendingGroupPaginator;
use Kalle\Pdf\Document\Table\Rendering\TablePendingRenderState;
use Kalle\Pdf\Document\Table\Rendering\TableStructElemFactory;
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
use Kalle\Pdf\Document\Table\TableSections;
use Kalle\Pdf\Document\Text\StructureTag;
use Kalle\Pdf\Document\Text\TextSegment;
use Kalle\Pdf\Graphics\Color;
use Kalle\Pdf\Layout\VerticalAlign;
use Kalle\Pdf\Structure\StructElem;

final class Table
{
    private const DEFAULT_LINE_HEIGHT_FACTOR = 1.2;
    private const DEFAULT_CONTINUATION_TOP_MARGIN = 40.0;

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
    private readonly TableCaptionRenderer $captionRenderer;
    private readonly TableGroupRenderer $groupRenderer;
    private readonly TablePendingGroupPaginator $pendingGroupPaginator;
    private readonly TablePendingRenderState $pendingRenderState;
    private readonly TableStructElemFactory $structElemFactory;
    private readonly TableSections $sections;
    private readonly ?StructElem $tableStructElem;
    private ?TableCaption $caption = null;

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
        $this->captionRenderer = new TableCaptionRenderer();
        $this->structElemFactory = new TableStructElemFactory();
        $this->groupRenderer = new TableGroupRenderer();
        $this->pendingGroupPaginator = new TablePendingGroupPaginator();
        $this->pendingRenderState = new TablePendingRenderState();
        $this->sections = new TableSections();
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
        if (!$this->sections->canConfigureCaption()) {
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
        $this->sections->markBodyRowsAdded();

        return $this->addTypedRow($cells, false);
    }

    /**
     * @param list<string|list<TextSegment>|TableCell> $cells
     */
    public function addHeaderRow(array $cells, bool $repeat = true): self
    {
        if (!$this->sections->canAddHeaderRows()) {
            throw new InvalidArgumentException('Header rows must be added before body or footer rows.');
        }

        if ($repeat) {
            $this->sections->addRepeatingHeaderRow($cells);
        }

        return $this->addTypedRow($cells, true);
    }

    /**
     * @param list<string|list<TextSegment>|TableCell> $cells
     */
    public function addFooterRow(array $cells): self
    {
        $this->sections->addFooterRow($cells);

        return $this;
    }

    /**
     * @param list<string|list<TextSegment>|TableCell> $cells
     */
    private function addTypedRow(array $cells, bool $header): self
    {
        $this->sections->markRowsAdded();

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
        $repeatHeaders = $isBodyGroup && $this->sections->hasRepeatingHeaderRows();
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
                && $this->pendingGroupPaginator->shouldDeferLeadingSplit(
                    $remainingRows,
                    $this->pendingRenderState->hasPendingRowspanCells(),
                    $fittingRowCount,
                )
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
        if ($repeatHeaders && $this->sections->hasRepeatingHeaderRows()) {
            $preparedHeaderRows = $this->prepareRowGroup(
                $this->sections->repeatingHeaderRows(),
                true,
                false,
                'Header rowspans must be completed within the repeated header rows.',
            );
            $this->rowGroupHeightResolver->resolve($preparedHeaderRows);
        }

        return $this->pendingGroupPaginator->resolvePageFit(
            $rowHeights,
            $this->cursorY - $this->bottomMargin,
            $repeatHeaders && $this->sections->hasRepeatingHeaderRows(),
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
            $this->sections->repeatingHeaderRows(),
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
            $rowStructElem = $this->structElemFactory->createRow($this->page, $this->tableStructElem);

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
                    $this->structElemFactory->createCell(
                        $this->page,
                        $preparedCell->cell,
                        $preparedRow->header,
                        $rowStructElem,
                    ),
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
        $result = $this->groupRenderer->render(
            $this->page,
            $preparedRows,
            $rowHeights,
            $this->cursorY,
            $this->preparedCellRenderer,
            $this->style,
            $this->rowStyle,
            $this->headerStyle,
            $this->footerStyle,
            $this->baseFont,
            $this->fontSize,
            $this->lineHeightFactor,
            $this->tableStructElem,
        );
        $this->page = $result->page;
        $this->cursorY = $result->cursorY;
    }

    private function finalize(): void
    {
        if ($this->sections->areFootersRendered() || !$this->sections->hasFooterRows()) {
            return;
        }

        if ($this->hasActiveRowspans()) {
            throw new InvalidArgumentException('Rowspan groups must be completed before footer rows are rendered.');
        }

        $preparedFooterRows = $this->prepareRowGroup(
            $this->sections->footerRows(),
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
        $this->sections->markFootersRendered();
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

    /**
     * @param list<float> $rowHeights
     */
    private function renderCaptionIfNeeded(array $rowHeights): void
    {
        $caption = $this->caption;

        if ($caption === null || $this->sections->isCaptionRendered()) {
            return;
        }

        $result = $this->captionRenderer->render(
            $caption,
            $this->page,
            $this->cursorY,
            $this->topMargin,
            $this->bottomMargin,
            $this->x,
            $this->width,
            $rowHeights[0] ?? 0.0,
            $this->baseFont,
            $this->fontSize,
            $this->lineHeightFactor,
            $this->tableStructElem,
        );
        $this->page = $result->page;
        $this->cursorY = $result->cursorY;
        $this->sections->markCaptionRendered();
    }
}
