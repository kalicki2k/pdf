<?php

declare(strict_types=1);

namespace Kalle\Pdf\Layout\Table;

use function array_map;
use function array_sum;
use function count;
use function max;
use function preg_split;
use function trim;

use InvalidArgumentException;
use Kalle\Pdf\Document\Table;
use Kalle\Pdf\Document\TableCell;
use Kalle\Pdf\Document\TableRow;
use Kalle\Pdf\Document\TextFlow;
use Kalle\Pdf\Font\EmbeddedFontDefinition;
use Kalle\Pdf\Font\StandardFontDefinition;
use Kalle\Pdf\Text\TextOptions;
use Kalle\Pdf\Text\TextSegment;

final class TableLayoutCalculator
{
    /**
     * @return list<float>
     */
    public function resolveColumnWidths(
        Table $table,
        float $availableWidth,
        ?TextFlow $textFlow = null,
        StandardFontDefinition | EmbeddedFontDefinition | null $font = null,
    ): array {
        if ($availableWidth <= 0.0) {
            throw new InvalidArgumentException('Available table width must be greater than zero.');
        }

        $fixedWidth = 0.0;
        $autoWidth = 0.0;
        $totalWeight = 0.0;
        $autoColumnWidths = [];

        if ($this->tableContainsAutoColumns($table)) {
            if ($textFlow === null || $font === null) {
                throw new InvalidArgumentException('Auto table columns require a text flow and font for width resolution.');
            }

            $autoColumnWidths = $this->resolveAutoColumnWidths($table, $font);
        }

        foreach ($table->columns as $index => $column) {
            if ($column->width->isFixed()) {
                $fixedWidth += $column->width->value;

                continue;
            }

            if ($column->width->isAuto()) {
                $autoWidth += $autoColumnWidths[$index] ?? 0.0;

                continue;
            }

            $totalWeight += $column->width->value;
        }

        if (($fixedWidth + $autoWidth) > $availableWidth) {
            throw new InvalidArgumentException('Fixed and auto table columns exceed the available table width.');
        }

        $remainingWidth = $availableWidth - $fixedWidth - $autoWidth;

        if ($remainingWidth <= 0.0 && $totalWeight > 0.0) {
            throw new InvalidArgumentException('Proportional table columns require remaining width.');
        }

        return array_map(
            static function ($column, int $index) use ($autoColumnWidths, $remainingWidth, $totalWeight): float {
                if ($column->width->isFixed()) {
                    return $column->width->value;
                }

                if ($column->width->isAuto()) {
                    return $autoColumnWidths[$index] ?? 0.0;
                }

                return $remainingWidth * ($column->width->value / $totalWeight);
            },
            $table->columns,
            array_keys($table->columns),
        );
    }

    /**
     * @param list<float> $columnWidths
     */
    public function layoutTable(
        Table $table,
        array $columnWidths,
        TextFlow $textFlow,
        StandardFontDefinition | EmbeddedFontDefinition $font,
    ): TableLayout {
        if (count($table->columns) !== count($columnWidths)) {
            throw new InvalidArgumentException('Resolved column widths must match the configured column count.');
        }

        return $this->layoutRows($table->rows, $table, $columnWidths, $textFlow, $font);
    }

