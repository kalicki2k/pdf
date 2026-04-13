<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Kalle\Pdf\Color\Color;
use Kalle\Pdf\Color\MaterialColor;
use Kalle\Pdf\Document\DocumentBuildException;
use Kalle\Pdf\Document\Profile;
use Kalle\Pdf\Document\Table;
use Kalle\Pdf\Document\TableCell;
use Kalle\Pdf\Document\TableColumn;
use Kalle\Pdf\Document\TablePlacement;
use Kalle\Pdf\Document\TableRow;
use Kalle\Pdf\Drawing\StrokeStyle;
use Kalle\Pdf\Drawing\Units;
use Kalle\Pdf\Font\EmbeddedFontSource;
use Kalle\Pdf\Layout\Table\Border;
use Kalle\Pdf\Layout\Table\CellPadding;
use Kalle\Pdf\Page\Margin;
use Kalle\Pdf\Page\PageSize;
use Kalle\Pdf\Pdf;
use Kalle\Pdf\Text\TextAlign;
use Kalle\Pdf\Text\TextOptions;
use Kalle\Pdf\Text\TextSegment;
use Kalle\Pdf\Text\TextSemantic;

$outputDirectory = __DIR__ . '/../var/examples';

if (!is_dir($outputDirectory) && !mkdir($outputDirectory, 0777, true) && !is_dir($outputDirectory)) {
    throw new RuntimeException('Unable to create example output directory.');
}

$startedAt = microtime(true);

$left = Units::mm(20);
$right = Units::mm(190);
$contentWidth = $right - $left;

$headlineColor = Color::material(MaterialColor::RED, 700);
$textColor = Color::material(MaterialColor::BLUE_GREY, 800);
$mutedColor = Color::material(MaterialColor::BLUE_GREY, 500);
$tableHeaderColor = Color::material(MaterialColor::BLUE_GREY, 50);
$tableBorderColor = Color::material(MaterialColor::GREY, 400);
$fontSource = EmbeddedFontSource::fromPath(__DIR__ . '/../assets/fonts/ibm-plex-sans/variable/IBMPlexSans[wdth,wght].ttf');

$table = Table::define(
    TableColumn::fixed(Units::mm(15)),
    TableColumn::fixed(Units::mm(75)),
    TableColumn::fixed(Units::mm(20)),
    TableColumn::fixed(Units::mm(30)),
    TableColumn::fixed(Units::mm(30)),
)
    ->withPlacement(TablePlacement::at($left, Units::mm(104), $contentWidth))
    ->withCellPadding(CellPadding::symmetric(Units::mm(1.2), Units::mm(1.5)))
    ->withBorder(Border::all(0.5))
    ->withTextOptions(new TextOptions(
        fontSize: 9,
        lineHeight: 12,
        embeddedFont: $fontSource,
        color: $textColor,
    ))
    ->withHeaderRows(
        TableRow::fromCells(
            TableCell::text('Pos.')
                ->withBackgroundColor($tableHeaderColor)
                ->withHorizontalAlign(TextAlign::CENTER),
            TableCell::text('Beschreibung')
                ->withBackgroundColor($tableHeaderColor),
            TableCell::text('Menge')
                ->withBackgroundColor($tableHeaderColor)
                ->withHorizontalAlign(TextAlign::CENTER),
            TableCell::text('Einzelpreis netto')
                ->withBackgroundColor($tableHeaderColor)
                ->withHorizontalAlign(TextAlign::RIGHT),
            TableCell::text('Gesamt netto')
                ->withBackgroundColor($tableHeaderColor)
                ->withHorizontalAlign(TextAlign::RIGHT),
        ),
    )
    ->withRows(
        TableRow::fromCells(
            TableCell::text('1')->withHorizontalAlign(TextAlign::CENTER),
            TableCell::text('Erstellung einer Unternehmenswebseite'),
            TableCell::text('1')->withHorizontalAlign(TextAlign::CENTER),
            TableCell::text('1.200,00 EUR')->withHorizontalAlign(TextAlign::RIGHT),
            TableCell::text('1.200,00 EUR')->withHorizontalAlign(TextAlign::RIGHT),
        ),
        TableRow::fromCells(
            TableCell::text('2')->withHorizontalAlign(TextAlign::CENTER),
            TableCell::text('Pflege und Aktualisierung bestehender Inhalte'),
            TableCell::text('5 Std.')->withHorizontalAlign(TextAlign::CENTER),
            TableCell::text('80,00 EUR')->withHorizontalAlign(TextAlign::RIGHT),
            TableCell::text('400,00 EUR')->withHorizontalAlign(TextAlign::RIGHT),
        ),
        TableRow::fromCells(
            TableCell::text('3')->withHorizontalAlign(TextAlign::CENTER),
            TableCell::text('Bildbearbeitung und Optimierung fuer Web'),
            TableCell::text('2 Std.')->withHorizontalAlign(TextAlign::CENTER),
            TableCell::text('65,00 EUR')->withHorizontalAlign(TextAlign::RIGHT),
            TableCell::text('130,00 EUR')->withHorizontalAlign(TextAlign::RIGHT),
        ),
    );

