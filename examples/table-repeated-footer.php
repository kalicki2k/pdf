<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Kalle\Pdf\Color\Color;
use Kalle\Pdf\Color\MaterialColor;
use Kalle\Pdf\Document\DefaultDocumentBuilder;
use Kalle\Pdf\Document\DocumentBuildException;
use Kalle\Pdf\Document\Table;
use Kalle\Pdf\Document\TableCaption;
use Kalle\Pdf\Document\TableCell;
use Kalle\Pdf\Document\TableColumn;
use Kalle\Pdf\Document\TableHeaderScope;
use Kalle\Pdf\Document\TableOptions;
use Kalle\Pdf\Document\TableRow;
use Kalle\Pdf\Drawing\StrokeStyle;
use Kalle\Pdf\Drawing\Units;
use Kalle\Pdf\Font\EmbeddedFontSource;
use Kalle\Pdf\Layout\Table\Border;
use Kalle\Pdf\Layout\Table\CellPadding;
use Kalle\Pdf\Page\Margin;
use Kalle\Pdf\Page\PageSize;
use Kalle\Pdf\Text\TextAlign;
use Kalle\Pdf\Text\TextOptions;
use Kalle\Pdf\Text\TextSegment;

$outputDirectory = __DIR__ . '/../var/examples';

if (!is_dir($outputDirectory) && !mkdir($outputDirectory, 0777, true) && !is_dir($outputDirectory)) {
    throw new RuntimeException('Unable to create example output directory.');
}

$fontRegular = EmbeddedFontSource::fromPath(__DIR__ . '/../assets/fonts/inter/static/Inter-Regular.ttf');
$fontBold = EmbeddedFontSource::fromPath(__DIR__ . '/../assets/fonts/inter/static/Inter-Bold.ttf');

$ink = Color::material(MaterialColor::BLUE_GREY, 900);
$body = Color::material(MaterialColor::BLUE_GREY, 800);
$muted = Color::material(MaterialColor::BLUE_GREY, 500);
$headerFill = Color::material(MaterialColor::BLUE_GREY, 800);
$footerFill = Color::material(MaterialColor::BLUE_GREY, 100);
$accent = Color::material(MaterialColor::TEAL, 700);
$grid = Color::material(MaterialColor::BLUE_GREY, 300);
$softFill = Color::material(MaterialColor::BLUE_GREY, 50);

$currency = static fn (float $value): string => number_format($value, 2, ',', '.') . ' EUR';

