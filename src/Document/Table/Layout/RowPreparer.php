<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document\Table\Layout;

use InvalidArgumentException;
use Kalle\Pdf\Document\Page;
use Kalle\Pdf\Document\Table\Style\CellStyle;
use Kalle\Pdf\Document\Table\Style\HeaderStyle;
use Kalle\Pdf\Document\Table\Style\RowStyle;
use Kalle\Pdf\Document\Table\Style\TableStyle;
use Kalle\Pdf\Document\Table\Support\TableStyleResolver;
use Kalle\Pdf\Document\Table\Support\TableTextMetrics;
use Kalle\Pdf\Document\Table\TableCell;
use Kalle\Pdf\Document\Text\TextSegment;

final readonly class RowPreparer
{
    /**
     * @param list<float|int> $columnWidths
     */
    public function __construct(
        private Page $page,
        private array $columnWidths,
        private string $baseFont,
        private int $fontSize,
        private float $lineHeightFactor,
        private TableStyle $tableStyle,
        private ?RowStyle $rowStyle,
        private ?HeaderStyle $headerStyle,
        private TableStyleResolver $styleResolver,
        private TableTextMetrics $textMetrics,
    ) {
    }

    /**
     * @param list<string|list<TextSegment>|TableCell> $cells
     * @param list<int> $activeRowspans
     * @return array{cells: list<PreparedTableCell>, nextRowspans: list<int>}
     */
    public function prepareRow(array $cells, array $activeRowspans, bool $header): array
    {
        $preparedCells = [];
        $lineHeight = $this->fontSize * $this->lineHeightFactor;
        $nextRowspans = array_map(
            static fn (int $remainingRows): int => max(0, $remainingRows - 1),
            $activeRowspans,
        );
        $columnIndex = 0;

        foreach ($cells as $cell) {
            while ($columnIndex < count($this->columnWidths) && $activeRowspans[$columnIndex] > 0) {
                $columnIndex++;
            }

            $preparedCell = $this->normalizeCell($cell, $header);
            $resolvedStyle = $this->styleResolver->resolveCellStyle(
                $this->tableStyle,
                $this->rowStyle,
                $this->headerStyle,
                $preparedCell,
                $header,
            );
            $padding = $resolvedStyle->padding;
            $columnWidth = $this->resolveColumnSpanWidth($columnIndex, $preparedCell->colspan, $activeRowspans);
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

            $alignmentHeight = $this->textMetrics->resolveAlignmentHeight($lineCount, $this->fontSize, $lineHeight);
            $contentHeight = $this->textMetrics->resolveContentHeight($lineCount, $this->fontSize, $lineHeight);
            $cellHeight = $contentHeight + $padding->vertical();
            $preparedCells[] = new PreparedTableCell(
                $preparedCell,
                $columnWidth,
                $columnIndex,
                $cellHeight,
                $contentHeight,
                $alignmentHeight,
                $resolvedStyle->padding,
            );

            if ($preparedCell->rowspan > 1) {
                for ($offset = 0; $offset < $preparedCell->colspan; $offset++) {
                    $nextRowspans[$columnIndex + $offset] = $preparedCell->rowspan - 1;
                }
            }

            $columnIndex += $preparedCell->colspan;
        }

        while ($columnIndex < count($this->columnWidths) && $activeRowspans[$columnIndex] > 0) {
            $columnIndex++;
        }

        if ($columnIndex !== count($this->columnWidths)) {
            throw new InvalidArgumentException('Table row spans must match the number of columns.');
        }

        return ['cells' => $preparedCells, 'nextRowspans' => array_values($nextRowspans)];
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
