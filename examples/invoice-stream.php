<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Kalle\Pdf\Color\Color;
use Kalle\Pdf\Color\MaterialColor;
use Kalle\Pdf\Document\Attachment\MimeType;
use Kalle\Pdf\Document\DocumentBuildException;
use Kalle\Pdf\Document\Profile;
use Kalle\Pdf\Document\Table;
use Kalle\Pdf\Document\TableCell;
use Kalle\Pdf\Document\TableColumn;
use Kalle\Pdf\Document\TableFooterContext;
use Kalle\Pdf\Document\TableOptions;
use Kalle\Pdf\Document\TableRow;
use Kalle\Pdf\Drawing\StrokeStyle;
use Kalle\Pdf\Drawing\Units;
use Kalle\Pdf\Font\EmbeddedFontSource;
use Kalle\Pdf\Image\ImagePlacement;
use Kalle\Pdf\Layout\Table\Border;
use Kalle\Pdf\Layout\Table\CellPadding;
use Kalle\Pdf\Page\Margin;
use Kalle\Pdf\Page\PageSize;
use Kalle\Pdf\Pdf;
use Kalle\Pdf\Text\TextAlign;
use Kalle\Pdf\Text\TextOptions;
use Kalle\Pdf\Text\TextSegment;
use Kalle\Pdf\Text\TextSemantic;
use Kalle\Pdf\Xml;

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
$tableFooterColor = Color::material(MaterialColor::BLUE_GREY, 100);
$fontRegular = EmbeddedFontSource::fromPath(__DIR__ . '/../assets/fonts/inter/static/Inter-Regular.ttf');
$fontBold = EmbeddedFontSource::fromPath(__DIR__ . '/../assets/fonts/inter/static/Inter-Bold.ttf');
$logoPath = __DIR__ . '/../assets/images/MusterfirmaGmbHLogoDesign.png';

$invoiceNumber = 'RE-2026-0415';
$invoiceDate = '13.04.2026';
$servicePeriod = '01.03.2026 - 31.03.2026';
$customerNumber = 'KD-10482';
$projectCode = 'PLT-OPS-2026-Q2';
$purchaseOrder = 'PO-7781-BCG';
$paymentTerms = 'zahlbar innerhalb von 14 Tagen ohne Abzug';