$lineItems = [
    ['2026-03-01', 'Bereitstellung der Monitoring-Checks fuer Produktionssysteme und Baseline-Abgleich nach Monatswechsel', 'OPS-1842', 2.50, 98.00],
    ['2026-03-02', 'Priorisierte Analyse eines Datenbank-Timeouts im Kundenportal inklusive Lastprofil und Query-Review', 'INC-4421', 3.25, 112.00],
    ['2026-03-03', 'Redaktionelle Pflege der Statusseite mit Wartungsfenster, Stakeholder-Review und Freigabe', 'COM-2190', 1.50, 92.00],
    ['2026-03-04', 'Absicherung des Mail-Relays nach SPF- und DKIM-Anpassung inklusive Testversand und Logging', 'SEC-7710', 2.75, 108.00],
    ['2026-03-05', 'Patch-Validierung fuer das Kubernetes-Cluster mit Rollout-Plan und Dokumentation der Findings', 'OPS-1848', 4.25, 112.00],
    ['2026-03-06', 'Review der Backup-Jobs fuer Finanzdaten inklusive Wiederherstellungstest und Checklisten-Update', 'OPS-1853', 2.00, 98.00],
    ['2026-03-07', 'Technisches SEO-Crawling fuer die Produktseiten mit Bildkompression und Redirect-Korrekturen', 'WEB-3181', 3.50, 96.00],
    ['2026-03-08', 'Anpassung der SSO-Konfiguration fuer neue Mitarbeitergruppe und Test der Rollenvererbung', 'IAM-9082', 2.25, 108.00],
    ['2026-03-09', 'Durchsicht der Fehlerraten im API-Gateway und Nachschärfung der Alert-Schwellen fuer Peak-Traffic', 'OPS-1860', 3.00, 112.00],
    ['2026-03-10', 'Pflege der Produktdatenfeeds fuer Marktplatzanbindungen inklusive Mapping-Validierung', 'MKT-1470', 2.75, 94.00],
    ['2026-03-11', 'Abstimmung und Umsetzung kleiner UX-Korrekturen im Kundenkonto nach Support-Rueckmeldungen', 'WEB-3194', 4.00, 96.00],
    ['2026-03-12', 'Sicherheitsreview der externen Integrationen und Rotation zweier kompromittierter API-Schluessel', 'SEC-7724', 2.50, 118.00],
    ['2026-03-13', 'Qualitaetssicherung fuer das Monatsrelease mit Smoke-Test auf Checkout, Registrierung und Self-Service', 'REL-2107', 5.25, 104.00],
    ['2026-03-14', 'Performance-Feinschliff an den Suchergebnissen inklusive Cache-Tuning und Query-Profiling', 'WEB-3202', 3.75, 102.00],
    ['2026-03-15', 'Nachbereitung des Quartalsworkshops, Pflege der Roadmap und Priorisierung offener Technikthemen', 'PM-4031', 2.00, 88.00],
    ['2026-03-16', 'Einrichtung eines getrennten Test-Mandanten fuer die Rechnungslogik mit anonymisierten Echtdaten', 'ERP-5514', 4.50, 110.00],
    ['2026-03-17', 'Anpassung der Consent-Banner-Logik fuer neue Tracking-Kategorien und Rechtsabstimmung', 'WEB-3210', 2.25, 94.00],
    ['2026-03-18', 'Fehleranalyse fuer doppelte Bestellbestaetigungen inklusive Mail-Trace und Queue-Inspektion', 'INC-4444', 3.50, 112.00],
    ['2026-03-19', 'Datenbereinigung im CRM fuer inaktive Kontakte und Vereinheitlichung der Branchenklassifikation', 'CRM-8920', 2.00, 86.00],
    ['2026-03-20', 'Weiterentwicklung des Reporting-Dashboards mit zwei neuen Kennzahlen und Rollenpruefung', 'BI-1183', 4.25, 99.00],
    ['2026-03-21', 'Analyse einer Mobilansicht im Bestellprozess mit CSS-Korrekturen und Cross-Device-Abgleich', 'WEB-3216', 2.75, 96.00],
    ['2026-03-22', 'Pruefung des Fraud-Filters fuer Lastschrift-Transaktionen inklusive Regel-Simulation', 'PAY-6008', 3.00, 108.00],
    ['2026-03-23', 'Review der Suchindex-Aktualisierung nach Content-Migration und Delta-Rebuild der betroffenen Segmente', 'OPS-1882', 3.50, 102.00],
    ['2026-03-24', 'Abstimmung mit dem Support-Team zu haeufigen Kundenanfragen und Ableitung neuer Hilfecenter-Inhalte', 'CS-3301', 1.75, 86.00],
    ['2026-03-25', 'Optimierung des Newsletter-Exports fuer Salesforce inklusive Mapping-Test und Dubletten-Pruefung', 'MKT-1481', 2.50, 94.00],
    ['2026-03-26', 'Release-Begleitung fuer das Self-Service-Portal mit Go-Live-Checkliste und Live-Monitoring', 'REL-2118', 4.00, 110.00],
    ['2026-03-27', 'Anpassung der Rollenmatrix im Adminbereich fuer externe Dienstleister mit Audit-Nachweis', 'IAM-9105', 2.25, 108.00],
    ['2026-03-28', 'Pflege des Produktkatalogs fuer das Fruehjahrs-Sortiment und Bildfreigaben durch das Marketing', 'CAT-7002', 3.25, 90.00],
    ['2026-03-29', 'Notfallprobe fuer den Ausfall des Zahlungsdienstleisters mit Kommunikationsmatrix und Runbook-Check', 'BCM-1209', 3.75, 118.00],
    ['2026-03-30', 'Monatlicher Security-Report mit Abweichungsanalyse, Audit-Trail und Management-Zusammenfassung', 'SEC-7738', 2.50, 114.00],
    ['2026-03-31', 'Abschluss der Monatsdokumentation mit Abnahme, Ablage im Kunden-Wiki und Versand des Executive Summary', 'OPS-1890', 1.50, 92.00],
];

