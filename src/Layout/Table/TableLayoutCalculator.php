<?php

declare(strict_types=1);

namespace Kalle\Pdf\Layout\Table;

use function array_map;
use function array_sum;
use function count;

use InvalidArgumentException;

use Kalle\Pdf\Document\Table;

use Kalle\Pdf\Document\TableRow;
use Kalle\Pdf\Document\TextFlow;
use Kalle\Pdf\Font\EmbeddedFontDefinition;
use Kalle\Pdf\Font\StandardFontDefinition;
use Kalle\Pdf\Text\TextOptions;

use function max;

final class TableLayoutCalculator
{
    /**
     * @return list<float>
     */
    public function resolveColumnWidths(Table $table, float $availableWidth): array
    {
        if ($availableWidth <= 0.0) {
            throw new InvalidArgumentException('Available table width must be greater than zero.');
        }

        $fixedWidth = 0.0;
        $totalWeight = 0.0;

        foreach ($table->columns as $column) {
            if ($column->width->isFixed()) {
                $fixedWidth += $column->width->value;

                continue;
            }

            $totalWeight += $column->width->value;
        }

        if ($fixedWidth > $availableWidth) {
            throw new InvalidArgumentException('Fixed table columns exceed the available table width.');
        }

        $remainingWidth = $availableWidth - $fixedWidth;

        if ($remainingWidth <= 0.0 && $totalWeight > 0.0) {
            throw new InvalidArgumentException('Proportional table columns require remaining width.');
        }

        return array_map(
            static function ($column) use ($remainingWidth, $totalWeight): float {
                if ($column->width->isFixed()) {
                    return $column->width->value;
                }

                return $remainingWidth * ($column->width->value / $totalWeight);
            },
            $table->columns,
        );
    }

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
                $wrappedLines = $textFlow->wrapTextLines($cell->text, $cellOptions, $font, 0.0);
                $lineCount = max(count($wrappedLines), 1);
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

        return new TableLayout($columnWidths, $rowHeights, $cellLayouts, $this->buildRowGroups($cellLayouts, $rowHeights));
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

    private function cellTextOptions(TextOptions $options, \Kalle\Pdf\Document\TableCell $cell, float $contentWidth): TextOptions
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
}