$lineItems = [
    ['period' => '01.03.-07.03.', 'description' => 'Betrieb und Monitoring der Produktionsplattform inkl. Incident Review und Bereitschaftsauswertung', 'quantity' => 1.0, 'unit' => 'Pauschale', 'unitPrice' => 1850.00],
    ['period' => '03.03.2026', 'description' => 'Sicherheitsupdate des Shop-Frameworks inkl. Staging-Test und Deploy-Freigabe', 'quantity' => 3.5, 'unit' => 'Std.', 'unitPrice' => 128.00],
    ['period' => '05.03.2026', 'description' => 'Optimierung der Checkout-Validierung zur Reduktion von Zahlungsabbruechen', 'quantity' => 5.0, 'unit' => 'Std.', 'unitPrice' => 122.00],
    ['period' => '08.03.2026', 'description' => 'Einrichtung eines Performance-Dashboards fuer Conversion, Warenkorb und API-Latenzen', 'quantity' => 4.0, 'unit' => 'Std.', 'unitPrice' => 118.00],
    ['period' => '10.03.2026', 'description' => 'Content-Pflege Fruehjahrskampagne inkl. Landingpage, Hero-Banner und Produktteaser', 'quantity' => 6.0, 'unit' => 'Std.', 'unitPrice' => 95.00],
    ['period' => '12.03.2026', 'description' => 'UX-Review des Kundenkontos mit priorisierter Massnahmenliste fuer das interne Produktteam', 'quantity' => 2.5, 'unit' => 'Std.', 'unitPrice' => 132.00],
    ['period' => '17.03.2026', 'description' => 'Schnittstellenanalyse ERP zu Shop inkl. Fehlerprotokoll fuer den Exportprozess', 'quantity' => 4.5, 'unit' => 'Std.', 'unitPrice' => 128.00],
    ['period' => '21.03.2026', 'description' => 'Implementierung einer Staffelpreislogik fuer B2B-Kunden im Produktdetail', 'quantity' => 7.0, 'unit' => 'Std.', 'unitPrice' => 124.00],
    ['period' => '24.03.2026', 'description' => 'QA und Abnahmebegleitung fuer das Release 2026.03 inkl. Smoke Tests', 'quantity' => 3.0, 'unit' => 'Std.', 'unitPrice' => 119.00],
    ['period' => '27.03.2026', 'description' => 'Datenbereinigung und Redirect-Plan fuer auslaufende Sortimente', 'quantity' => 2.0, 'unit' => 'Std.', 'unitPrice' => 98.00],
    ['period' => '31.03.2026', 'description' => 'Monatliches Reporting, Handlungsempfehlungen und Abstimmung mit Vertrieb und E-Commerce-Leitung', 'quantity' => 2.0, 'unit' => 'Std.', 'unitPrice' => 135.00],
    ['period' => '01.04.2026', 'description' => 'Review der Warenkorb-Events fuer das BI-Team inkl. Mapping-Korrekturen im Tracking-Konzept', 'quantity' => 3.5, 'unit' => 'Std.', 'unitPrice' => 116.00],
    ['period' => '02.04.2026', 'description' => 'Beseitigung eines Preisrundungsfehlers im B2B-Checkout inklusive Regressionstest', 'quantity' => 4.0, 'unit' => 'Std.', 'unitPrice' => 128.00],
    ['period' => '04.04.2026', 'description' => 'Optimierung der Bildauslieferung fuer Kategorieseiten mit WebP-Fallback und Cache-Headern', 'quantity' => 2.5, 'unit' => 'Std.', 'unitPrice' => 102.00],
    ['period' => '06.04.2026', 'description' => 'Abgleich der ERP-Fehlerqueues nach Monatsabschluss und Dokumentation offener Importabweichungen', 'quantity' => 3.0, 'unit' => 'Std.', 'unitPrice' => 124.00],
    ['period' => '07.04.2026', 'description' => 'Einrichtung eines gesonderten Monitoring-Alerts fuer Ruecklaeufer im Zahlungsprozess', 'quantity' => 2.5, 'unit' => 'Std.', 'unitPrice' => 118.00],
    ['period' => '08.04.2026', 'description' => 'Unterstuetzung beim Fachbereichstest fuer neue Produktbundles inkl. Preis- und Bestandspruefung', 'quantity' => 4.5, 'unit' => 'Std.', 'unitPrice' => 112.00],
    ['period' => '10.04.2026', 'description' => 'Erweiterung des Status-Reports fuer die Geschaeftsfuehrung um KPI-Kommentare und Risikoampel', 'quantity' => 2.0, 'unit' => 'Std.', 'unitPrice' => 135.00],
    ['period' => '11.04.2026', 'description' => 'Vorbereitung des Sprint-Reviews mit Demo-Daten, Freigabecheck und technischer Risikoabschaetzung', 'quantity' => 3.0, 'unit' => 'Std.', 'unitPrice' => 110.00],
];

$formatAmount = static fn (float $amount): string => number_format($amount, 2, ',', '.') . ' €';
$formatQuantity = static fn (float $quantity): string => rtrim(rtrim(number_format($quantity, 2, ',', '.'), '0'), ',');
$formatDecimal = static fn (float $value): string => number_format($value, 2, '.', '');

$subtotal = 0.0;
$runningNetTotals = [0.0];
$tableRows = [];
$invoiceXmlLines = [];

foreach ($lineItems as $index => $item) {
    $lineTotal = $item['quantity'] * $item['unitPrice'];
    $subtotal += $lineTotal;
    $runningNetTotals[] = $subtotal;

    $tableRows[] = TableRow::fromCells(
        TableCell::text((string) ($index + 1))->withHorizontalAlign(TextAlign::CENTER),
        TableCell::text($item['period']),
        TableCell::text($item['description']),
        TableCell::text($formatQuantity($item['quantity']) . ' ' . $item['unit'])->withHorizontalAlign(TextAlign::CENTER)->withNoWrap(),
        TableCell::text($formatAmount($item['unitPrice']))->withHorizontalAlign(TextAlign::RIGHT)->withNoWrap(),
        TableCell::text($formatAmount($lineTotal))->withHorizontalAlign(TextAlign::RIGHT)->withNoWrap(),
    );

    $invoiceXmlLines[] = Xml::element('line')->withChildren([
        Xml::element('position')->withText((string) ($index + 1)),
        Xml::element('servicePeriod')->withText($item['period']),
        Xml::element('description')->withText($item['description']),
        Xml::element('quantity')->withText($formatDecimal($item['quantity'])),
        Xml::element('unit')->withText($item['unit']),
        Xml::element('unitPriceNet')->withText($formatDecimal($item['unitPrice'])),
        Xml::element('lineTotalNet')->withText($formatDecimal($lineTotal)),
    ]);
}

