<?php

declare(strict_types=1);

use Kalle\Pdf\Document\Document;
use Kalle\Pdf\Layout\Geometry\Position;
use Kalle\Pdf\Layout\Geometry\Rect;
use Kalle\Pdf\Layout\Page\PageSize;
use Kalle\Pdf\Layout\Page\Units;
use Kalle\Pdf\Layout\Table\Definition\TableCell;
use Kalle\Pdf\Layout\Table\Style\CellStyle;
use Kalle\Pdf\Layout\Table\Style\HeaderStyle;
use Kalle\Pdf\Layout\Table\Style\TableBorder;
use Kalle\Pdf\Layout\Table\Style\TablePadding;
use Kalle\Pdf\Layout\Table\Style\TableStyle;
use Kalle\Pdf\Layout\Text\Input\ParagraphOptions;
use Kalle\Pdf\Layout\Text\Input\TextBoxOptions;
use Kalle\Pdf\Layout\Text\Input\TextOptions;
use Kalle\Pdf\Layout\Text\Input\TextSegment;
use Kalle\Pdf\Layout\Value\HorizontalAlign;
use Kalle\Pdf\Profile\Profile;
use Kalle\Pdf\Style\Color;

require __DIR__ . '/../vendor/autoload.php';

$outputDir = __DIR__ . '/../var/examples';

if (!is_dir($outputDir) && !mkdir($outputDir, 0777, true) && !is_dir($outputDir)) {
    throw new RuntimeException(sprintf('Unable to create output directory "%s".', $outputDir));
}

$startedAt = microtime(true);

$left = Units::mm(20);
$right = Units::mm(190);
$contentWidth = $right - $left;

$companyBox = new Rect(Units::mm(120), Units::mm(220), Units::mm(70), Units::mm(45));
$senderLineBox = new Rect($left, Units::mm(246), Units::mm(95), Units::mm(8));
$recipientBox = new Rect($left, Units::mm(224), Units::mm(75), Units::mm(22));
$titlePosition = new Position($left, Units::mm(205));
$metaBox = new Rect($left, Units::mm(188), Units::mm(80), Units::mm(14));
$introPosition = new Position($left, Units::mm(182));
$tablePosition = new Position($left, Units::mm(165));
$totalsPosition = new Position(Units::mm(120), Units::mm(78));

$document = new Document(
    profile: Profile::standard(1.4),
    title: 'Rechnung 2026-0015',
    author: 'DEIN FIRMENNAME',
    subject: 'Ausgangsrechnung',
    language: 'de-DE',
    creator: 'Rechnungsservice',
    creatorTool: 'Backoffice Export',
)
    ->addKeyword('Rechnung')
    ->addKeyword('Buchhaltung')
    ->registerFont('Helvetica')
    ->registerFont('Helvetica-Bold')
    ->registerFont('Helvetica-Oblique');

$page = $document->addPage(PageSize::A4());

$page->addTextBox(
    text: "DEIN FIRMENNAME\nStraße Hausnummer\nPLZ Ort\nDeutschland\n\nTelefon: 0123 456789\nE-Mail: info@deinefirma.de\nWeb: www.deinefirma.de\n\nSteuernummer: 12/345/67890\nUSt-IdNr.: DE123456789",
    box: $companyBox,
    fontName: 'Helvetica',
    size: 9,
    options: new TextBoxOptions(
        lineHeight: Units::mm(4),
        align: HorizontalAlign::RIGHT,
    ),
);

$page->addTextBox(
    text: 'DEIN FIRMENNAME - Straße Hausnummer - PLZ Ort - Deutschland',
    box: $senderLineBox,
    fontName: 'Helvetica',
    size: 6,
    options: new TextBoxOptions(
        lineHeight: Units::mm(4),
    ),
);

$page->addTextBox(
    text: "Kundenfirma Müller GmbH\nz. Hd. Anna Müller\nBeispielweg 8\n80331 München\nDeutschland",
    box: $recipientBox,
    fontName: 'Helvetica',
    size: 9,
    options: new TextBoxOptions(
        lineHeight: Units::mm(4),
    ),
);

$page->addText(
    'Rechnung',
    $titlePosition,
    'Helvetica-Bold',
    20,
    new TextOptions(color: Color::rgb(220, 20, 60)),
);

