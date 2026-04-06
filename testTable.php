<?php

declare(strict_types=1);

use Kalle\Pdf\Document\Document;
use Kalle\Pdf\Document\Geometry\Position;
use Kalle\Pdf\Document\Geometry\Rect;
use Kalle\Pdf\Document\Page;
use Kalle\Pdf\Document\Table\Style\CellStyle;
use Kalle\Pdf\Document\Table\Style\HeaderStyle;
use Kalle\Pdf\Document\Table\Style\RowStyle;
use Kalle\Pdf\Document\Table\Style\TableBorder;
use Kalle\Pdf\Document\Table\Style\TablePadding;
use Kalle\Pdf\Document\Table\Style\TableStyle;
use Kalle\Pdf\Document\Table\TableCell;
use Kalle\Pdf\Document\Text\TextBoxOptions;
use Kalle\Pdf\Document\Text\TextOptions;
use Kalle\Pdf\Document\Text\TextSegment;
use Kalle\Pdf\Graphics\Color;
use Kalle\Pdf\Layout\HorizontalAlign;
use Kalle\Pdf\Layout\PageSize;
use Kalle\Pdf\Layout\Units;
use Kalle\Pdf\Layout\VerticalAlign;

require 'vendor/autoload.php';

$outputDir = __DIR__ . '/var/examples';

if (!is_dir($outputDir) && !mkdir($outputDir, 0777, true) && !is_dir($outputDir)) {
    throw new RuntimeException(sprintf('Unable to create output directory "%s".', $outputDir));
}

$startedAt = microtime(true);

$document = new Document(
    version: 1.4,
    title: 'Table test',
    fontConfig: [
        [
            'baseFont' => 'NotoSans-Regular',
            'path' => 'assets/fonts/NotoSans-Regular.ttf',
            'unicode' => true,
            'subtype' => 'CIDFontType2',
            'encoding' => 'Identity-H',
        ],
    ],
)
    ->registerFont('Helvetica')
    ->registerFont('Helvetica-Bold')
    ->registerFont('Helvetica-Oblique')
    ->registerFont('NotoSans-Regular')
    ->addKeyword('table')
    ->addKeyword('layout')
    ->addKeyword('manual-test');

$renderHeader = static function (Page $page, string $headline, string $subline): void {
    $page->addText(
        $headline,
        new Position(Units::mm(20), Units::mm(285)),
        'Helvetica-Bold',
        16,
        new TextOptions(color: Color::rgb(25, 25, 25)),
    );

    $page->addText(
        $subline,
        new Position(Units::mm(20), Units::mm(278)),
        'Helvetica',
        10,
        new TextOptions(color: Color::gray(0.35)),
    );
};

$renderChecklist = static function (Page $page, Rect $box, array $items): void {
    $lines = array_map(
        static fn (string $item): string => '- ' . $item,
        $items,
    );

    $page->addRectangle(
        $box,
        0.6,
        Color::gray(0.8),
        Color::gray(0.97),
    );

    $page->addTextBox(
        implode("\n", $lines),
        $box,
        'NotoSans-Regular',
        9,
        new TextBoxOptions(
            lineHeight: Units::mm(4.5),
        ),
    );
};

$page = $document->addPage(PageSize::A4());
$renderHeader(
    $page,
    'Table test',
    'Manuelle Tabellenfaelle fuer Spans, Styles und Pagination.',
);

$renderChecklist(
    $page,
    new Rect(Units::mm(20), Units::mm(248), Units::mm(170), Units::mm(22)),
    [
        'Rowspan und Colspan muessen saubere Zellgrenzen behalten.',
        'Header-, Row- und Cell-Styles duerfen sich nicht gegenseitig zerlegen.',
        'Lange Inhalte muessen Zeilenhoehe und Umbruch stabil vergroessern.',
    ],
);