$taxAmount = round($subtotal * 0.19, 2);
$totalAmount = $subtotal + $taxAmount;

$table = Table::define(
    TableColumn::auto(),
    TableColumn::auto(),
    TableColumn::proportional(1),
    TableColumn::auto(),
    TableColumn::auto(),
    TableColumn::auto(),
)
    ->withOptions(TableOptions::make(
        border: Border::all(0.5),
        textOptions: TextOptions::make(
            fontSize: 8.5,
            lineHeight: 11.5,
            embeddedFont: $fontRegular,
            color: $textColor,
        ),
        cellPadding: CellPadding::symmetric(Units::mm(1.2), Units::mm(1.5)),
        spacingAfter: Units::mm(12),
        repeatFooterOnPageBreak: true,
    ))
    ->withHeaderRows(
        TableRow::fromCells(
            TableCell::text('Pos.')
                ->withBackgroundColor($tableHeaderColor)
                ->withHorizontalAlign(TextAlign::CENTER),
            TableCell::text('Zeitraum')
                ->withBackgroundColor($tableHeaderColor),
            TableCell::text('Beschreibung')
                ->withBackgroundColor($tableHeaderColor),
            TableCell::text('Menge')
                ->withBackgroundColor($tableHeaderColor)
                ->withHorizontalAlign(TextAlign::CENTER)
                ->withNoWrap(),
            TableCell::text('Satz netto')
                ->withBackgroundColor($tableHeaderColor)
                ->withHorizontalAlign(TextAlign::RIGHT)
                ->withNoWrap(),
            TableCell::text('Betrag netto')
                ->withBackgroundColor($tableHeaderColor)
                ->withHorizontalAlign(TextAlign::RIGHT)
                ->withNoWrap(),
        ),
    )
    ->withRows(...$tableRows)
    ->withRepeatedFooterRows(static function (TableFooterContext $context) use ($runningNetTotals, $fontBold, $textColor, $tableFooterColor, $formatAmount): array {
        $runningTotal = $runningNetTotals[$context->completedBodyRowCount] ?? 0.0;

        return [
            TableRow::fromCells(
                TableCell::segments(
                    TextSegment::plain('Zwischensumme netto:', TextOptions::make(
                        embeddedFont: $fontBold,
                        color: $textColor,
                    )),
                )->withColspan(5)->withBackgroundColor($tableFooterColor)->withHorizontalAlign(TextAlign::RIGHT),
                TableCell::segments(
                    TextSegment::plain($formatAmount($runningTotal), TextOptions::make(
                        embeddedFont: $fontBold,
                        color: $textColor,
                    )),
                )->withBackgroundColor($tableFooterColor)->withHorizontalAlign(TextAlign::RIGHT)->withNoWrap(),
            ),
        ];
    })
    ->withFinalFooterRows(
        TableRow::fromCells(
            TableCell::segments(
                TextSegment::plain('Zwischensumme netto:', TextOptions::make(
                    embeddedFont: $fontBold,
                    color: $textColor,
                )),
            )->withColspan(5)->withBackgroundColor($tableFooterColor)->withHorizontalAlign(TextAlign::RIGHT),
            TableCell::segments(
                TextSegment::plain($formatAmount($subtotal), TextOptions::make(
                    embeddedFont: $fontBold,
                    color: $textColor,
                )),
            )->withBackgroundColor($tableFooterColor)->withHorizontalAlign(TextAlign::RIGHT)->withNoWrap(),
        ),
        TableRow::fromCells(
            TableCell::segments(
                TextSegment::plain('Umsatzsteuer 19 %:', TextOptions::make(
                    embeddedFont: $fontBold,
                    color: $textColor,
                )),
            )->withColspan(5)->withBackgroundColor($tableFooterColor)->withHorizontalAlign(TextAlign::RIGHT),
            TableCell::segments(
                TextSegment::plain($formatAmount($taxAmount), TextOptions::make(
                    embeddedFont: $fontBold,
                    color: $textColor,
                )),
            )->withBackgroundColor($tableFooterColor)->withHorizontalAlign(TextAlign::RIGHT)->withNoWrap(),
        ),
        TableRow::fromCells(
            TableCell::segments(
                TextSegment::plain('Gesamtbetrag brutto:', TextOptions::make(
                    embeddedFont: $fontBold,
                    color: $headlineColor,
                )),
            )->withColspan(5)->withBackgroundColor($tableFooterColor)->withHorizontalAlign(TextAlign::RIGHT),
            TableCell::segments(
                TextSegment::plain($formatAmount($totalAmount), TextOptions::make(
                    embeddedFont: $fontBold,
                    color: $headlineColor,
                )),
            )->withBackgroundColor($tableFooterColor)->withHorizontalAlign(TextAlign::RIGHT)->withNoWrap(),
        ),
    );