$netTotal = 0.0;

$headerCell = static fn (string $label) => TableCell::segments(
    TextSegment::plain($label, new TextOptions(
        embeddedFont: $fontBold,
        color: Color::white(),
    )),
)->withBackgroundColor($headerFill);

$footerLabelCell = static fn (string $label, int $colspan = 1) => TableCell::segments(
    TextSegment::plain($label, new TextOptions(
        embeddedFont: $fontBold,
        color: $ink,
    )),
)->withBackgroundColor($footerFill)->withColspan($colspan);

$footerValueCell = static fn (string $value) => TableCell::segments(
    TextSegment::plain($value, new TextOptions(
        embeddedFont: $fontBold,
        color: $accent,
    )),
)->withBackgroundColor($footerFill)->withHorizontalAlign(TextAlign::RIGHT);

$withOptionalFill = static function (TableCell $cell, ?Color $fill): TableCell {
    if ($fill === null) {
        return $cell;
    }

    return $cell->withBackgroundColor($fill);
};

$table = Table::define(
    TableColumn::auto(),
    TableColumn::proportional(1.0),
    TableColumn::auto(),
    TableColumn::auto(),
    TableColumn::auto(),
    TableColumn::auto(),
)->withOptions(new TableOptions(
    border: Border::all(0.4),
    textOptions: new TextOptions(
        fontSize: 8.7,
        lineHeight: 11.6,
        embeddedFont: $fontRegular,
        color: $body,
    ),
    caption: TableCaption::text('Leistungsnachweis Maerz 2026')
        ->withTextOptions(new TextOptions(
            fontSize: 10.0,
            lineHeight: 13.0,
            embeddedFont: $fontBold,
            color: $ink,
        ))
        ->withSpacingAfter(6.0),
    cellPadding: CellPadding::symmetric(Units::mm(1.4), Units::mm(1.6)),
    repeatHeaderOnPageBreak: true,
    repeatFooterOnPageBreak: true,
))
    ->withHeaderRows(TableRow::fromCells(
        $headerCell('Datum')->withHeaderScope(TableHeaderScope::COLUMN),
        $headerCell('Leistung')->withHeaderScope(TableHeaderScope::COLUMN),
        $headerCell('Ticket')->withHeaderScope(TableHeaderScope::COLUMN),
        $headerCell('Std.')->withHeaderScope(TableHeaderScope::COLUMN)->withHorizontalAlign(TextAlign::CENTER),
        $headerCell('Satz')->withHeaderScope(TableHeaderScope::COLUMN)->withHorizontalAlign(TextAlign::RIGHT),
        $headerCell('Betrag')->withHeaderScope(TableHeaderScope::COLUMN)->withHorizontalAlign(TextAlign::RIGHT),
    ));

foreach ($lineItems as $index => [$serviceDate, $description, $ticket, $hours, $rate]) {
    $amount = $hours * $rate;
    $netTotal += $amount;
    $rowFill = $index % 2 === 0 ? null : $softFill;

    $table = $table->addRow(TableRow::fromCells(
        $withOptionalFill(TableCell::text($serviceDate), $rowFill),
        $withOptionalFill(TableCell::text($description), $rowFill),
        $withOptionalFill(TableCell::text($ticket), $rowFill)
            ->withHorizontalAlign(TextAlign::CENTER),
        $withOptionalFill(TableCell::text(number_format($hours, 2, ',', '.')), $rowFill)
            ->withHorizontalAlign(TextAlign::CENTER),
        $withOptionalFill(TableCell::text($currency($rate)), $rowFill)
            ->withHorizontalAlign(TextAlign::RIGHT),
        $withOptionalFill(TableCell::text($currency($amount)), $rowFill)
            ->withHorizontalAlign(TextAlign::RIGHT),
    ));
}