$document = Pdf::document()
    ->profile(Profile::pdfA3b())
    ->title('Rechnung 2026-0015')
    ->author('DEIN FIRMENNAME')
    ->subject('Ausgangsrechnung')
    ->keywords('Rechnung, Buchhaltung')
    ->language('de-DE')
    ->creator('examples/invoice.php')
    ->creatorTool('pdf2')
    ->pageSize(PageSize::A4())
    ->margin(Margin::all(Units::mm(20)))
    ->textLines(
        [
            'DEIN FIRMENNAME',
            'Straße Hausnummer',
            'PLZ Ort',
            'Deutschland',
            '',
            'Telefon: 0123 456789',
            'E-Mail: info@deinefirma.de',
            'Web: www.deinefirma.de',
            '',
            'Steuernummer: 12/345/67890',
            'USt-IdNr.: DE123456789',
        ],
        new TextOptions(
            x: Units::mm(120),
            y: Units::mm(255),
            width: Units::mm(70),
            fontSize: 9,
            lineHeight: 11,
            embeddedFont: $fontSource,
            color: $textColor,
            align: TextAlign::RIGHT,
            semantic: TextSemantic::ARTIFACT,
        ),
    )
    ->text(
        'DEIN FIRMENNAME - Strasse Hausnummer - PLZ Ort - Deutschland',
        new TextOptions(
            // x: $left,
            y: Units::mm(238),
            width: Units::mm(95),
            fontSize: 6,
            lineHeight: 8,
            embeddedFont: $fontSource,
            color: $mutedColor,
            semantic: TextSemantic::ARTIFACT,
        ),
    )
    ->line(
        $left,
        Units::mm(236),
        Units::mm(95),
        Units::mm(236),
        new StrokeStyle(width: 0.5, color: $tableBorderColor),
    )
    ->text(
        "Kundenfirma Mueller GmbH\nz. Hd. Anna Mueller\nBeispielweg 8\n80331 Muenchen\nDeutschland",
        new TextOptions(
            //            x: $left,
            // y: Units::mm(227),
            width: Units::mm(75),
            fontSize: 9,
            lineHeight: 12,
            embeddedFont: $fontSource,
            color: $textColor,
        ),
    )
    ->text(
        'Rechnung',
        new TextOptions(
            //            x: $left,
            y: Units::mm(188),
            fontSize: 22,
            embeddedFont: $fontSource,
            color: $headlineColor,
        ),
    )
    ->text(
        [
            TextSegment::plain('Rechnungsnummer: '),
            TextSegment::plain('2026-0015', new TextOptions(
                embeddedFont: $fontSource,
                color: $headlineColor,
            )),
            TextSegment::plain(PHP_EOL . 'Rechnungsdatum: 05.04.2026'),
            TextSegment::plain(PHP_EOL . 'Leistungsdatum: 31.03.2026'),
        ],
        new TextOptions(
            y: Units::mm(181),
            width: Units::mm(80),
            fontSize: 9,
            lineHeight: 12,
            embeddedFont: $fontSource,
            color: $textColor,
        ),
    )
    ->text(
        "Sehr geehrte Frau Mueller,\nhiermit berechne ich Ihnen folgende Leistungen:",
        new TextOptions(
            //            x: $left,
            y: Units::mm(166),
            width: $contentWidth,
            fontSize: 9,
            lineHeight: 13,
            embeddedFont: $fontSource,
            color: $textColor,
        ),
    )
    ->table($table)
    ->textLines(
        [
            TextSegment::plain('Zwischensumme: 1.730,00 EUR'),
            TextSegment::plain('USt. 19 %: 328,70 EUR'),
            TextSegment::plain('Gesamtbetrag: 2.058,70 EUR'),
        ],
        new TextOptions(
            x: Units::mm(120),
            y: Units::mm(72),
            width: Units::mm(70),
            fontSize: 10,
            lineHeight: 14,
            embeddedFont: $fontSource,
            color: $textColor,
        ),
    )
    ->text(
        "Bitte ueberweisen Sie den Gesamtbetrag innerhalb von 14 Tagen ohne Abzug.\nVielen Dank fuer Ihren Auftrag.",
        new TextOptions(
            x: Units::mm(120),
            y: Units::mm(52),
            width: Units::mm(70),
            fontSize: 9,
            lineHeight: 13,
            embeddedFont: $fontSource,
            color: $textColor,
        ),
    );

$targetPath = $outputDirectory . '/invoice.pdf';

try {
    $document->writeToFile($targetPath);
} catch (DocumentBuildException $exception) {
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);

    exit(1);
}

printf(
    "Erstellt in %.3f Sekunden: %s\n",
    microtime(true) - $startedAt,
    $targetPath,
);