$page->addTextBox(
    text: [
        TextSegment::plain('Rechnungsnummer: '),
        TextSegment::bold('2026-0015'),
        TextSegment::plain("\nRechnungsdatum: 05.04.2026"),
        TextSegment::plain("\nLeistungsdatum: 31.03.2026"),
    ],
    box: $metaBox,
    fontName: 'Helvetica',
    size: 9,
    options: new TextBoxOptions(
        lineHeight: Units::mm(4),
    ),
);

$page->createTextFrame($introPosition, $contentWidth, Units::mm(24))
    ->addParagraph(
        text: "Sehr geehrte Frau Müller,\nhiermit berechne ich Ihnen folgende Leistungen:",
        fontName: 'Helvetica',
        size: 9,
        options: new ParagraphOptions(
            lineHeight: Units::mm(4.5),
            spacingAfter: Units::mm(4),
        ),
    );

$page->createTable(
    $tablePosition,
    $contentWidth,
    [
        Units::mm(15),
        Units::mm(75),
        Units::mm(20),
        Units::mm(30),
        Units::mm(30),
    ],
)
    ->font('Helvetica', 9)
    ->style(new TableStyle(
        padding: TablePadding::symmetric(Units::mm(1.5), Units::mm(1.2)),
        border: TableBorder::all(color: Color::gray(0.7)),
    ))
    ->headerStyle(new HeaderStyle(
        fillColor: Color::gray(0.94),
        textColor: Color::gray(0.15),
    ))
    ->addHeaderRow(['Pos.', 'Beschreibung', 'Menge', 'Einzelpreis netto', 'Gesamt netto'])
    ->addRow([
        new TableCell('1', style: new CellStyle(horizontalAlign: HorizontalAlign::CENTER)),
        'Erstellung einer Unternehmenswebseite',
        new TableCell('1', style: new CellStyle(horizontalAlign: HorizontalAlign::CENTER)),
        new TableCell('1.200,00 EUR', style: new CellStyle(horizontalAlign: HorizontalAlign::RIGHT)),
        new TableCell('1.200,00 EUR', style: new CellStyle(horizontalAlign: HorizontalAlign::RIGHT)),
    ])
    ->addRow([
        new TableCell('2', style: new CellStyle(horizontalAlign: HorizontalAlign::CENTER)),
        'Pflege und Aktualisierung bestehender Inhalte',
        new TableCell('5 Std.', style: new CellStyle(horizontalAlign: HorizontalAlign::CENTER)),
        new TableCell('80,00 EUR', style: new CellStyle(horizontalAlign: HorizontalAlign::RIGHT)),
        new TableCell('400,00 EUR', style: new CellStyle(horizontalAlign: HorizontalAlign::RIGHT)),
    ])
    ->addRow([
        new TableCell('3', style: new CellStyle(horizontalAlign: HorizontalAlign::CENTER)),
        'Bildbearbeitung und Optimierung für Web',
        new TableCell('2 Std.', style: new CellStyle(horizontalAlign: HorizontalAlign::CENTER)),
        new TableCell('65,00 EUR', style: new CellStyle(horizontalAlign: HorizontalAlign::RIGHT)),
        new TableCell('130,00 EUR', style: new CellStyle(horizontalAlign: HorizontalAlign::RIGHT)),
    ]);

$page->createTextFrame($totalsPosition, Units::mm(70), Units::mm(35))
    ->addParagraph(
        text: [
            TextSegment::plain("Zwischensumme: 1.730,00 EUR\n"),
            TextSegment::plain("USt. 19 %: 328,70 EUR\n"),
            TextSegment::bold('Gesamtbetrag: 2.058,70 EUR'),
        ],
        fontName: 'Helvetica',
        size: 10,
        options: new ParagraphOptions(
            lineHeight: Units::mm(5),
            spacingAfter: Units::mm(4),
        ),
    )
    ->addParagraph(
        text: "Bitte überweisen Sie den Gesamtbetrag innerhalb von 14 Tagen ohne Abzug.\nVielen Dank für Ihren Auftrag.",
        fontName: 'Helvetica',
        size: 9,
        options: new ParagraphOptions(
            lineHeight: Units::mm(4.5),
        ),
    );

//$targetPath = $outputDir . '/rechnung_' . new DateTime()->format('Y-m-d-H-i-s') . '.pdf';
$targetPath = $outputDir . '/rechnung.pdf';
$document->writeToFile($targetPath);

printf(
    "Erstellt in %.3f Sekunden.\n",
    microtime(true) - $startedAt,
);
