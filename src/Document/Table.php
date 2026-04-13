<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use function array_fill;
use function count;
use function is_array;

use Closure;
use InvalidArgumentException;
use Kalle\Pdf\Layout\Table\Border;
use Kalle\Pdf\Layout\Table\CellPadding;
use Kalle\Pdf\Text\TextOptions;

final readonly class Table
{
    /**
     * Alias for the final footer rows so existing integrations can continue to read `footerRows`.
     *
     * @var list<TableRow>
     */
    public array $footerRows;

    public TableOptions $options;
    public ?TableCaption $caption;
    public ?TablePlacement $placement;
    public CellPadding $cellPadding;
    public Border $border;
    public TextOptions $textOptions;
    public float $spacingBefore;
    public float $spacingAfter;
    public bool $repeatHeaderOnPageBreak;
    public bool $repeatFooterOnPageBreak;
    public ?Closure $repeatedFooterRenderer;
    public ?Closure $finalFooterRenderer;

    /**
     * @param list<TableColumn> $columns
     * @param list<TableRow> $rows
     * @param list<TableRow> $headerRows
     * @param list<TableRow> $repeatedFooterRows
     * @param list<TableRow> $finalFooterRows
     */
    public function __construct(
        public array $columns,
        ?TableOptions $options = null,
        public array $rows = [],
        public array $headerRows = [],
        public array $repeatedFooterRows = [],
        public array $finalFooterRows = [],
        ?Closure $repeatedFooterRenderer = null,
        ?Closure $finalFooterRenderer = null,
    ) {
        if (count($this->columns) === 0) {
            throw new InvalidArgumentException('A table must contain at least one column.');
        }

        $this->options = $options ?? TableOptions::make();

        $this->caption = $this->options->caption;
        $this->placement = $this->options->placement;
        $this->cellPadding = $this->options->cellPadding;
        $this->border = $this->options->border;
        $this->textOptions = $this->options->textOptions;
        $this->spacingBefore = $this->options->spacingBefore;
        $this->spacingAfter = $this->options->spacingAfter;
        $this->repeatHeaderOnPageBreak = $this->options->repeatHeaderOnPageBreak;
        $this->repeatFooterOnPageBreak = $this->options->repeatFooterOnPageBreak;
        $this->footerRows = $this->finalFooterRows;
        $this->repeatedFooterRenderer = $repeatedFooterRenderer;
        $this->finalFooterRenderer = $finalFooterRenderer;

        $this->assertRowsMatchGrid($this->headerRows);
        $this->assertRowsMatchGrid($this->rows);
        $this->assertRowsMatchGrid($this->repeatedFooterRows);
        $this->assertRowsMatchGrid($this->finalFooterRows);
    }

    public static function define(TableColumn ...$columns): self
    {
        /** @var list<TableColumn> $columns */
        return new self(columns: $columns);
    }

    public function addRow(TableRow $row): self
    {
        return new self(
            columns: $this->columns,
            rows: [...$this->rows, $row],
            headerRows: $this->headerRows,
            repeatedFooterRows: $this->repeatedFooterRows,
            finalFooterRows: $this->finalFooterRows,
            repeatedFooterRenderer: $this->repeatedFooterRenderer,
            finalFooterRenderer: $this->finalFooterRenderer,
            options: $this->options,
        );
    }

    public function withRows(TableRow ...$rows): self
    {
        /** @var list<TableRow> $rows */
        return new self(
            columns: $this->columns,
            rows: $rows,
            headerRows: $this->headerRows,
            repeatedFooterRows: $this->repeatedFooterRows,
            finalFooterRows: $this->finalFooterRows,
            repeatedFooterRenderer: $this->repeatedFooterRenderer,
            finalFooterRenderer: $this->finalFooterRenderer,
            options: $this->options,
        );
    }

    public function withHeaderRows(TableRow ...$headerRows): self
    {
        /** @var list<TableRow> $headerRows */
        return new self(
            columns: $this->columns,
            rows: $this->rows,
            headerRows: $headerRows,
            repeatedFooterRows: $this->repeatedFooterRows,
            finalFooterRows: $this->finalFooterRows,
            repeatedFooterRenderer: $this->repeatedFooterRenderer,
            finalFooterRenderer: $this->finalFooterRenderer,
            options: $this->options,
        );
    }

    public function withFooterRows(TableRow ...$footerRows): self
    {
        return $this->withFinalFooterRows(...$footerRows);
    }

    public function withRepeatedFooterRows(TableRow ...$footerRows): self
    {
        /** @var list<TableRow> $footerRows */
        return new self(
            columns: $this->columns,
            rows: $this->rows,
            headerRows: $this->headerRows,
            repeatedFooterRows: $footerRows,
            finalFooterRows: $this->finalFooterRows,
            repeatedFooterRenderer: null,
            finalFooterRenderer: $this->finalFooterRenderer,
            options: $this->options,
        );
    }

    public function withFinalFooterRows(TableRow ...$footerRows): self
    {
        /** @var list<TableRow> $footerRows */
        return new self(
            columns: $this->columns,
            rows: $this->rows,
            headerRows: $this->headerRows,
            repeatedFooterRows: $this->repeatedFooterRows,
            finalFooterRows: $footerRows,
            repeatedFooterRenderer: $this->repeatedFooterRenderer,
            finalFooterRenderer: null,
            options: $this->options,
        );
    }

    public function withRepeatedFooter(callable $renderer): self
    {
        return new self(
            columns: $this->columns,
            rows: $this->rows,
            headerRows: $this->headerRows,
            repeatedFooterRows: [],
            finalFooterRows: $this->finalFooterRows,
            repeatedFooterRenderer: $renderer(...),
            finalFooterRenderer: $this->finalFooterRenderer,
            options: $this->options,
        );
    }

    public function withFinalFooter(callable $renderer): self
    {
        return new self(
            columns: $this->columns,
            rows: $this->rows,
            headerRows: $this->headerRows,
            repeatedFooterRows: $this->repeatedFooterRows,
            finalFooterRows: [],
            repeatedFooterRenderer: $this->repeatedFooterRenderer,
            finalFooterRenderer: $renderer(...),
            options: $this->options,
        );
    }

    public function withFooter(callable $renderer): self
    {
        return $this->withFinalFooter($renderer);
    }

    /**
     * @return list<TableRow>
     */
    public function resolveRepeatedFooterRows(TableFooterContext $context): array
    {
        return $this->resolveFooterRows($this->repeatedFooterRenderer, $this->repeatedFooterRows, $context);
    }

    /**
     * @return list<TableRow>
     */
    public function resolveFinalFooterRows(TableFooterContext $context): array
    {
        return $this->resolveFooterRows($this->finalFooterRenderer, $this->finalFooterRows, $context);
    }

    public function withOptions(TableOptions $options): self
    {
        return new self(
            columns: $this->columns,
            rows: $this->rows,
            headerRows: $this->headerRows,
            repeatedFooterRows: $this->repeatedFooterRows,
            finalFooterRows: $this->finalFooterRows,
            repeatedFooterRenderer: $this->repeatedFooterRenderer,
            finalFooterRenderer: $this->finalFooterRenderer,
            options: $options,
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

    /**
     * @param list<TableRow> $fallbackRows
     * @return list<TableRow>
     */
    private function resolveFooterRows(?Closure $renderer, array $fallbackRows, TableFooterContext $context): array
    {
        if ($renderer === null) {
            return $fallbackRows;
        }

        $rows = $renderer($context);

        if (!is_array($rows)) {
            throw new InvalidArgumentException('Dynamic table footer renderer must return an array of TableRow objects.');
        }

        foreach ($rows as $row) {
            if (!$row instanceof TableRow) {
                throw new InvalidArgumentException('Dynamic table footer renderer must return only TableRow objects.');
            }
        }

        /** @var list<TableRow> $rows */
        return $rows;
    }
}