$xmlDocument = Xml::document(
    root: Xml::element('invoice', [
        'xmlns' => 'urn:kalle:invoice:1',
        'profile' => 'generic-invoice-example',
        'language' => 'de-DE',
    ])->withChildren([
        Xml::element('document')->withChildren([
            Xml::element('number')->withText($invoiceNumber),
            Xml::element('type')->withText('380'),
            Xml::element('issueDate')->withText('2026-04-13'),
            Xml::element('servicePeriod')->withText($servicePeriod),
            Xml::element('customerNumber')->withText($customerNumber),
            Xml::element('projectCode')->withText($projectCode),
            Xml::element('purchaseOrder')->withText($purchaseOrder),
            Xml::element('paymentTerms')->withText($paymentTerms),
            Xml::element('currency')->withText('EUR'),
        ]),
        Xml::element('seller')->withChildren([
            Xml::element('name')->withText('DEIN FIRMENNAME'),
            Xml::element('address')->withChildren([
                Xml::element('street')->withText('Strasse Hausnummer'),
                Xml::element('postalCode')->withText('00000'),
                Xml::element('city')->withText('Ort'),
                Xml::element('country')->withText('DE'),
            ]),
            Xml::element('taxRegistration')->withChildren([
                Xml::element('taxNumber')->withText('12/345/67890'),
                Xml::element('vatId')->withText('DE123456789'),
            ]),
            Xml::element('contact')->withChildren([
                Xml::element('phone')->withText('0123 456789'),
                Xml::element('email')->withText('info@deinefirma.de'),
                Xml::element('website')->withText('www.deinefirma.de'),
            ]),
        ]),
        Xml::element('buyer')->withChildren([
            Xml::element('name')->withText('Kundenfirma Mueller GmbH'),
            Xml::element('contactPerson')->withText('Anna Mueller'),
            Xml::element('address')->withChildren([
                Xml::element('street')->withText('Beispielweg 8'),
                Xml::element('postalCode')->withText('80331'),
                Xml::element('city')->withText('Muenchen'),
                Xml::element('country')->withText('DE'),
            ]),
        ]),
        Xml::element('lines')->withChildren($invoiceXmlLines),
        Xml::element('totals')->withChildren([
            Xml::element('taxBasisTotal')->withText($formatDecimal($subtotal)),
            Xml::element('taxTotal')->withText($formatDecimal($taxAmount)),
            Xml::element('grandTotal')->withText($formatDecimal($totalAmount)),
            Xml::element('tax')->withChildren([
                Xml::element('category')->withText('S'),
                Xml::element('rate')->withText('19.00'),
                Xml::element('taxableAmount')->withText($formatDecimal($subtotal)),
                Xml::element('amount')->withText($formatDecimal($taxAmount)),
            ]),
        ]),
    ]),
    standalone: true,
);

$xmlAttachmentPath = $outputDirectory . '/invoice-stream.xml';
$pdfPath = $outputDirectory . '/invoice-stream.pdf';

Xml::writeToFile($xmlDocument, $xmlAttachmentPath);

