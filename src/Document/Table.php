<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use function array_fill;
use function count;

use InvalidArgumentException;

use Kalle\Pdf\Layout\Table\Border;
use Kalle\Pdf\Layout\Table\CellPadding;
use Kalle\Pdf\Text\TextOptions;

final readonly class Table
{
    /**
     * @param list<TableColumn> $columns
     * @param list<TableRow> $headerRows
     * @param list<TableRow> $rows
     */
    public function __construct(
        public array $columns,
        public array $headerRows = [],
        public array $rows = [],
        public CellPadding $cellPadding = new CellPadding(4.0, 4.0, 4.0, 4.0),
        public Border $border = new Border(0.5, 0.5, 0.5, 0.5),
        public TextOptions $textOptions = new TextOptions(fontSize: 12.0, lineHeight: 14.4),
        public bool $repeatHeaderOnPageBreak = false,
    ) {
        if (count($this->columns) === 0) {
            throw new InvalidArgumentException('A table must contain at least one column.');
        }

        $this->assertRowsMatchGrid($this->headerRows);
        $this->assertRowsMatchGrid($this->rows);
    }

    public static function define(TableColumn ...$columns): self
    {
        return new self($columns);
    }

    public function addRow(TableRow $row): self
    {
        return new self(
            columns: $this->columns,
            headerRows: $this->headerRows,
            rows: [...$this->rows, $row],
            cellPadding: $this->cellPadding,
            border: $this->border,
            textOptions: $this->textOptions,
            repeatHeaderOnPageBreak: $this->repeatHeaderOnPageBreak,
        );
    }

    public function withRows(TableRow ...$rows): self
    {
        return new self(
            columns: $this->columns,
            headerRows: $this->headerRows,
            rows: $rows,
            cellPadding: $this->cellPadding,
            border: $this->border,
            textOptions: $this->textOptions,
            repeatHeaderOnPageBreak: $this->repeatHeaderOnPageBreak,
        );
    }

    public function withHeaderRows(TableRow ...$headerRows): self
    {
        return new self(
            columns: $this->columns,
            headerRows: $headerRows,
            rows: $this->rows,
            cellPadding: $this->cellPadding,
            border: $this->border,
            textOptions: $this->textOptions,
            repeatHeaderOnPageBreak: $this->repeatHeaderOnPageBreak,
        );
    }

    public function withRepeatedHeaderOnPageBreak(bool $repeatHeaderOnPageBreak = true): self
    {
        return new self(
            columns: $this->columns,
            headerRows: $this->headerRows,
            rows: $this->rows,
            cellPadding: $this->cellPadding,
            border: $this->border,
            textOptions: $this->textOptions,
            repeatHeaderOnPageBreak: $repeatHeaderOnPageBreak,
        );
    }

    public function withCellPadding(CellPadding $cellPadding): self
    {
        return new self(
            columns: $this->columns,
            headerRows: $this->headerRows,
            rows: $this->rows,
            cellPadding: $cellPadding,
            border: $this->border,
            textOptions: $this->textOptions,
            repeatHeaderOnPageBreak: $this->repeatHeaderOnPageBreak,
        );
    }

    public function withBorder(Border $border): self
    {
        return new self(
            columns: $this->columns,
            headerRows: $this->headerRows,
            rows: $this->rows,
            cellPadding: $this->cellPadding,
            border: $border,
            textOptions: $this->textOptions,
            repeatHeaderOnPageBreak: $this->repeatHeaderOnPageBreak,
        );
    }

    public function withTextOptions(TextOptions $textOptions): self
    {
        return new self(
            columns: $this->columns,
            headerRows: $this->headerRows,
            rows: $this->rows,
            cellPadding: $this->cellPadding,
            border: $this->border,
            textOptions: $textOptions,
            repeatHeaderOnPageBreak: $this->repeatHeaderOnPageBreak,
        );
    }

    /**
     * @param list<TableRow> $rows
     */
    private function assertRowsMatchGrid(array $rows): void
    {
        $activeRowspans = array_fill(0, count($this->columns), 0);

        foreach ($rows as $row) {
            $columnIndex = 0;

            foreach ($row->cells as $cell) {
                while (($activeRowspans[$columnIndex] ?? 0) > 0) {
                    $columnIndex++;
                }

                if ($columnIndex + $cell->colspan > count($this->columns)) {
                    throw new InvalidArgumentException('Table cell spans exceed the configured column count.');
                }

                for ($offset = 0; $offset < $cell->colspan; $offset++) {
                    if (($activeRowspans[$columnIndex + $offset] ?? 0) > 0) {
                        throw new InvalidArgumentException('Table cells overlap an active rowspan.');
                    }

                    $activeRowspans[$columnIndex + $offset] = $cell->rowspan;
                }

                $columnIndex += $cell->colspan;
            }

            while (($activeRowspans[$columnIndex] ?? 0) > 0) {
                $columnIndex++;
            }

            if ($columnIndex !== count($this->columns)) {
                throw new InvalidArgumentException('Table rows must fully cover the configured column grid.');
            }

            foreach ($activeRowspans as $index => $remainingRows) {
                if ($remainingRows > 0) {
                    $activeRowspans[$index]--;
                }
            }
        }
    }
}
