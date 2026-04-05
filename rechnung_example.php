<?php

use Kalle\Pdf\Document\Document;
use Kalle\Pdf\Document\Geometry\Rect;
use Kalle\Pdf\Document\Text\ParagraphOptions;
use Kalle\Pdf\Document\Text\TextBoxOptions;
use Kalle\Pdf\Document\Text\TextSegment;
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

$page->createTextFrame(
    Units::mm(140),
    Units::mm(257) + Units::mm(20) - 9,
    Units::mm(70),
    Units::mm(228),
)
    ->addParagraph(
        text: "DEIN FIRMENNAME\nStraße Hausnummer\nPLZ Ort\nDeutschland",
        fontName: 'Helvetica',
        size: 9,
        options: new ParagraphOptions(
            lineHeight: Units::mm(4),
            spacingAfter: Units::mm(6),
        ),
    )
    ->addParagraph(
        text: "Telefon: 0123 456789\nE-Mail: info@deinefirma.de\nWeb: www.deinefirma.de",
        fontName: 'Helvetica',
        size: 9,
        options: new ParagraphOptions(
            lineHeight: Units::mm(4),
            spacingAfter: Units::mm(5),
        ),
    )
    ->addParagraph(
        text: "Steuernummer: 12/345/67890\nUSt-IdNr.: DE123456789",
        fontName: 'Helvetica',
        size: 9,
        options: new ParagraphOptions(
            lineHeight: Units::mm(4),
        ),
    );

$page
    ->addTextBox(
        text: "DEIN FIRMENNAME - Straße Hausnummer - PLZ Ort - Deutschland",
        box: new Rect(Units::mm(20), Units::mm(239), Units::mm(100), Units::mm(20)),
        fontName: 'Helvetica',
        size: 6,
        options: new TextBoxOptions(
            lineHeight: Units::mm(4),
        ),
    )
    ->addTextBox(
        text: "Kundenfirma Müller GmbH\nz. Hd. Anna Müller\nBeispielweg 8\n80331 München\nDeutschland",
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
    )
    ->addTextBox(
        text: "Sehr geehrte Frau Müller,\nhiermit berechne ich Ihnen folgende Leistungen:",
        box: new Rect(Units::mm(20), Units::mm(180), Units::mm(170), Units::mm(15)),
        fontName: 'Helvetica',
        size: 9,
        options: new TextBoxOptions(
            lineHeight: Units::mm(4),
        ),
    )
    ->addFlowText(
        text: "Sehr geehrte Frau Müller,\nhiermit berechne ich Ihnen folgende Leistungen:",
        x: Units::mm(20),
        y: Units::mm(160),
        maxWidth: Units::mm(170),
    );

$targetPath = $outputDir . '/' . 'rechnung_' . new DateTime()->format('Y-m-d-H-i-s') . '.pdf';
file_put_contents($targetPath, $document->render());

printf(
    'Erstellt in %.3f Sekunden.%s',
    microtime(true) - $startedAt,
    PHP_EOL,
);
