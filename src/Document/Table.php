<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use InvalidArgumentException;
use Kalle\Pdf\Graphics\Color;
use Kalle\Pdf\Graphics\Opacity;

final class Table
{
    private const DEFAULT_LINE_HEIGHT_FACTOR = 1.2;

    private Page $page;
    private float $cursorY;
    private float $padding = 6.0;
    private string $baseFont = 'Helvetica';
    private int $fontSize = 12;
    private float $lineHeightFactor = self::DEFAULT_LINE_HEIGHT_FACTOR;
    private ?Color $borderColor = null;
    private float $borderWidth = 1.0;
    private ?Opacity $borderOpacity = null;
    private ?Color $rowFillColor = null;
    private ?Color $rowTextColor = null;
    private ?Color $headerFillColor = null;
    private ?Color $headerTextColor = null;

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

        $totalColumnWidth = array_sum(array_map(static fn (float|int $value): float => (float) $value, $columnWidths));

        if (abs($totalColumnWidth - $width) > 0.001) {
            throw new InvalidArgumentException('Table column widths must add up to the table width.');
        }

        if ($bottomMargin < 0) {
            throw new InvalidArgumentException('Table bottom margin must be zero or greater.');
        }

        $this->page = $page;
        $this->cursorY = $y;
        $this->borderColor = Color::gray(0.75);
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
        if ($padding < 0) {
            throw new InvalidArgumentException('Table cell padding must be zero or greater.');
        }

        $this->padding = $padding;

        return $this;
    }

    public function border(?Color $color = null, ?float $width = null, ?Opacity $opacity = null): self
    {
        if ($width !== null && $width <= 0) {
            throw new InvalidArgumentException('Table border width must be greater than zero.');
        }

        $this->borderColor = $color;
        $this->borderWidth = $width ?? $this->borderWidth;
        $this->borderOpacity = $opacity;

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

    /**
     * @param list<string|list<TextSegment>|TableCell> $cells
     */
    public function addRow(array $cells, bool $header = false): self
    {
        if (count($cells) !== count($this->columnWidths)) {
            throw new InvalidArgumentException('Table row cell count must match the number of columns.');
        }

        $preparedCells = [];
        $lineHeight = $this->fontSize * $this->lineHeightFactor;
        $rowHeight = 0.0;

        foreach ($cells as $columnIndex => $cell) {
            $preparedCell = $this->normalizeCell($cell, $header);
            $contentWidth = (float) $this->columnWidths[$columnIndex] - (2 * $this->padding);

            if ($contentWidth <= 0) {
                throw new InvalidArgumentException('Table column width must be greater than the horizontal cell padding.');
            }

            $lineCount = $this->page->countParagraphLines(
                $preparedCell->text,
                $this->baseFont,
                $this->fontSize,
                $contentWidth,
            );

            $cellHeight = ($this->fontSize + (max(0, $lineCount - 1) * $lineHeight)) + (2 * $this->padding);
            $rowHeight = max($rowHeight, $cellHeight);
            $preparedCells[] = $preparedCell;
        }

        $this->ensureRowFitsOnCurrentPage($rowHeight);

        $rowBottomY = $this->cursorY - $rowHeight;
        $cellX = $this->x;

        foreach ($preparedCells as $columnIndex => $preparedCell) {
            $columnWidth = (float) $this->columnWidths[$columnIndex];
            $fillColor = $preparedCell->fillColor ?? ($header ? $this->headerFillColor : $this->rowFillColor);
            $textColor = $preparedCell->textColor ?? ($header ? $this->headerTextColor : $this->rowTextColor);

            $this->page->addRectangle(
                $cellX,
                $rowBottomY,
                $columnWidth,
                $rowHeight,
                $this->borderWidth,
                $this->borderColor,
                $fillColor,
                $this->borderOpacity,
            );

            $this->page = $this->page->addParagraph(
                $preparedCell->text,
                $cellX + $this->padding,
                $this->cursorY - $this->padding - $this->fontSize,
                $columnWidth - (2 * $this->padding),
                $this->baseFont,
                $this->fontSize,
                null,
                $lineHeight,
                $rowBottomY + $this->padding,
                $textColor,
                $preparedCell->opacity,
                $preparedCell->align,
            );

            $cellX += $columnWidth;
        }

        $this->cursorY = $rowBottomY;

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

    private function ensureRowFitsOnCurrentPage(float $rowHeight): void
    {
        if ($this->cursorY - $rowHeight >= $this->bottomMargin) {
            return;
        }

        $topMargin = $this->page->getHeight() - $this->cursorY;
        $this->page = $this->page->getDocument()->addPage($this->page->getWidth(), $this->page->getHeight());
        $this->cursorY = $this->page->getHeight() - $topMargin;
    }

    /**
     * @param string|list<TextSegment>|TableCell $cell
     */
    private function normalizeCell(string|array|TableCell $cell, bool $header): TableCell
    {
        if ($cell instanceof TableCell) {
            return new TableCell(
                $this->normalizeText($cell->text, $header),
                $cell->align,
                $cell->fillColor,
                $cell->textColor,
                $cell->opacity,
            );
        }

        return new TableCell($this->normalizeText($cell, $header));
    }

    /**
     * @param string|list<TextSegment> $text
     * @return string|list<TextSegment>
     */
    private function normalizeText(string|array $text, bool $header): string|array
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