$page->createTable(
    new Position(Units::mm(20), Units::mm(238)),
    Units::mm(170),
    [
        Units::mm(20),
        Units::mm(56),
        Units::mm(28),
        Units::mm(22),
        Units::mm(44),
    ],
)
    ->font('NotoSans-Regular', 9)
    ->style(new TableStyle(
        padding: TablePadding::symmetric(Units::mm(2.2), Units::mm(1.5)),
        border: TableBorder::all(color: Color::gray(0.72)),
        verticalAlign: VerticalAlign::TOP,
    ))
    ->headerStyle(new HeaderStyle(
        fillColor: Color::gray(0.92),
        textColor: Color::gray(0.15),
    ))
    ->rowStyle(new RowStyle(
        textColor: Color::gray(0.2),
    ))
    ->addRow(['Gruppe', 'Beschreibung', 'Status', 'Menge', 'Betrag'], header: true)
    ->addRow([
        new TableCell(
            'A',
            rowspan: 3,
            style: new CellStyle(
                horizontalAlign: HorizontalAlign::CENTER,
                verticalAlign: VerticalAlign::MIDDLE,
                fillColor: Color::rgb(236, 243, 255),
            ),
        ),
        'Analyse und Aufnahme der Basisdaten',
        'Offen',
        new TableCell('1', style: new CellStyle(horizontalAlign: HorizontalAlign::CENTER)),
        new TableCell('180,00 EUR', style: new CellStyle(horizontalAlign: HorizontalAlign::RIGHT)),
    ])
    ->addRow([
        'Konzept und erste Textstruktur',
        'Aktiv',
        new TableCell('2', style: new CellStyle(horizontalAlign: HorizontalAlign::CENTER)),
        new TableCell('240,00 EUR', style: new CellStyle(horizontalAlign: HorizontalAlign::RIGHT)),
    ])
    ->addRow([
        new TableCell(
            [
                TextSegment::plain('Zusammenfassung mit '),
                TextSegment::bold('colspan'),
                TextSegment::plain(' ueber Beschreibung, Status und Menge.'),
            ],
            colspan: 3,
            style: new CellStyle(
                fillColor: Color::gray(0.96),
                border: TableBorder::all(color: Color::gray(0.65)),
            ),
        ),
        new TableCell('420,00 EUR', style: new CellStyle(horizontalAlign: HorizontalAlign::RIGHT)),
    ])
    ->addRow([
        new TableCell(
            'B',
            rowspan: 2,
            style: new CellStyle(
                horizontalAlign: HorizontalAlign::CENTER,
                verticalAlign: VerticalAlign::MIDDLE,
                fillColor: Color::rgb(245, 240, 230),
            ),
        ),
        new TableCell(
            'Diese Zelle ist bewusst laenger und prueft, ob Rowspan, Umbruch und Border-Merging gemeinsam stabil bleiben.',
            style: new CellStyle(verticalAlign: VerticalAlign::TOP),
        ),
        'Pruefung',
        new TableCell('4', style: new CellStyle(horizontalAlign: HorizontalAlign::CENTER)),
        new TableCell('640,00 EUR', style: new CellStyle(horizontalAlign: HorizontalAlign::RIGHT)),
    ])
    ->addRow([
        'Abschluss',
        'Fertig',
        new TableCell('1', style: new CellStyle(horizontalAlign: HorizontalAlign::CENTER)),
        new TableCell('95,00 EUR', style: new CellStyle(horizontalAlign: HorizontalAlign::RIGHT)),
    ]);

$renderChecklist(
    $page,
    new Rect(Units::mm(20), Units::mm(92), Units::mm(170), Units::mm(20)),
    [
        'Die naechste Haertungsstufe sollte visuell Top/Middle/Bottom nebeneinander pruefen.',
        'Schmale Spalten muessen auch mit Padding und Border-Overrides lesbar bleiben.',
    ],
);

$page->createTable(
    new Position(Units::mm(20), Units::mm(82)),
    Units::mm(170),
    [
        Units::mm(24),
        Units::mm(58),
        Units::mm(34),
        Units::mm(54),
    ],
)
    ->font('NotoSans-Regular', 9)
    ->style(new TableStyle(
        padding: TablePadding::symmetric(Units::mm(2), Units::mm(1.4)),
        border: TableBorder::all(color: Color::gray(0.75)),
        verticalAlign: VerticalAlign::MIDDLE,
    ))
    ->headerStyle(new HeaderStyle(
        fillColor: Color::rgb(241, 246, 250),
    ))
    ->addRow(['Align', 'Referenztext', 'Kurztext', 'Border / Padding'], header: true)
    ->addRow([
        new TableCell('Top', style: new CellStyle(verticalAlign: VerticalAlign::TOP)),
        "Mehrzeiliger Inhalt\nerhoeht die Zeilenhoehe\nund dient als Referenz.",
        new TableCell('oben', style: new CellStyle(verticalAlign: VerticalAlign::TOP)),
        new TableCell(
            "Nur links\nund unten",
            style: new CellStyle(
                padding: TablePadding::only(top: 1, right: 2, bottom: 4, left: 8),
                border: TableBorder::only(['left', 'bottom'], color: Color::rgb(190, 60, 60)),
            ),
        ),
    ])
    ->addRow([
        new TableCell('Middle', style: new CellStyle(verticalAlign: VerticalAlign::MIDDLE)),
        "Noch eine Zeile\nmit bewusst enger Spalte\nfuer den Umbruch.",
        new TableCell('mitte', style: new CellStyle(verticalAlign: VerticalAlign::MIDDLE)),
        new TableCell(
            'Volle Border mit grauer Flaeche.',
            style: new CellStyle(
                fillColor: Color::gray(0.94),
                border: TableBorder::all(color: Color::gray(0.55)),
            ),
        ),
    ])
    ->addRow([
        new TableCell('Bottom', style: new CellStyle(verticalAlign: VerticalAlign::BOTTOM)),
        "Die dritte Zeile zeigt,\ndass kurze Inhalte unten landen koennen.",
        new TableCell('unten', style: new CellStyle(verticalAlign: VerticalAlign::BOTTOM)),
        new TableCell(
            'Rechtsbuendig mit viel Innenabstand.',
            style: new CellStyle(
                horizontalAlign: HorizontalAlign::RIGHT,
                padding: TablePadding::only(top: 2, right: 8, bottom: 2, left: 2),
            ),
        ),
    ]);

