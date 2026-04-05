<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use InvalidArgumentException;
use Kalle\Pdf\Document\Table\Layout\CellLayoutResolver;
use Kalle\Pdf\Document\Table\Layout\PreparedTableCell;
use Kalle\Pdf\Document\Table\Layout\PreparedTableRow;
use Kalle\Pdf\Document\Table\Layout\RowGroupHeightResolver;
use Kalle\Pdf\Document\Table\Layout\RowPreparer;
use Kalle\Pdf\Document\Table\Rendering\CellBoxRenderer;
use Kalle\Pdf\Document\Table\Rendering\PreparedCellRenderer;
use Kalle\Pdf\Document\Table\Style\HeaderStyle;
use Kalle\Pdf\Document\Table\Style\RowStyle;
use Kalle\Pdf\Document\Table\Style\TableBorder;
use Kalle\Pdf\Document\Table\Style\TablePadding;
use Kalle\Pdf\Document\Table\Style\TableStyle;
use Kalle\Pdf\Document\Table\Support\TableStyleResolver;
use Kalle\Pdf\Document\Table\TableCell;
use Kalle\Pdf\Graphics\Color;
use Kalle\Pdf\Layout\VerticalAlign;

final class Table
{
    private const DEFAULT_LINE_HEIGHT_FACTOR = 1.2;

    /** @var list<list<string|list<TextSegment>|TableCell>> */
    private array $headerRows = [];
    /** @var list<int> */
    private array $activeRowspans = [];
    /** @var list<PreparedTableRow> */
    private array $pendingGroupRows = [];
    private readonly float $topMargin;
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
    private readonly CellLayoutResolver $cellLayoutResolver;
    private readonly CellBoxRenderer $cellBoxRenderer;
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
        $this->activeRowspans = array_fill(0, count($columnWidths), 0);
        $this->styleResolver = new TableStyleResolver();
        $this->cellLayoutResolver = new CellLayoutResolver($this->x, $this->columnWidths);
        $this->rowGroupHeightResolver = new RowGroupHeightResolver();
        $this->cellBoxRenderer = new CellBoxRenderer($this->styleResolver);
        $this->preparedCellRenderer = new PreparedCellRenderer(
            $this->styleResolver,
            $this->cellLayoutResolver,
            $this->cellBoxRenderer,
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
        $groupHeight = array_sum($rowHeights);
        $isBodyGroup = array_any(
            $pendingGroupRows,
            static fn (PreparedTableRow $row): bool => $row->header === false,
        );

        $this->ensureGroupFitsOnCurrentPage($groupHeight, $isBodyGroup);
        $this->renderPendingGroup($pendingGroupRows, $rowHeights);
        $this->pendingGroupRows = [];
    }

    private function ensureGroupFitsOnCurrentPage(float $groupHeight, bool $repeatHeaders): void
    {
        if ($this->cursorY - $groupHeight >= $this->bottomMargin) {
            return;
        }

        $fullPageAvailableHeight = $this->page->getHeight() - $this->topMargin - $this->bottomMargin;
        if ($groupHeight > $fullPageAvailableHeight) {
            throw new InvalidArgumentException('Rowspan groups cannot cross page boundaries.');
        }

        $this->page = $this->page->getDocument()->addPage($this->page->getWidth(), $this->page->getHeight());
        $this->cursorY = $this->page->getHeight() - $this->topMargin;

        if (!$repeatHeaders || $this->headerRows === []) {
            return;
        }

        $preparedHeaderRows = $this->prepareRowGroup($this->headerRows, true);
        $headerHeights = $this->rowGroupHeightResolver->resolve($preparedHeaderRows);
        $headerHeight = array_sum($headerHeights);

        if (($headerHeight + $groupHeight) > $fullPageAvailableHeight) {
            throw new InvalidArgumentException('Rowspan groups cannot cross page boundaries.');
        }

        $this->renderPendingGroup($preparedHeaderRows, $headerHeights);
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
