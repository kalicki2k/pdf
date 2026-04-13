<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Kalle\Pdf\Color\Color;
use Kalle\Pdf\Document\DefaultDocumentBuilder;
use Kalle\Pdf\Document\Table;
use Kalle\Pdf\Document\TableCaption;
use Kalle\Pdf\Document\TableCell;
use Kalle\Pdf\Document\TableColumn;
use Kalle\Pdf\Document\TableHeaderScope;
use Kalle\Pdf\Document\TableOptions;
use Kalle\Pdf\Document\TablePlacement;
use Kalle\Pdf\Document\TableRow;
use Kalle\Pdf\Drawing\Units;
use Kalle\Pdf\Layout\Table\Border;
use Kalle\Pdf\Layout\Table\CellPadding;
use Kalle\Pdf\Layout\Table\VerticalAlign;
use Kalle\Pdf\Page\Margin;
use Kalle\Pdf\Page\PageSize;
use Kalle\Pdf\Text\TextAlign;
use Kalle\Pdf\Text\TextLink;
use Kalle\Pdf\Text\TextOptions;
use Kalle\Pdf\Text\TextSegment;

$outputDirectory = __DIR__ . '/../var/examples';

if (!is_dir($outputDirectory) && !mkdir($outputDirectory, 0777, true) && !is_dir($outputDirectory)) {
    throw new RuntimeException('Unable to create example output directory.');
}

$margin = Margin::all(Units::mm(16));
$tableText = TextOptions::make(
    fontSize: 9.5,
    lineHeight: 12.5,
    color: Color::hex('#1f2937'),
);
$captionText = TextOptions::make(
    fontSize: 12,
    lineHeight: 15,
    color: Color::hex('#0f172a'),
);
$table = Table::define(
    TableColumn::fixed(70.0),
    TableColumn::fixed(58.0),
    TableColumn::proportional(1.0),
)
    ->withOptions(
        (TableOptions::make())
            ->withPlacement(TablePlacement::at(50.0, 430.0, 320.0))
            ->withCaption(
                TableCaption::text('Tables in pdf2: caption, repeated headers, row headers, spans, backgrounds, vertical alignment, footer and tagged PDF scope')
                    ->withTextOptions($captionText)
                    ->withSpacingAfter(10.0),
            )
            ->withCellPadding(CellPadding::symmetric(6.0, 7.0))
            ->withBorder(Border::all(0.5))
            ->withTextOptions($tableText)
            ->withRepeatedHeaderOnPageBreak(),
    )
    ->withHeaderRows(
        TableRow::fromCells(
            TableCell::text('Region / Item')
                ->withHeaderScope(TableHeaderScope::BOTH)
                ->withBackgroundColor(Color::hex('#dbeafe'))
                ->withHorizontalAlign(TextAlign::CENTER),
            TableCell::text('Metric')
                ->withBackgroundColor(Color::hex('#dbeafe'))
                ->withHorizontalAlign(TextAlign::CENTER),
            TableCell::text('Notes')
                ->withBackgroundColor(Color::hex('#dbeafe'))
                ->withHorizontalAlign(TextAlign::CENTER),
        ),
    )
    ->withRows(
        TableRow::fromCells(
            TableCell::text('North', rowspan: 2)
                ->withHeaderScope(TableHeaderScope::ROW)
                ->withBackgroundColor(Color::hex('#f8fafc'))
                ->withPadding(CellPadding::symmetric(10.0, 8.0)),
            TableCell::text('Availability'),
            TableCell::segments(
                TextSegment::plain('Stable overall. Read the '),
                TextSegment::link(
                    'playbook',
                    TextLink::externalUrl(
                        'https://example.com/playbook',
                        contents: 'Open mitigation playbook',
                        accessibleLabel: 'Open the mitigation playbook',
                        groupKey: 'north-playbook',
                    ),
                ),
                TextSegment::plain(" before the next review.\nMinor fluctuations remained below threshold."),
            )->withBorder(new Border(1.0, 1.0, 1.0, 1.0)),
        ),
        TableRow::fromCells(
            TableCell::text('Response time'),
            TableCell::text('Within SLA in all weekly samples.')->withHorizontalAlign(TextAlign::RIGHT),
        ),
        TableRow::fromCells(
            TableCell::text('North summary')->withHeaderScope(TableHeaderScope::ROW)->withBackgroundColor(Color::hex('#fef3c7')),
            TableCell::text('Open follow-up items remain small and local.', colspan: 2)->withVerticalAlign(VerticalAlign::MIDDLE),
        ),
        TableRow::fromCells(
            TableCell::text('South', rowspan: 2)->withHeaderScope(TableHeaderScope::ROW)->withBackgroundColor(Color::hex('#f8fafc')),
            TableCell::text('Availability'),
            TableCell::text("Longer note block to demonstrate wrapping inside a rowspan group.\nThe left cell spans both rows while the note flow continues deterministically."),
        ),
        TableRow::fromCells(
            TableCell::text('Backlog'),
            TableCell::text("Two escalations were closed.\nOne handover note stays open for next month."),
        ),
        TableRow::fromCells(
            TableCell::text('South summary')->withHeaderScope(TableHeaderScope::ROW)->withBackgroundColor(Color::hex('#fef3c7')),
            TableCell::text('Summary row uses colspan and a highlighted background.', colspan: 2),
        ),
    )
    ->withFinalFooterRows(
        TableRow::fromCells(
            TableCell::text('Totals')->withHeaderScope(TableHeaderScope::ROW)->withBackgroundColor(Color::hex('#e2e8f0')),
            TableCell::text('2 regions')->withBackgroundColor(Color::hex('#e2e8f0')),
            TableCell::text('Footer rows are optional and move to the next page if needed.')->withBackgroundColor(Color::hex('#e2e8f0')),
        ),
    );

for ($index = 1; $index <= 10; $index++) {
    $table = $table->addRow(TableRow::fromCells(
        TableCell::text('Detail ' . $index)->withHeaderScope(TableHeaderScope::ROW),
        TableCell::text('Audit'),
        TableCell::text(
            'Synthetic follow-up row ' . $index . ' keeps the example flowing over multiple pages so repeated headers and deterministic page breaks are visible.',
        ),
    ));
}

DefaultDocumentBuilder::make()
    ->title('Table Example')
    ->author('Kalle PDF')
    ->subject('Demonstrates the current table feature set in pdf2')
    ->language('en-US')
    ->creator('examples/table.php')
    ->creatorTool('pdf2')
    ->pageSize(PageSize::A5())
    ->margin($margin)
    ->text('Table Example', TextOptions::make(
        fontSize: 18,
        lineHeight: 22,
        color: Color::hex('#0f172a'),
        spacingAfter: 8,
    ))
    ->text('This example deliberately exercises the current table foundation, including caption, repeated headers, row headers with explicit scope, colspan, rowspan, cell backgrounds, vertical alignment, footer rows and page breaks.', TextOptions::make(
        fontSize: 10,
        lineHeight: 14,
        color: Color::hex('#475569'),
        spacingAfter: 12,
    ))
    ->table($table)
    ->writeToFile($outputDirectory . '/table.pdf');