$paginationPage = $document->addPage(Units::mm(148), Units::mm(110));
$paginationPage->addText(
    'Table test: Pagination',
    new Position(Units::mm(12), Units::mm(102)),
    'Helvetica-Bold',
    13,
    new TextOptions(color: Color::rgb(25, 25, 25)),
);
$paginationPage->addTextBox(
    "Diese Sequenz soll Header-Wiederholung, Splits und rowspan ueber Seitenumbrueche sichtbar machen.\nDie Folgeseiten werden vom Table-Renderer automatisch erzeugt.",
    new Rect(Units::mm(12), Units::mm(90), Units::mm(124), Units::mm(10)),
    'NotoSans-Regular',
    8,
    new TextBoxOptions(lineHeight: Units::mm(3.8)),
);

$paginationTable = $paginationPage->createTable(
    new Position(Units::mm(12), Units::mm(84)),
    Units::mm(124),
    [
        Units::mm(16),
        Units::mm(56),
        Units::mm(18),
        Units::mm(34),
    ],
    Units::mm(10),
)
    ->font('NotoSans-Regular', 8)
    ->style(new TableStyle(
        padding: TablePadding::symmetric(Units::mm(1.8), Units::mm(1.1)),
        border: TableBorder::all(color: Color::gray(0.72)),
        verticalAlign: VerticalAlign::TOP,
    ))
    ->headerStyle(new HeaderStyle(
        fillColor: Color::gray(0.9),
    ))
    ->addRow(['Pos.', 'Beschreibung', 'Status', 'Wert'], header: true)
    ->addRow(['1', 'Kurze Zeile vor dem Split-Test.', 'ok', '10'])
    ->addRow(['2', 'Noch eine kurze Zeile, damit die Gruppe nicht direkt am Anfang startet.', 'ok', '20'])
    ->addRow(['3', 'Schmale Tabellen auf kleinen Seiten sind fuer Pagination besonders empfindlich.', 'ok', '30'])
    ->addRow([
        new TableCell(
            'G4',
            rowspan: 4,
            style: new CellStyle(
                horizontalAlign: HorizontalAlign::CENTER,
                verticalAlign: VerticalAlign::MIDDLE,
                fillColor: Color::rgb(236, 243, 255),
            ),
        ),
        'Start einer vierzeiligen Group, die bewusst in den Seitenumbruch hineinlaeuft.',
        'lauf',
        '40',
    ])
    ->addRow([
        'Der Text bleibt absichtlich lang, damit Hoehe aufgebaut wird und der Split sichtbar wird.',
        'lauf',
        '50',
    ])
    ->addRow([
        'Auch Header-Wiederholung und Border-Fortsetzung sollen hier kontrolliert werden.',
        'lauf',
        '60',
    ])
    ->addRow([
        'Letzte Zeile der Group mit identischer rowspan-Zelle links.',
        'ende',
        '70',
    ]);

for ($index = 5; $index <= 14; $index++) {
    $description = sprintf(
        'Regulaere Folgezeile %d fuer wiederholte Header und konstantes Split-Verhalten.',
        $index,
    );

    if ($index % 3 === 0) {
        $description .= ' Mit etwas Zusatztext fuer einen zweiten Umbruch.';
    }

    $paginationTable->addRow([
        (string) $index,
        $description,
        $index % 2 === 0 ? 'ok' : 'neu',
        (string) ($index * 10),
    ]);
}

$targetPath = $outputDir . '/table_test_' . new DateTime()->format('Y-m-d-H-i-s') . '.pdf';
file_put_contents($targetPath, $document->render());

printf(
    "Erstellt in %.3f Sekunden: %s\n",
    microtime(true) - $startedAt,
    $targetPath,
);