    /**
     * @param list<TableRow> $rows
     * @param list<float> $columnWidths
     */
    public function layoutRows(
        array $rows,
        Table $table,
        array $columnWidths,
        TextFlow $textFlow,
        StandardFontDefinition | EmbeddedFontDefinition $font,
    ): TableLayout {
        if (count($table->columns) !== count($columnWidths)) {
            throw new InvalidArgumentException('Resolved column widths must match the configured column count.');
        }

        $padding = $table->cellPadding;
        $baseOptions = $table->textOptions;
        /** @var list<float> $rowHeights */
        $rowHeights = array_fill(0, count($rows), 0.0);
        $cellLayouts = [];
        $activeRowspans = array_fill(0, count($columnWidths), 0);

        foreach ($rows as $rowIndex => $row) {
            $columnIndex = 0;

            foreach ($row->cells as $cell) {
                while (($activeRowspans[$columnIndex] ?? 0) > 0) {
                    $columnIndex++;
                }

                $cellWidth = array_sum(array_slice($columnWidths, $columnIndex, $cell->colspan));
                $cellPadding = $cell->padding ?? $padding;
                $cellBorder = $cell->border ?? $table->border;
                $contentWidth = max($cellWidth - $cellPadding->horizontal(), 0.0);
                $cellOptions = $this->cellTextOptions($baseOptions, $cell, $contentWidth);
                $wrappedSegmentLines = $cell->content->isRichText()
                    ? $textFlow->wrapSegmentLines($cell->content->segments, $cellOptions, $font, 0.0)
                    : null;
                $wrappedLines = $wrappedSegmentLines !== null
                    ? $this->wrappedLineTexts($wrappedSegmentLines)
                    : $textFlow->wrapTextLines($cell->text, $cellOptions, $font, 0.0);
                $lineCount = max($wrappedSegmentLines !== null ? count($wrappedSegmentLines) : count($wrappedLines), 1);
                $cellHeight = ($lineCount * $textFlow->lineHeight($cellOptions)) + $cellPadding->vertical();
                $cellLayouts[] = new TableCellLayout(
                    $cell,
                    $rowIndex,
                    $columnIndex,
                    $cellWidth,
                    $contentWidth,
                    $cellHeight,
                    $cellPadding,
                    $cellBorder,
                    $cellOptions,
                    $wrappedLines,
                    $wrappedSegmentLines,
                );

                if ($cell->rowspan === 1) {
                    $rowHeights[$rowIndex] = max($rowHeights[$rowIndex], $cellHeight);
                }

                for ($offset = 0; $offset < $cell->colspan; $offset++) {
                    $activeRowspans[$columnIndex + $offset] = $cell->rowspan;
                }

                $columnIndex += $cell->colspan;
            }

            foreach ($activeRowspans as $index => $remainingRows) {
                if ($remainingRows > 0) {
                    $activeRowspans[$index]--;
                }
            }
        }

        foreach ($cellLayouts as $cellLayout) {
            if ($cellLayout->cell->rowspan === 1) {
                continue;
            }

            $spanHeight = 0.0;

            for ($offset = 0; $offset < $cellLayout->cell->rowspan; $offset++) {
                $spanHeight += $rowHeights[$cellLayout->rowIndex + $offset];
            }

            if ($spanHeight >= $cellLayout->height) {
                continue;
            }

            $lastRowIndex = $cellLayout->rowIndex + $cellLayout->cell->rowspan - 1;
            $rowHeights[$lastRowIndex] += $cellLayout->height - $spanHeight;
        }

        $normalizedRowHeights = array_values($rowHeights);
        /** @var list<float> $normalizedRowHeights */

        return new TableLayout(
            $columnWidths,
            $normalizedRowHeights,
            $cellLayouts,
            $this->buildRowGroups($cellLayouts, $normalizedRowHeights),
        );
    }

    /**
     * @param list<TableCellLayout> $cellLayouts
     * @param list<float> $rowHeights
     * @return list<TableRowGroupLayout>
     */
    private function buildRowGroups(array $cellLayouts, array $rowHeights): array
    {
        $groupEndByStart = [];

        foreach ($cellLayouts as $cellLayout) {
            $startRowIndex = $cellLayout->rowIndex;
            $endRowIndex = $cellLayout->rowIndex + $cellLayout->cell->rowspan - 1;
            $groupEndByStart[$startRowIndex] = max($groupEndByStart[$startRowIndex] ?? $startRowIndex, $endRowIndex);
        }

        $rowGroups = [];
        $currentStart = 0;
        $currentEnd = -1;
        $rowCount = count($rowHeights);

        for ($rowIndex = 0; $rowIndex < $rowCount; $rowIndex++) {
            if ($currentEnd < $rowIndex) {
                $currentStart = $rowIndex;
                $currentEnd = $rowIndex;
            }

            $currentEnd = max($currentEnd, $groupEndByStart[$rowIndex] ?? $rowIndex);

            if ($rowIndex !== $currentEnd) {
                continue;
            }

            $height = 0.0;

            for ($index = $currentStart; $index <= $currentEnd; $index++) {
                $height += $rowHeights[$index];
            }

            $rowGroups[] = new TableRowGroupLayout($currentStart, $currentEnd, $height);
            $currentEnd = -1;
        }

        return $rowGroups;
    }