$vatAmount = $netTotal * 0.19;
$grossTotal = $netTotal + $vatAmount;

$table = $table->withFooterRows(
    TableRow::fromCells(
        $footerLabelCell('Monatssumme netto', 5),
        $footerValueCell($currency($netTotal)),
    ),
    TableRow::fromCells(
        $footerLabelCell('zzgl. USt. 19 %', 5),
        $footerValueCell($currency($vatAmount)),
    ),
    TableRow::fromCells(
        $footerLabelCell('Gesamtbetrag', 5),
        $footerValueCell($currency($grossTotal)),
    ),
);

$document = DefaultDocumentBuilder::make()
    ->title('Managed Services Statement - March 2026')
    ->author('Kalle PDF')
    ->subject('Professional multi-page table with repeated footer rows')
    ->language('de-DE')
    ->creator('examples/table-repeated-footer.php')
    ->creatorTool('pdf2')
    ->pageSize(PageSize::A4())
    ->margin(Margin::all(Units::mm(18)))
    ->text('Managed Services Statement', new TextOptions(
        embeddedFont: $fontBold,
        fontSize: 20,
        lineHeight: 24,
        color: $ink,
        spacingAfter: 4,
    ))
    ->text('Brightlane Commerce GmbH · Monatliche Leistungsabrechnung fuer Plattformbetrieb, Sicherheit und Produktpflege', new TextOptions(
        embeddedFont: $fontRegular,
        fontSize: 9.5,
        lineHeight: 13,
        color: $muted,
        spacingAfter: 10,
    ))
    ->line(
        Units::mm(18),
        Units::mm(263),
        Units::mm(192),
        Units::mm(263),
        new StrokeStyle(width: 0.8, color: $grid),
    )
    ->textLines([
        'Kunde: Brightlane Commerce GmbH',
        'Leistungszeitraum: 01.03.2026 bis 31.03.2026',
        'Service-Level: Managed Operations & Continuous Delivery',
    ], new TextOptions(
        y: Units::mm(252),
        width: Units::mm(88),
        embeddedFont: $fontRegular,
        fontSize: 9,
        lineHeight: 12,
        color: $body,
    ))
    ->textLines([
        'Account Lead: Anna Mueller',
        'Reporting Cycle: monatlich',
        'Abrechnungsbasis: Time & Material',
    ], new TextOptions(
        x: Units::mm(122),
        y: Units::mm(252),
        width: Units::mm(70),
        embeddedFont: $fontRegular,
        fontSize: 9,
        lineHeight: 12,
        color: $body,
        align: TextAlign::RIGHT,
    ))
    ->text('Der Leistungsnachweis unten ist bewusst ueber mehrere Seiten aufgebaut, damit wiederholte Tabellenkoepfe und wiederholte Footer-Zeilen in einem realistischen Abrechnungsdokument sichtbar werden.', new TextOptions(
        y: Units::mm(228),
        width: Units::mm(174),
        embeddedFont: $fontRegular,
        fontSize: 9,
        lineHeight: 13,
        color: $body,
        spacingAfter: 8,
    ))
    ->table($table)
    ->text('Hinweis: Die Footer-Zeilen mit Netto-, Steuer- und Gesamtbetrag werden auf jeder Folgeseite erneut ausgegeben. Das eignet sich fuer Berichte, Leistungsnachweise und Abrechnungsanlagen, bei denen Summen auf jeder Seite sichtbar bleiben sollen.', new TextOptions(
        embeddedFont: $fontRegular,
        fontSize: 8.5,
        lineHeight: 12,
        color: $muted,
        spacingBefore: 10,
    ));

$targetPath = $outputDirectory . '/table-repeated-footer.pdf';

try {
    $document->writeToFile($targetPath);
} catch (DocumentBuildException $exception) {
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);

    exit(1);
}

printf("Created: %s\n", $targetPath);
