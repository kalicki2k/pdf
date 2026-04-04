<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use InvalidArgumentException;
use Kalle\Pdf\Graphics\Color;
use Kalle\Pdf\Graphics\Opacity;

final class Table
{
    private const DEFAULT_LINE_HEIGHT_FACTOR = 1.2;
    private const CELL_BOTTOM_EPSILON = 0.01;

    /** @var list<list<string|list<TextSegment>|TableCell>> */
    private array $headerRows = [];
    /** @var list<int> */
    private array $activeRowspans = [];
    /**
     * @var list<array{
     *     cells: list<array{cell: TableCell, width: float, column: int, minHeight: float, contentHeight: float}>,
     *     header: bool
     * }>
     */
    private array $pendingGroupRows = [];
    private readonly float $topMargin;
    private Page $page;
    private float $cursorY;
    private TablePadding $padding;
    private string $baseFont = 'Helvetica';
    private int $fontSize = 12;
    private float $lineHeightFactor = self::DEFAULT_LINE_HEIGHT_FACTOR;
    private ?TableBorder $border = null;
    private ?Color $rowFillColor = null;
    private ?Color $rowTextColor = null;
    private ?Color $headerFillColor = null;
    private ?Color $headerTextColor = null;
    private VerticalAlign $verticalAlign = VerticalAlign::TOP;

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
        $this->padding = TablePadding::all(6.0);
        $this->border = TableBorder::all(color: Color::gray(0.75));
        $this->headerFillColor = Color::gray(0.92);
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

    public function padding(float $padding): self
    {
        $this->padding = TablePadding::all($padding);

        return $this;
    }

    public function paddingStyle(TablePadding $padding): self
    {
        $this->padding = $padding;

        return $this;
    }

    public function border(?Color $color = null, ?float $width = null, ?Opacity $opacity = null): self
    {
        if ($width !== null && $width <= 0) {
            throw new InvalidArgumentException('Table border width must be greater than zero.');
        }

        $resolvedWidth = $width ?? $this->border->width ?? 1.0;

        $this->border = TableBorder::all(
            $resolvedWidth,
            $color,
            $opacity,
        );

        return $this;
    }

    public function borderStyle(?TableBorder $border): self
    {
        $this->border = $border;

        return $this;
    }

    public function rowStyle(?Color $fillColor = null, ?Color $textColor = null): self
    {
        $this->rowFillColor = $fillColor;
        $this->rowTextColor = $textColor;

        return $this;
    }

    public function headerStyle(?Color $fillColor = null, ?Color $textColor = null): self
    {
        $this->headerFillColor = $fillColor;
        $this->headerTextColor = $textColor;

        return $this;
    }

