<?php

use Kalle\Pdf\Document\Document;
use Kalle\Pdf\Document\Position;
use Kalle\Pdf\Document\TextBoxOptions;
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

$page->addTextBox(
    text: "DEIN FIRMENNAME\nStraße Hausnummer\nPLZ Ort\nDeutschland",
    position: new Position(Units::mm(140), Units::mm(257)),
    width: Units::mm(70),
    height: Units::mm(20),
    fontName: 'Helvetica',
    size: 9,
    options: new TextBoxOptions(
        lineHeight: Units::mm(4),
    ),
);

$page->addTextBox(
    text: "Telefon: 0123 456789\nE-Mail: info@deinefirma.de\nWeb: www.deinefirma.de",
    position: new Position(Units::mm(140), Units::mm(240)),
    width: Units::mm(70),
    height: Units::mm(15),
    fontName: 'Helvetica',
    size: 9,
    options: new TextBoxOptions(
        lineHeight: Units::mm(4),
    ),
);

$page->addTextBox(
    text: "Steuernummer: 12/345/67890\nUSt-IdNr.: DE123456789",
    position: new Position(Units::mm(140), Units::mm(228)),
    width: Units::mm(70),
    height: Units::mm(10),
    fontName: 'Helvetica',
    size: 9,
    options: new TextBoxOptions(
        lineHeight: Units::mm(4),
    ),
);

$page->addText(
    text: 'Rechnung',
    position: new Position(Units::mm(20), Units::mm(200)),
    fontName: 'Helvetica',
    size: 20,
);

$page->addTextBox(
    text: "Rechnungsnummer: 2026-0015\nRechnungsdatum: 05.04.2026\nLeistungsdatum: 31.03.2026",
    position: new Position(Units::mm(20), Units::mm(188)),
    width: Units::mm(70),
    height: Units::mm(10),
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