    private function cellTextOptions(TextOptions $options, TableCell $cell, float $contentWidth): TextOptions
    {
        return new TextOptions(
            width: $contentWidth,
            fontSize: $options->fontSize,
            lineHeight: $options->lineHeight,
            fontName: $options->fontName,
            embeddedFont: $options->embeddedFont,
            fontEncoding: $options->fontEncoding,
            color: $options->color,
            kerning: $options->kerning,
            baseDirection: $options->baseDirection,
            align: $cell->horizontalAlign ?? $options->align,
        );
    }

    /**
     * @param list<list<TextSegment>> $wrappedSegmentLines
     * @return list<string>
     */
    private function wrappedLineTexts(array $wrappedSegmentLines): array
    {
        return array_map(
            static fn (array $line): string => implode('', array_map(
                static fn (TextSegment $segment): string => $segment->text,
                $line,
            )),
            $wrappedSegmentLines,
        );
    }

    private function tableContainsAutoColumns(Table $table): bool
    {
        foreach ($table->columns as $column) {
            if ($column->width->isAuto()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<float>
     */
    private function resolveAutoColumnWidths(
        Table $table,
        StandardFontDefinition | EmbeddedFontDefinition $font,
    ): array {
        $autoWidths = array_fill(0, count($table->columns), 0.0);
        $rows = [...$table->headerRows, ...$table->rows, ...$table->footerRows];

        foreach ($rows as $row) {
            $columnIndex = 0;

            foreach ($row->cells as $cell) {
                $autoColumns = [];
                $fixedWidthInSpan = 0.0;

                for ($index = $columnIndex; $index < ($columnIndex + $cell->colspan); $index++) {
                    $column = $table->columns[$index];

                    if ($column->width->isFixed()) {
                        $fixedWidthInSpan += $column->width->value;
                    }

                    if ($column->width->isAuto()) {
                        $autoColumns[] = $index;
                    }
                }

                if ($autoColumns !== []) {
                    $requiredWidth = $this->minimumCellWidth($table, $cell, $font);
                    $share = max($requiredWidth - $fixedWidthInSpan, 0.0) / count($autoColumns);

                    foreach ($autoColumns as $autoColumnIndex) {
                        $autoWidths[$autoColumnIndex] = max($autoWidths[$autoColumnIndex], $share);
                    }
                }

                $columnIndex += $cell->colspan;
            }
        }

        return array_values($autoWidths);
    }

    private function minimumCellWidth(
        Table $table,
        TableCell $cell,
        StandardFontDefinition | EmbeddedFontDefinition $font,
    ): float {
        $padding = $cell->padding ?? $table->cellPadding;
        $contentWidth = $cell->content->isRichText()
            ? $this->minimumRichTextWidth($cell->content->segments, $table->textOptions, $font)
            : $this->minimumPlainTextWidth($cell->text, $table->textOptions, $font);

        return $contentWidth + $padding->horizontal();
    }

    private function minimumPlainTextWidth(
        string $text,
        TextOptions $options,
        StandardFontDefinition | EmbeddedFontDefinition $font,
    ): float {
        $widestToken = 0.0;

        foreach ($this->tokenizeForMinimumWidth($text) as $token) {
            if (trim($token) === '') {
                continue;
            }

            $widestToken = max($widestToken, $font->measureTextWidth($token, $options->fontSize));
        }

        return $widestToken;
    }

    /**
     * @param list<TextSegment> $segments
     */
    private function minimumRichTextWidth(
        array $segments,
        TextOptions $options,
        StandardFontDefinition | EmbeddedFontDefinition $font,
    ): float {
        $widestToken = 0.0;
        $currentTokenWidth = 0.0;

        foreach ($segments as $segment) {
            foreach ($this->tokenizeForMinimumWidth($segment->text) as $token) {
                if (trim($token) === '') {
                    $widestToken = max($widestToken, $currentTokenWidth);
                    $currentTokenWidth = 0.0;

                    continue;
                }

                $currentTokenWidth += $font->measureTextWidth($token, $options->fontSize);
            }
        }

        return max($widestToken, $currentTokenWidth);
    }

    /**
     * @return list<string>
     */
    private function tokenizeForMinimumWidth(string $text): array
    {
        return preg_split('/(\s+)/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [$text];
    }
}
