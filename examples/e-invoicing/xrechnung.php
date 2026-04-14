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
$invoiceIssueDate = '2026-04-13';
$invoiceDueDate = '2026-04-27';
$servicePeriod = '01.03.2026 - 31.03.2026';
$serviceStartDate = '2026-03-01';
$serviceEndDate = '2026-03-31';
$customerNumber = 'KD-10482';
$projectCode = 'PLT-OPS-2026-Q2';
$purchaseOrder = 'PO-7781-BCG';
$paymentTerms = 'zahlbar innerhalb von 14 Tagen ohne Abzug';
$leitwegId = '992-12345-77';

$lineItems = [
    ['period' => '01.03.-07.03.', 'description' => 'Betrieb und Monitoring der Produktionsplattform inkl. Incident Review und Bereitschaftsauswertung', 'quantity' => 1.0, 'unit' => 'Pauschale', 'unitPrice' => 1850.00],
    ['period' => '03.03.2026', 'description' => 'Sicherheitsupdate des Shop-Frameworks inkl. Staging-Test und Deploy-Freigabe', 'quantity' => 3.5, 'unit' => 'Std.', 'unitPrice' => 128.00],
    ['period' => '05.03.2026', 'description' => 'Optimierung der Checkout-Validierung zur Reduktion von Zahlungsabbruechen', 'quantity' => 5.0, 'unit' => 'Std.', 'unitPrice' => 122.00],
    ['period' => '08.03.2026', 'description' => 'Einrichtung eines Performance-Dashboards fuer Conversion, Warenkorb und API-Latenzen', 'quantity' => 4.0, 'unit' => 'Std.', 'unitPrice' => 118.00],
    ['period' => '10.03.2026', 'description' => 'Content-Pflege Fruehjahrskampagne inkl. Landingpage, Hero-Banner und Produktteaser', 'quantity' => 6.0, 'unit' => 'Std.', 'unitPrice' => 95.00],
    ['period' => '12.03.2026', 'description' => 'UX-Review des Kundenkontos mit priorisierter Massnahmenliste fuer das interne Produktteam', 'quantity' => 2.5, 'unit' => 'Std.', 'unitPrice' => 132.00],
    ['period' => '17.03.2026', 'description' => 'Schnittstellenanalyse ERP zu Shop inkl. Fehlerprotokoll fuer den Exportprozess', 'quantity' => 4.5, 'unit' => 'Std.', 'unitPrice' => 128.00],
    ['period' => '21.03.2026', 'description' => 'Implementierung einer Staffelpreislogik fuer B2B-Kunden im Produktdetail', 'quantity' => 7.0, 'unit' => 'Std.', 'unitPrice' => 124.00],
];

