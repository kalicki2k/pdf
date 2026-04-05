<?php

use Kalle\Pdf\Document\Document;
use Kalle\Pdf\Document\Geometry\Rect;
use Kalle\Pdf\Document\Text\TextSegment;
use Kalle\Pdf\Document\Text\TextBoxOptions;
use Kalle\Pdf\Graphics\Color;
use Kalle\Pdf\Layout\PageSize;
use Kalle\Pdf\Layout\Units;

require 'vendor/autoload.php';

$outputDir = __DIR__ . '/var/examples';

if (!is_dir($outputDir) && !mkdir($outputDir, 0777, true) && !is_dir($outputDir)) {
    throw new RuntimeException(sprintf('Unable to create output directory "%s".', $outputDir));
}

$startedAt = microtime(true);

$document = new Document(
    version: 1.0,
    title: 'Rechnung',
)
    ->addKeyword('Rechnung')
    ->registerFont('Helvetica');

$page = $document->addPage(PageSize::A4());

$page
    ->addTextBox(
        text: "DEIN FIRMENNAME\nStraße Hausnummer\nPLZ Ort\nDeutschland",
        box: new Rect(Units::mm(140), Units::mm(257), Units::mm(70), Units::mm(20)),
        fontName: 'Helvetica',
        size: 9,
        options: new TextBoxOptions(
            lineHeight: Units::mm(4),
        ),
    )
    ->addTextBox(
        text: "Telefon: 0123 456789\nE-Mail: info@deinefirma.de\nWeb: www.deinefirma.de",
        box: new Rect(Units::mm(140), Units::mm(240), Units::mm(70), Units::mm(15)),
        fontName: 'Helvetica',
        size: 9,
        options: new TextBoxOptions(
            lineHeight: Units::mm(4),
        ),
    )
    ->addTextBox(
        text: "Steuernummer: 12/345/67890\nUSt-IdNr.: DE123456789",
        box: new Rect(Units::mm(140), Units::mm(228), Units::mm(70), Units::mm(10)),
        fontName: 'Helvetica',
        size: 9,
        options: new TextBoxOptions(
            lineHeight: Units::mm(4),
        ),
    )
    ->addTextBox(
        text: "Telefon: 0123 456789\nE-Mail: info@deinefirma.de\nWeb: www.deinefirma.de",
        box: new Rect(Units::mm(20), Units::mm(240), Units::mm(70), Units::mm(15)),
        fontName: 'Helvetica',
        size: 9,
        options: new TextBoxOptions(
            lineHeight: Units::mm(4),
        ),
    )
    ->addTextBox(
        text: [TextSegment::colored('Rechnung', Color::rgb(220, 20, 60))],
        box: new Rect(Units::mm(20), Units::mm(210), Units::mm(170), Units::mm(10)),
        fontName: 'Helvetica',
        size: 20,
    )
    ->addTextBox(
        text: "Rechnungsnummer: 2026-0015\nRechnungsdatum: 05.04.2026\nLeistungsdatum: 31.03.2026",
        box: new Rect(Units::mm(20), Units::mm(198), Units::mm(70), Units::mm(10)),
        fontName: 'Helvetica',
        size: 9,
        options: new TextBoxOptions(
            lineHeight: Units::mm(4),
        ),
    );

$targetPath = $outputDir . '/' . 'rechnung_' . new DateTime()->format('Y-m-d-H-i-s') . '.pdf';
file_put_contents($targetPath, $document->render());

printf(
    'Erstellt in %.3f Sekunden.%s',
    microtime(true) - $startedAt,
    PHP_EOL,
);
