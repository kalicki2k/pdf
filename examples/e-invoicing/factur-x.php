<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use Kalle\Pdf\Color\Color;
use Kalle\Pdf\Color\MaterialColor;
use Kalle\Pdf\Document\Attachment\AssociatedFileRelationship;
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
use Kalle\Pdf\Layout\PositionMode;
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

$outputDirectory = __DIR__ . '/../../var/examples';

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
$fontRegular = EmbeddedFontSource::fromPath(__DIR__ . '/../../assets/fonts/inter/static/Inter-Regular.ttf');
$fontBold = EmbeddedFontSource::fromPath(__DIR__ . '/../../assets/fonts/inter/static/Inter-Bold.ttf');
$logoPath = __DIR__ . '/../../assets/images/MusterfirmaGmbHLogoDesign.png';

$invoiceNumber = 'RE-2026-0415';
$invoiceDate = '13.04.2026';
$invoiceIssueDate = '20260413';
$servicePeriod = '01.03.2026 - 31.03.2026';
$serviceStartDate = '20260301';
$serviceEndDate = '20260331';
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

    $invoiceXmlLines[] = Xml::element('ram:IncludedSupplyChainTradeLineItem')->withChildren([
        Xml::element('ram:AssociatedDocumentLineDocument')->withChildren([
            Xml::element('ram:LineID')->withText((string) ($index + 1)),
        ]),
        Xml::element('ram:SpecifiedTradeProduct')->withChildren([
            Xml::element('ram:Name')->withText($item['description']),
            Xml::element('ram:Description')->withText('Leistungszeitraum ' . $item['period']),
        ]),
        Xml::element('ram:SpecifiedLineTradeAgreement')->withChildren([
            Xml::element('ram:NetPriceProductTradePrice')->withChildren([
                Xml::element('ram:ChargeAmount')->withText($formatDecimal($item['unitPrice'])),
            ]),
        ]),
        Xml::element('ram:SpecifiedLineTradeDelivery')->withChildren([
            Xml::element('ram:BilledQuantity', ['unitCode' => 'HUR'])->withText($formatDecimal($item['quantity'])),
        ]),
        Xml::element('ram:SpecifiedLineTradeSettlement')->withChildren([
            Xml::element('ram:ApplicableTradeTax')->withChildren([
                Xml::element('ram:TypeCode')->withText('VAT'),
                Xml::element('ram:CategoryCode')->withText('S'),
                Xml::element('ram:RateApplicablePercent')->withText('19.00'),
            ]),
            Xml::element('ram:SpecifiedTradeSettlementLineMonetarySummation')->withChildren([
                Xml::element('ram:LineTotalAmount')->withText($formatDecimal($lineTotal)),
            ]),
        ]),
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
    root: Xml::element('rsm:CrossIndustryInvoice', [
        'xmlns:rsm' => 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100',
        'xmlns:ram' => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
        'xmlns:udt' => 'urn:un:unece:uncefact:data:standard:UnqualifiedDataType:100',
    ])->withChildren([
        Xml::element('rsm:ExchangedDocumentContext')->withChildren([
            Xml::element('ram:GuidelineSpecifiedDocumentContextParameter')->withChildren([
                Xml::element('ram:ID')->withText('urn:factur-x.eu:1p0:en16931:extended'),
            ]),
        ]),
        Xml::element('rsm:ExchangedDocument')->withChildren([
            Xml::element('ram:ID')->withText($invoiceNumber),
            Xml::element('ram:TypeCode')->withText('380'),
            Xml::element('ram:IssueDateTime')->withChildren([
                Xml::element('udt:DateTimeString', ['format' => '102'])->withText($invoiceIssueDate),
            ]),
            Xml::element('ram:IncludedNote')->withChildren([
                Xml::element('ram:Content')->withText('Kundennummer ' . $customerNumber . ', Projektcode ' . $projectCode . ', Bestellreferenz ' . $purchaseOrder),
            ]),
        ]),
        Xml::element('rsm:SupplyChainTradeTransaction')->withChildren([
            ...$invoiceXmlLines,
            Xml::element('ram:ApplicableHeaderTradeAgreement')->withChildren([
                Xml::element('ram:BuyerReference')->withText($customerNumber),
                Xml::element('ram:SellerTradeParty')->withChildren([
                    Xml::element('ram:Name')->withText('DEIN FIRMENNAME'),
                    Xml::element('ram:DefinedTradeContact')->withChildren([
                        Xml::element('ram:TelephoneUniversalCommunication')->withChildren([
                            Xml::element('ram:CompleteNumber')->withText('0123 456789'),
                        ]),
                        Xml::element('ram:EmailURIUniversalCommunication')->withChildren([
                            Xml::element('ram:URIID')->withText('info@deinefirma.de'),
                        ]),
                    ]),
                    Xml::element('ram:PostalTradeAddress')->withChildren([
                        Xml::element('ram:PostcodeCode')->withText('00000'),
                        Xml::element('ram:LineOne')->withText('Strasse Hausnummer'),
                        Xml::element('ram:CityName')->withText('Ort'),
                        Xml::element('ram:CountryID')->withText('DE'),
                    ]),
                    Xml::element('ram:SpecifiedTaxRegistration')->withChildren([
                        Xml::element('ram:ID', ['schemeID' => 'VA'])->withText('DE123456789'),
                    ]),
                ]),
                Xml::element('ram:BuyerTradeParty')->withChildren([
                    Xml::element('ram:Name')->withText('Kundenfirma Mueller GmbH'),
                    Xml::element('ram:DefinedTradeContact')->withChildren([
                        Xml::element('ram:PersonName')->withText('Anna Mueller'),
                    ]),
                    Xml::element('ram:PostalTradeAddress')->withChildren([
                        Xml::element('ram:PostcodeCode')->withText('80331'),
                        Xml::element('ram:LineOne')->withText('Beispielweg 8'),
                        Xml::element('ram:CityName')->withText('Muenchen'),
                        Xml::element('ram:CountryID')->withText('DE'),
                    ]),
                ]),
            ]),
            Xml::element('ram:ApplicableHeaderTradeDelivery')->withChildren([
                Xml::element('ram:ActualDeliverySupplyChainEvent')->withChildren([
                    Xml::element('ram:OccurrenceDateTime')->withChildren([
                        Xml::element('udt:DateTimeString', ['format' => '102'])->withText($serviceEndDate),
                    ]),
                ]),
            ]),
            Xml::element('ram:ApplicableHeaderTradeSettlement')->withChildren([
                Xml::element('ram:InvoiceCurrencyCode')->withText('EUR'),
                Xml::element('ram:SpecifiedTradeSettlementPaymentMeans')->withChildren([
                    Xml::element('ram:TypeCode')->withText('58'),
                    Xml::element('ram:PayeePartyCreditorFinancialAccount')->withChildren([
                        Xml::element('ram:IBANID')->withText('DE12345678901234567890'),
                    ]),
                    Xml::element('ram:PayeeSpecifiedCreditorFinancialInstitution')->withChildren([
                        Xml::element('ram:BICID')->withText('MUSTDEFFXXX'),
                    ]),
                ]),
                Xml::element('ram:ApplicableTradeTax')->withChildren([
                    Xml::element('ram:CalculatedAmount')->withText($formatDecimal($taxAmount)),
                    Xml::element('ram:TypeCode')->withText('VAT'),
                    Xml::element('ram:BasisAmount')->withText($formatDecimal($subtotal)),
                    Xml::element('ram:CategoryCode')->withText('S'),
                    Xml::element('ram:RateApplicablePercent')->withText('19.00'),
                ]),
                Xml::element('ram:BillingSpecifiedPeriod')->withChildren([
                    Xml::element('ram:StartDateTime')->withChildren([
                        Xml::element('udt:DateTimeString', ['format' => '102'])->withText($serviceStartDate),
                    ]),
                    Xml::element('ram:EndDateTime')->withChildren([
                        Xml::element('udt:DateTimeString', ['format' => '102'])->withText($serviceEndDate),
                    ]),
                ]),
                Xml::element('ram:SpecifiedTradePaymentTerms')->withChildren([
                    Xml::element('ram:Description')->withText($paymentTerms),
                ]),
                Xml::element('ram:SpecifiedTradeSettlementHeaderMonetarySummation')->withChildren([
                    Xml::element('ram:LineTotalAmount')->withText($formatDecimal($subtotal)),
                    Xml::element('ram:TaxBasisTotalAmount')->withText($formatDecimal($subtotal)),
                    Xml::element('ram:TaxTotalAmount', ['currencyID' => 'EUR'])->withText($formatDecimal($taxAmount)),
                    Xml::element('ram:GrandTotalAmount')->withText($formatDecimal($totalAmount)),
                    Xml::element('ram:DuePayableAmount')->withText($formatDecimal($totalAmount)),
                ]),
            ]),
        ]),
    ]),
    standalone: true,
);

$invoiceXml = Xml::serialize($xmlDocument);

$document = Pdf::document()
    ->profile(Profile::pdfA3b())
    ->title('Rechnung ' . $invoiceNumber)
    ->author('DEIN FIRMENNAME')
    ->subject('Ausgangsrechnung')
    ->keywords('Rechnung, Buchhaltung, Leistungsabrechnung')
    ->language('de-DE')
    ->creator('examples/e-invoicing/factur-x.php')
    ->creatorTool('pdf2')
    ->pageSize(PageSize::A4())
    ->margin(Margin::all(Units::mm(20)))
    ->attachment(
        'factur-x.xml',
        $invoiceXml,
        'Maschinenlesbare Factur-X Rechnungsdaten',
        MimeType::XML,
        AssociatedFileRelationship::ALTERNATIVE,
    )
    ->imageFile(
        $logoPath,
        ImagePlacement::absolute(
            right: Units::mm(20),
            top: Units::mm(10),
            width: Units::mm(80),
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
            right: Units::mm(0),
            top: Units::mm(12.5),
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
            bottom: Units::mm(252),
            positionMode: PositionMode::ABSOLUTE,
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
            bottom: Units::mm(246.2),
            positionMode: PositionMode::ABSOLUTE,
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
            bottom: Units::mm(188),
            positionMode: PositionMode::ABSOLUTE,
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
            left: Units::mm(120),
            positionMode: PositionMode::ABSOLUTE,
            width: Units::mm(70),
            fontSize: 9,
            lineHeight: 13,
            embeddedFont: $fontRegular,
            color: $textColor,
        ),
    );

$targetPath = $outputDirectory . '/factur-x.pdf';

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