$formatAmount = static fn (float $amount): string => number_format($amount, 2, ',', '.') . ' EUR';
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

    $invoiceXmlLines[] = Xml::element('cac:InvoiceLine')->withChildren([
        Xml::element('cbc:ID')->withText((string) ($index + 1)),
        Xml::element('cbc:InvoicedQuantity', ['unitCode' => 'HUR'])->withText($formatDecimal($item['quantity'])),
        Xml::element('cbc:LineExtensionAmount', ['currencyID' => 'EUR'])->withText($formatDecimal($lineTotal)),
        Xml::element('cac:Item')->withChildren([
            Xml::element('cbc:Description')->withText($item['description']),
            Xml::element('cbc:Name')->withText('Leistungszeitraum ' . $item['period']),
            Xml::element('cac:ClassifiedTaxCategory')->withChildren([
                Xml::element('cbc:ID')->withText('S'),
                Xml::element('cbc:Percent')->withText('19.00'),
                Xml::element('cac:TaxScheme')->withChildren([
                    Xml::element('cbc:ID')->withText('VAT'),
                ]),
            ]),
        ]),
        Xml::element('cac:Price')->withChildren([
            Xml::element('cbc:PriceAmount', ['currencyID' => 'EUR'])->withText($formatDecimal($item['unitPrice'])),
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
            TableCell::text('Pos.')->withBackgroundColor($tableHeaderColor)->withHorizontalAlign(TextAlign::CENTER),
            TableCell::text('Zeitraum')->withBackgroundColor($tableHeaderColor),
            TableCell::text('Beschreibung')->withBackgroundColor($tableHeaderColor),
            TableCell::text('Menge')->withBackgroundColor($tableHeaderColor)->withHorizontalAlign(TextAlign::CENTER)->withNoWrap(),
            TableCell::text('Satz netto')->withBackgroundColor($tableHeaderColor)->withHorizontalAlign(TextAlign::RIGHT)->withNoWrap(),
            TableCell::text('Betrag netto')->withBackgroundColor($tableHeaderColor)->withHorizontalAlign(TextAlign::RIGHT)->withNoWrap(),
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
    root: Xml::element('Invoice', [
        'xmlns' => 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2',
        'xmlns:cac' => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
        'xmlns:cbc' => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
    ])->withChildren([
        Xml::element('cbc:CustomizationID')->withText('urn:cen.eu:en16931:2017'),
        Xml::element('cbc:ProfileID')->withText('urn:fdc:peppol.eu:2017:poacc:billing:01:1.0'),
        Xml::element('cbc:ID')->withText($invoiceNumber),
        Xml::element('cbc:IssueDate')->withText($invoiceIssueDate),
        Xml::element('cbc:DueDate')->withText($invoiceDueDate),
        Xml::element('cbc:InvoiceTypeCode')->withText('380'),
        Xml::element('cbc:DocumentCurrencyCode')->withText('EUR'),
        Xml::element('cbc:BuyerReference')->withText($leitwegId),
        Xml::element('cac:OrderReference')->withChildren([
            Xml::element('cbc:ID')->withText($purchaseOrder),
        ]),
        Xml::element('cac:AccountingSupplierParty')->withChildren([
            Xml::element('cac:Party')->withChildren([
                Xml::element('cbc:EndpointID', ['schemeID' => 'EM'])->withText('info@deinefirma.de'),
                Xml::element('cac:PartyName')->withChildren([
                    Xml::element('cbc:Name')->withText('DEIN FIRMENNAME'),
                ]),
                Xml::element('cac:PostalAddress')->withChildren([
                    Xml::element('cbc:StreetName')->withText('Strasse Hausnummer'),
                    Xml::element('cbc:CityName')->withText('Ort'),
                    Xml::element('cbc:PostalZone')->withText('00000'),
                    Xml::element('cac:Country')->withChildren([
                        Xml::element('cbc:IdentificationCode')->withText('DE'),
                    ]),
                ]),
                Xml::element('cac:PartyTaxScheme')->withChildren([
                    Xml::element('cbc:CompanyID')->withText('DE123456789'),
                    Xml::element('cac:TaxScheme')->withChildren([
                        Xml::element('cbc:ID')->withText('VAT'),
                    ]),
                ]),
                Xml::element('cac:PartyLegalEntity')->withChildren([
                    Xml::element('cbc:RegistrationName')->withText('DEIN FIRMENNAME'),
                ]),
                Xml::element('cac:Contact')->withChildren([
                    Xml::element('cbc:Telephone')->withText('0123 456789'),
                    Xml::element('cbc:ElectronicMail')->withText('info@deinefirma.de'),
                ]),
            ]),
        ]),
        Xml::element('cac:AccountingCustomerParty')->withChildren([
            Xml::element('cac:Party')->withChildren([
                Xml::element('cbc:EndpointID', ['schemeID' => '0204'])->withText($leitwegId),
                Xml::element('cac:PartyIdentification')->withChildren([
                    Xml::element('cbc:ID')->withText($customerNumber),
                ]),
                Xml::element('cac:PartyName')->withChildren([
                    Xml::element('cbc:Name')->withText('Kundenfirma Mueller GmbH'),
                ]),
                Xml::element('cac:PostalAddress')->withChildren([
                    Xml::element('cbc:StreetName')->withText('Beispielweg 8'),
                    Xml::element('cbc:CityName')->withText('Muenchen'),
                    Xml::element('cbc:PostalZone')->withText('80331'),
                    Xml::element('cac:Country')->withChildren([
                        Xml::element('cbc:IdentificationCode')->withText('DE'),
                    ]),
                ]),
                Xml::element('cac:Contact')->withChildren([
                    Xml::element('cbc:Name')->withText('Anna Mueller'),
                ]),
            ]),
        ]),
        Xml::element('cac:PaymentMeans')->withChildren([
            Xml::element('cbc:PaymentMeansCode')->withText('58'),
            Xml::element('cac:PayeeFinancialAccount')->withChildren([
                Xml::element('cbc:ID')->withText('DE12345678901234567890'),
                Xml::element('cac:FinancialInstitutionBranch')->withChildren([
                    Xml::element('cbc:ID')->withText('MUSTDEFFXXX'),
                ]),
            ]),
        ]),
        Xml::element('cac:PaymentTerms')->withChildren([
            Xml::element('cbc:Note')->withText($paymentTerms),
        ]),
        Xml::element('cac:TaxTotal')->withChildren([
            Xml::element('cbc:TaxAmount', ['currencyID' => 'EUR'])->withText($formatDecimal($taxAmount)),
            Xml::element('cac:TaxSubtotal')->withChildren([
                Xml::element('cbc:TaxableAmount', ['currencyID' => 'EUR'])->withText($formatDecimal($subtotal)),
                Xml::element('cbc:TaxAmount', ['currencyID' => 'EUR'])->withText($formatDecimal($taxAmount)),
                Xml::element('cac:TaxCategory')->withChildren([
                    Xml::element('cbc:ID')->withText('S'),
                    Xml::element('cbc:Percent')->withText('19.00'),
                    Xml::element('cac:TaxScheme')->withChildren([
                        Xml::element('cbc:ID')->withText('VAT'),
                    ]),
                ]),
            ]),
        ]),
        Xml::element('cac:LegalMonetaryTotal')->withChildren([
            Xml::element('cbc:LineExtensionAmount', ['currencyID' => 'EUR'])->withText($formatDecimal($subtotal)),
            Xml::element('cbc:TaxExclusiveAmount', ['currencyID' => 'EUR'])->withText($formatDecimal($subtotal)),
            Xml::element('cbc:TaxInclusiveAmount', ['currencyID' => 'EUR'])->withText($formatDecimal($totalAmount)),
            Xml::element('cbc:PayableAmount', ['currencyID' => 'EUR'])->withText($formatDecimal($totalAmount)),
        ]),
        ...$invoiceXmlLines,
    ]),
    standalone: true,
);

$invoiceXml = Xml::serialize($xmlDocument);

$document = Pdf::document()
    ->profile(Profile::pdfA3b())
    ->title('Rechnung ' . $invoiceNumber)
    ->author('DEIN FIRMENNAME')
    ->subject('Ausgangsrechnung')
    ->keywords('XRechnung, Buchhaltung, Leistungsabrechnung')
    ->language('de-DE')
    ->creator('examples/e-invoicing/xrechnung.php')
    ->creatorTool('pdf2')
    ->pageSize(PageSize::A4())
    ->margin(Margin::all(Units::mm(20)))
    ->attachment(
        'xrechnung.xml',
        $invoiceXml,
        'Maschinenlesbare XRechnung',
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
        'XRechnung',
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
            TextSegment::plain(PHP_EOL . 'Leitweg-ID: ' . $leitwegId),
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
        "Sehr geehrte Frau Mueller,\n\nanbei die visuelle Darstellung der XRechnung fuer die im Leistungsmonat erbrachten Betriebs-, Optimierungs- und Projektleistungen.",
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
        "Bitte ueberweisen Sie den Gesamtbetrag {$paymentTerms} auf das unten genannte Geschaeftskonto.\n\nBank: Musterbank AG\nIBAN: DE12 3456 7890 1234 5678 90\nBIC: MUSTDEFFXXX\n\nDie Leitweg-ID fuer dieses Beispiel lautet {$leitwegId}.",
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

$targetPath = $outputDirectory . '/xrechnung.pdf';

try {
    $document->writeToFile($targetPath);
} catch (DocumentBuildException $exception) {
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);

    exit(1);
}

$duration = microtime(true) - $startedAt;
$size = filesize($targetPath);

printf("Created %s in %.2f ms (%s bytes)\n", $targetPath, $duration * 1000, number_format((float) $size, 0, ',', '.'));
