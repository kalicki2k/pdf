<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Kalle\Pdf\Color\Color;
use Kalle\Pdf\Document\DefaultDocumentBuilder;
use Kalle\Pdf\Document\Table;
use Kalle\Pdf\Document\TableCell;
use Kalle\Pdf\Document\TableColumn;
use Kalle\Pdf\Document\TableHeaderScope;
use Kalle\Pdf\Document\TableRow;
use Kalle\Pdf\Drawing\Units;
use Kalle\Pdf\Layout\Table\CellPadding;
use Kalle\Pdf\Page\Margin;
use Kalle\Pdf\Page\PageSize;
use Kalle\Pdf\Text\TextOptions;

$outputDirectory = __DIR__ . '/../var/examples';

if (!is_dir($outputDirectory) && !mkdir($outputDirectory, 0777, true) && !is_dir($outputDirectory)) {
    throw new RuntimeException('Unable to create example output directory.');
}

$table = Table::define(
    TableColumn::fixed(42.0),
    TableColumn::fixed(42.0),
    TableColumn::fixed(42.0),
)
    ->withHeaderRows(TableRow::fromTexts('Region', 'Status', 'Notes'))
    ->withFooterRows(TableRow::fromTexts('Prepared by', 'Ops Team', 'Footer repeats on each continued page'))
    ->withRepeatedHeaderOnPageBreak()
    ->withRepeatedFooterOnPageBreak()
    ->withCellPadding(CellPadding::symmetric(4.0, 3.0))
    ->withTextOptions(new TextOptions(
        fontSize: 9.0,
        lineHeight: 11.5,
        color: Color::hex('#1f2937'),
    ));

for ($index = 1; $index <= 24; $index++) {
    $table = $table->addRow(TableRow::fromCells(
        TableCell::text('Region ' . $index)->withHeaderScope(TableHeaderScope::ROW),
        TableCell::text($index % 2 === 0 ? 'Stable' : 'Review'),
        TableCell::text('Synthetic row ' . $index . ' keeps the table flowing across multiple pages so the repeated footer is visible.'),
    ));
}

DefaultDocumentBuilder::make()
    ->title('Table Repeated Footer Example')
    ->author('Kalle PDF')
    ->subject('Demonstrates repeated table footers across multiple pages')
    ->language('en-US')
    ->creator('examples/table-repeated-footer.php')
    ->creatorTool('pdf2')
    ->pageSize(PageSize::A6())
    ->margin(Margin::all(Units::mm(10)))
    ->text('Repeated Table Footer', new TextOptions(
        fontSize: 16,
        lineHeight: 20,
        spacingAfter: 8,
        color: Color::hex('#0f172a'),
    ))
    ->text('This example renders a table that spans multiple pages and repeats both header and footer rows on continued pages.', new TextOptions(
        fontSize: 9,
        lineHeight: 13,
        spacingAfter: 10,
        color: Color::hex('#475569'),
    ))
    ->table($table)
    ->writeToFile($outputDirectory . '/table-repeated-footer.pdf');