$document = Pdf::document()
    ->profile(Profile::pdfA3b())
    ->title('Rechnung ' . $invoiceNumber)
    ->author('DEIN FIRMENNAME')
    ->subject('Ausgangsrechnung')
    ->keywords('Rechnung, Buchhaltung, Leistungsabrechnung')
    ->language('de-DE')
    ->creator('kalle/pdf')
    ->creatorTool('kalle/pdf')
    ->pageSize(PageSize::A4())
    ->margin(Margin::all(Units::mm(20)))
    ->attachmentFromFile(
        $xmlAttachmentPath,
        filename: 'invoice.xml',
        description: 'Maschinenlesbare Rechnungsdaten',
        mimeType: MimeType::XML,
    )
    ->imageFile(
        $logoPath,
        ImagePlacement::at(
            Units::mm(136),
            Units::mm(262),
            Units::mm(48),
        ),
    )
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
        TextOptions::make(
            x: Units::mm(120),
            y: Units::mm(242),
            width: Units::mm(70),
            fontSize: 9,
            lineHeight: 11,
            embeddedFont: $fontRegular,
            color: $textColor,
            align: TextAlign::RIGHT,
            semantic: TextSemantic::ARTIFACT,
        ),
    )
    ->text(
        'DEIN FIRMENNAME - Strasse Hausnummer - PLZ Ort - Deutschland',
        TextOptions::make(
            y: Units::mm(252),
            width: Units::mm(95),
            fontSize: 6,
            lineHeight: 8,
            embeddedFont: $fontRegular,
            color: $mutedColor,
            semantic: TextSemantic::ARTIFACT,
        ),
    )
    ->line(
        $left,
        Units::mm(250),
        Units::mm(95),
        Units::mm(250),
        new StrokeStyle(width: 0.5, color: $tableBorderColor),
    )
    ->text(
        "Kundenfirma Mueller GmbH\nz. Hd. Anna Mueller\nBeispielweg 8\n80331 Muenchen\nDeutschland",
        TextOptions::make(
            y: Units::mm(246.2),
            width: Units::mm(85),
            fontSize: 9,
            lineHeight: 12,
            embeddedFont: $fontRegular,
            color: $textColor,
        ),
    )
    ->text(
        'Rechnung',
        TextOptions::make(
            y: Units::mm(188),
            fontSize: 22,
            embeddedFont: $fontBold,
            color: $headlineColor,
        ),
    )
    ->text(
        [
            TextSegment::plain('Rechnungsnummer: '),
            TextSegment::plain($invoiceNumber, TextOptions::make(
                embeddedFont: $fontBold,
                color: $headlineColor,
            )),
            TextSegment::plain(PHP_EOL . 'Rechnungsdatum: ' . $invoiceDate),
            TextSegment::plain(PHP_EOL . 'Leistungszeitraum: ' . $servicePeriod),
            TextSegment::plain(PHP_EOL . 'Kundennummer: ' . $customerNumber),
            TextSegment::plain(PHP_EOL . 'Projektcode: ' . $projectCode),
            TextSegment::plain(PHP_EOL . 'Bestellreferenz: ' . $purchaseOrder),
        ],
        TextOptions::make(
            fontSize: 9,
            lineHeight: 12,
            spacingAfter: Units::mm(10),
            embeddedFont: $fontRegular,
            color: $textColor,
        ),
    )
    ->text(
        "Sehr geehrte Frau Mueller,\n\nvielen Dank fuer die weitere Zusammenarbeit im Maerz 2026. Nachfolgend berechne ich die im Leistungsmonat erbrachten Betriebs-, Optimierungs- und Projektleistungen fuer Ihre E-Commerce-Plattform.",
        TextOptions::make(
            width: $contentWidth,
            fontSize: 9,
            lineHeight: 13,
            embeddedFont: $fontRegular,
            color: $textColor,
        ),
    )
    ->table($table)
    ->text(
        "Bitte ueberweisen Sie den Gesamtbetrag {$paymentTerms} auf das unten genannte Geschaeftskonto.\n\nBank: Musterbank AG\nIBAN: DE12 3456 7890 1234 5678 90\nBIC: MUSTDEFFXXX\n\nBei Rueckfragen antworte ich gerne unter projekte@deinefirma.de.",
        TextOptions::make(
            x: Units::mm(120),
            width: Units::mm(70),
            fontSize: 9,
            lineHeight: 13,
            embeddedFont: $fontRegular,
            color: $textColor,
        ),
    );

try {
    $document->writeToFile($pdfPath);
} catch (DocumentBuildException $exception) {
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);

    exit(1);
}

printf(
    "Erstellt in %.3f Sekunden: %s und %s\n",
    microtime(true) - $startedAt,
    $pdfPath,
    $xmlAttachmentPath,
);
