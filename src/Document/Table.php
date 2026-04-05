<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use InvalidArgumentException;
use Kalle\Pdf\Document\Table\Layout\PreparedTableCell;
use Kalle\Pdf\Document\Table\Layout\PreparedTableCellGeometry;
use Kalle\Pdf\Document\Table\Layout\PreparedTableRow;
use Kalle\Pdf\Document\Table\Style\CellStyle;
use Kalle\Pdf\Document\Table\Style\HeaderStyle;
use Kalle\Pdf\Document\Table\Style\RowStyle;
use Kalle\Pdf\Document\Table\Style\TableBorder;
use Kalle\Pdf\Document\Table\Style\TablePadding;
use Kalle\Pdf\Document\Table\Style\TableStyle;
use Kalle\Pdf\Document\Table\Support\ResolvedBorderSide;
use Kalle\Pdf\Document\Table\Support\ResolvedTableCellStyle;
use Kalle\Pdf\Document\Table\TableCell;
use Kalle\Pdf\Graphics\Color;
use Kalle\Pdf\Graphics\Opacity;
use Kalle\Pdf\Layout\HorizontalAlign;
use Kalle\Pdf\Layout\VerticalAlign;

final class Table
{
    private const DEFAULT_LINE_HEIGHT_FACTOR = 1.2;
    private const CELL_BOTTOM_EPSILON = 0.01;

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
        $headerHeights = $this->resolvePendingGroupRowHeights($preparedHeaderRows);
        $headerHeight = array_sum($headerHeights);

        if (($headerHeight + $groupHeight) > $fullPageAvailableHeight) {
            throw new InvalidArgumentException('Rowspan groups cannot cross page boundaries.');
        }

        $this->renderPendingGroup($preparedHeaderRows, $headerHeights);
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
        $geometry = $this->resolvePreparedCellGeometry($preparedCell, $rowIndex, $rowHeights, $rowTopY, $resolvedStyle);

        $this->renderCellBox(
            $geometry->x,
            $geometry->bottomY,
            $geometry->width,
            $geometry->height,
            $resolvedStyle->fillColor,
            $this->style->border,
            $resolvedStyle->rowBorder,
            $resolvedStyle->cellBorder,
        );

        $this->page = $this->page->addParagraph(
            $preparedCell->cell->text,
            $geometry->textX,
            $geometry->textY,
            $geometry->textWidth,
            $this->baseFont,
            $this->fontSize,
            null,
            $lineHeight,
            $geometry->bottomLimitY,
            $resolvedStyle->textColor,
            $resolvedStyle->opacity,
            $resolvedStyle->horizontalAlign,
        );
    }

    /**
     * @param list<float> $rowHeights
     */
    private function resolvePreparedCellGeometry(
        PreparedTableCell $preparedCell,
        int $rowIndex,
        array $rowHeights,
        float $rowTopY,
        ResolvedTableCellStyle $resolvedStyle,
    ): PreparedTableCellGeometry {
        $height = array_sum(array_slice($rowHeights, $rowIndex, $preparedCell->cell->rowspan));
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
    ): void {
        if ($fillColor !== null) {
            $this->page->addRectangle($x, $y, $width, $height, null, null, $fillColor);
        }

        $topBorder = $this->resolveBorderSide('top', $defaultBorder, $rowBorder, $cellBorder);
        $rightBorder = $this->resolveBorderSide('right', $defaultBorder, $rowBorder, $cellBorder);
        $bottomBorder = $this->resolveBorderSide('bottom', $defaultBorder, $rowBorder, $cellBorder);
        $leftBorder = $this->resolveBorderSide('left', $defaultBorder, $rowBorder, $cellBorder);

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
    ): bool {
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