    public function verticalAlign(VerticalAlign $verticalAlign): self
    {
        $this->verticalAlign = $verticalAlign;

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
        $this->pendingGroupRows[] = [
            'cells' => $preparedRow['cells'],
            'header' => $header,
        ];
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

        /** @var list<array{
         *     cells: list<array{cell: TableCell, width: float, column: int, minHeight: float, contentHeight: float, padding: TablePadding}>,
         *     header: bool
         * }> $pendingGroupRows
         */
        $pendingGroupRows = $this->pendingGroupRows;

        $rowHeights = $this->resolvePendingGroupRowHeights($pendingGroupRows);
        $groupHeight = array_sum($rowHeights);
        $isBodyGroup = array_any(
            $pendingGroupRows,
            static fn (array $row): bool => $row['header'] === false,
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
     * @return array{
     *     cells: list<array{cell: TableCell, width: float, column: int, minHeight: float, contentHeight: float, padding: TablePadding}>,
     *     nextRowspans: list<int>
     * }
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
            $padding = $preparedCell->padding ?? $this->padding;
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
            $preparedCells[] = [
                'cell' => $preparedCell,
                'width' => $columnWidth,
                'column' => $columnIndex,
                'minHeight' => $cellHeight,
                'contentHeight' => $contentHeight,
                'padding' => $padding,
            ];

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
     * @param list<array{
     *     cells: list<array{cell: TableCell, width: float, column: int, minHeight: float, contentHeight: float, padding: TablePadding}>,
     *     header: bool
     * }> $preparedRows
     * @return list<float>
     */
    private function resolvePendingGroupRowHeights(array $preparedRows): array
    {
        $rowHeights = array_fill(0, count($preparedRows), 0.0);

        foreach ($preparedRows as $rowIndex => $preparedRow) {
            foreach ($preparedRow['cells'] as $preparedEntry) {
                if ($preparedEntry['cell']->rowspan === 1) {
                    $rowHeights[$rowIndex] = max($rowHeights[$rowIndex], $preparedEntry['minHeight']);
                }
            }
        }

        foreach ($preparedRows as $rowIndex => $preparedRow) {
            foreach ($preparedRow['cells'] as $preparedEntry) {
                $rowspan = $preparedEntry['cell']->rowspan;

                if ($rowspan === 1) {
                    continue;
                }

                if (($rowIndex + $rowspan) > count($preparedRows)) {
                    throw new InvalidArgumentException('Rowspan groups must be completed by subsequent rows.');
                }

                $currentHeight = array_sum(array_slice($rowHeights, $rowIndex, $rowspan));
                $missingHeight = $preparedEntry['minHeight'] - $currentHeight;

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

            $style = $cell->style;

            return new TableCell(
                $this->normalizeText($cell->text, $header),
                $style !== null && $style->horizontalAlign !== null ? $style->horizontalAlign : $cell->align,
                $cell->fillColor ?? $style?->fillColor,
                $cell->textColor ?? $style?->textColor,
                $cell->opacity ?? $style?->opacity,
                $cell->colspan,
                $cell->rowspan,
                $cell->border ?? $style?->border,
                $cell->verticalAlign ?? $style?->verticalAlign,
                $cell->padding ?? $style?->padding,
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
     * @return list<array{
     *     cells: list<array{cell: TableCell, width: float, column: int, minHeight: float, contentHeight: float, padding: TablePadding}>,
     *     header: bool
     * }>
     */
    private function prepareRowGroup(array $rows, bool $header): array
    {
        $previousRowspans = $this->activeRowspans;
        $this->activeRowspans = array_fill(0, count($this->columnWidths), 0);
        $preparedRows = [];

        foreach ($rows as $row) {
            $preparedRow = $this->prepareRow($row, $header);
            $preparedRows[] = [
                'cells' => $preparedRow['cells'],
                'header' => $header,
            ];
            $this->activeRowspans = $preparedRow['nextRowspans'];
        }

        if ($this->hasActiveRowspans()) {
            throw new InvalidArgumentException('Header rowspans must be completed within the header rows.');
        }

        $this->activeRowspans = $previousRowspans;

        return $preparedRows;
    }

    /**
     * @param list<array{
     *     cells: list<array{cell: TableCell, width: float, column: int, minHeight: float, contentHeight: float, padding: TablePadding}>,
     *     header: bool
     * }> $preparedRows
     * @param list<float> $rowHeights
     */
    private function renderPendingGroup(array $preparedRows, array $rowHeights): void
    {
        $lineHeight = $this->fontSize * $this->lineHeightFactor;
        $rowTopY = $this->cursorY;

        foreach ($preparedRows as $rowIndex => $preparedRow) {
            foreach ($preparedRow['cells'] as $preparedEntry) {
                $preparedCell = $preparedEntry['cell'];
                $spanHeight = array_sum(array_slice($rowHeights, $rowIndex, $preparedCell->rowspan));
                $cellX = $this->x + $this->calculateColumnOffset($preparedEntry['column']);
                $cellBottomY = $rowTopY - $spanHeight;
                $fillColor = $preparedCell->fillColor ?? ($preparedRow['header'] ? $this->headerFillColor : $this->rowFillColor);
                $textColor = $preparedCell->textColor ?? ($preparedRow['header'] ? $this->headerTextColor : $this->rowTextColor);
                $verticalAlign = $preparedCell->verticalAlign ?? $this->verticalAlign;
                $padding = $preparedEntry['padding'];
                $this->renderCellBox(
                    $cellX,
                    $cellBottomY,
                    $preparedEntry['width'],
                    $spanHeight,
                    $fillColor,
                    $this->border,
                    $preparedCell->border,
                );

                $this->page = $this->page->addParagraph(
                    $preparedCell->text,
                    $cellX + $padding->left,
                    $this->resolveCellTextStartY(
                        $rowTopY,
                        $cellBottomY,
                        $spanHeight,
                        $preparedEntry['contentHeight'],
                        $verticalAlign,
                        $padding,
                    ),
                    $preparedEntry['width'] - $padding->horizontal(),
                    $this->baseFont,
                    $this->fontSize,
                    null,
                    $lineHeight,
                    ($cellBottomY + $padding->bottom) - self::CELL_BOTTOM_EPSILON,
                    $textColor,
                    $preparedCell->opacity,
                    $preparedCell->align,
                );
            }

            $rowTopY -= $rowHeights[$rowIndex];
        }

        $this->cursorY = $rowTopY;
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
        ?TableBorder $cellBorder,
    ): void {
        if ($fillColor !== null) {
            $this->page->addRectangle($x, $y, $width, $height, null, null, $fillColor);
        }

        $topBorder = $this->resolveBorderSide('top', $defaultBorder, $cellBorder);
        $rightBorder = $this->resolveBorderSide('right', $defaultBorder, $cellBorder);
        $bottomBorder = $this->resolveBorderSide('bottom', $defaultBorder, $cellBorder);
        $leftBorder = $this->resolveBorderSide('left', $defaultBorder, $cellBorder);

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
                $topBorder['width'],
                $topBorder['color'],
                null,
                $topBorder['opacity'],
            );

            return;
        }

        if ($topBorder !== null) {
            $this->page->addLine($x, $y + $height, $x + $width, $y + $height, $topBorder['width'], $topBorder['color'], $topBorder['opacity']);
        }

        if ($rightBorder !== null) {
            $this->page->addLine($x + $width, $y, $x + $width, $y + $height, $rightBorder['width'], $rightBorder['color'], $rightBorder['opacity']);
        }

        if ($bottomBorder !== null) {
            $this->page->addLine($x, $y, $x + $width, $y, $bottomBorder['width'], $bottomBorder['color'], $bottomBorder['opacity']);
        }

        if ($leftBorder !== null) {
            $this->page->addLine($x, $y, $x, $y + $height, $leftBorder['width'], $leftBorder['color'], $leftBorder['opacity']);
        }
    }

    /**
     * @return array{width: float, color: ?Color, opacity: ?Opacity}|null
     */
    private function resolveBorderSide(string $side, ?TableBorder $defaultBorder, ?TableBorder $cellBorder): ?array
    {
        if ($cellBorder !== null && $cellBorder->isDefinedFor($side)) {
            if (!$cellBorder->isEnabled($side)) {
                return null;
            }

            return [
                'width' => $cellBorder->width ?? $defaultBorder->width ?? 1.0,
                'color' => $cellBorder->color ?? $defaultBorder?->color,
                'opacity' => $cellBorder->opacity ?? $defaultBorder?->opacity,
            ];
        }

        if ($defaultBorder === null || !$defaultBorder->isEnabled($side)) {
            return null;
        }

        return [
            'width' => $defaultBorder->width ?? 1.0,
            'color' => $defaultBorder->color,
            'opacity' => $defaultBorder->opacity,
        ];
    }

    /**
     * @param array{width: float, color: ?Color, opacity: ?Opacity} $top
     * @param array{width: float, color: ?Color, opacity: ?Opacity} $right
     * @param array{width: float, color: ?Color, opacity: ?Opacity} $bottom
     * @param array{width: float, color: ?Color, opacity: ?Opacity} $left
     */
    private function bordersAreEquivalent(array $top, array $right, array $bottom, array $left): bool
    {
        return $top === $right && $right === $bottom && $bottom === $left;
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
