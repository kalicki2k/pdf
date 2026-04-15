#!/usr/bin/env php
<?php

declare(strict_types=1);

use Kalle\Pdf\Document\DefaultDocumentBuilder;
use Kalle\Pdf\Document\DocumentRenderer;
use Kalle\Pdf\Document\Profile;
use Kalle\Pdf\Document\Table;
use Kalle\Pdf\Document\TableCaption;
use Kalle\Pdf\Document\TableCell;
use Kalle\Pdf\Document\TableColumn;
use Kalle\Pdf\Document\TableHeaderScope;
use Kalle\Pdf\Document\TableOptions;
use Kalle\Pdf\Document\TablePlacement;
use Kalle\Pdf\Document\TableRow;
use Kalle\Pdf\Font\EmbeddedFontSource;
use Kalle\Pdf\Text\TextOptions;
use Kalle\Pdf\Writer\FileOutput;

require dirname(__DIR__) . '/vendor/autoload.php';

if ($argc !== 2) {
    fwrite(STDERR, "Usage: bin/generate-pdfa1a-table-regression.php <output-pdf>\n");
    exit(1);
}

$fontPath = dirname(__DIR__) . '/assets/fonts/noto-sans/NotoSans-Regular.ttf';

if (!is_file($fontPath)) {
    fwrite(STDERR, sprintf("Required regression font not found: %s\n", $fontPath));
    exit(1);
}

$table = Table::define(
    TableColumn::fixed(120.0),
    TableColumn::fixed(120.0),
    TableColumn::fixed(120.0),
)
    ->withOptions(
        TableOptions::make()
            ->withPlacement(TablePlacement::absolute(left: 72.0, top: 700.0, width: 360.0))
            ->withCaption(TableCaption::text('Quartalsübersicht Привет'))
            ->withTextOptions(TextOptions::make(
                fontSize: 12,
                lineHeight: 15,
                embeddedFont: EmbeddedFontSource::fromPath($fontPath),
            )),
    )
    ->withHeaderRows(
        TableRow::fromCells(
            TableCell::text('Регион', rowspan: 2)->withHeaderScope(TableHeaderScope::BOTH),
            TableCell::text('План', colspan: 2),
        ),
        TableRow::fromTexts('Q1', 'Q2 Привет'),
    )
    ->withRows(
        TableRow::fromCells(
            TableCell::text('Север')->withHeaderScope(TableHeaderScope::ROW),
            TableCell::text('12'),
            TableCell::text('14'),
        ),
        TableRow::fromTexts('Юг', '10', '11'),
    )
    ->withFooterRows(
        TableRow::fromTexts('Итого', '22', '25'),
    );

$document = DefaultDocumentBuilder::make()
    ->profile(Profile::pdfA1a())
    ->title('PDF/A-1a Table Structure Regression')
    ->author('kalle/pdf2')
    ->subject('PDF/A-1a structured table regression fixture')
    ->language('de-DE')
    ->creator('Regression Fixture')
    ->creatorTool('bin/generate-pdfa1a-table-regression.php')
    ->table($table)
    ->build();

$output = new FileOutput($argv[1]);
(new DocumentRenderer())->write($document, $output);
$output->close();
