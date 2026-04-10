<?php

declare(strict_types=1);

use Kalle\Pdf\Document;
use Kalle\Pdf\Internal\Layout\Geometry\Position;
use Kalle\Pdf\Internal\Layout\Geometry\Rect;
use Kalle\Pdf\Internal\Layout\Page\PageSize;
use Kalle\Pdf\Internal\Layout\Page\Units;
use Kalle\Pdf\Internal\Layout\Value\HorizontalAlign;
use Kalle\Pdf\Internal\Layout\Value\VerticalAlign;
use Kalle\Pdf\Page;
use Kalle\Pdf\Profile;
use Kalle\Pdf\Style\Color;
use Kalle\Pdf\Table\Style\CellStyle;
use Kalle\Pdf\Table\Style\HeaderStyle;
use Kalle\Pdf\Table\Style\RowStyle;
use Kalle\Pdf\Table\Style\TableBorder;
use Kalle\Pdf\Table\Style\TablePadding;
use Kalle\Pdf\Table\Style\TableStyle;
use Kalle\Pdf\Table\TableCell;
use Kalle\Pdf\Text\TextBoxOptions;
use Kalle\Pdf\Text\TextOptions;
use Kalle\Pdf\Text\TextSegment;

require __DIR__ . '/../vendor/autoload.php';

$outputDir = __DIR__ . '/../var/examples';

if (!is_dir($outputDir) && !mkdir($outputDir, 0777, true) && !is_dir($outputDir)) {
    throw new RuntimeException(sprintf('Unable to create output directory "%s".', $outputDir));
}

$startedAt = microtime(true);

$document = new Document(
    profile: Profile::standard(1.4),
    title: 'Table test',
    fontConfig: [
        [
            'baseFont' => 'NotoSans-Regular',
            'path' => __DIR__ . '/../assets/fonts/NotoSans-Regular.ttf',
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
    ->addHeaderRow(['Gruppe', 'Beschreibung', 'Status', 'Menge', 'Betrag'])
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
    ->addHeaderRow(['Align', 'Referenztext', 'Kurztext', 'Border / Padding'])
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

$stylePage = $document->addPage(PageSize::A4());
$renderHeader(
    $stylePage,
    'Table test: Padding and Borders',
    'Gezielte Stilfaelle fuer Innenabstand, Border-Prioritaet und dichte Tabellen.',
);

$renderChecklist(
    $stylePage,
    new Rect(Units::mm(20), Units::mm(248), Units::mm(170), Units::mm(22)),
    [
        'Padding muss oben, rechts, unten und links sichtbar unterschiedlich wirken.',
        'Cell-Border-Overrides muessen gegen Table- und Row-Border lesbar bleiben.',
        'Dichte Tabellen mit Zahlen duerfen trotz knappen Spalten nicht kippen.',
    ],
);

$stylePage->createTable(
    new Position(Units::mm(20), Units::mm(238)),
    Units::mm(170),
    [
        Units::mm(28),
        Units::mm(46),
        Units::mm(46),
        Units::mm(50),
    ],
)
    ->font('NotoSans-Regular', 9)
    ->style(new TableStyle(
        padding: TablePadding::symmetric(Units::mm(2), Units::mm(1.2)),
        border: TableBorder::all(color: Color::gray(0.72)),
        verticalAlign: VerticalAlign::TOP,
    ))
    ->headerStyle(new HeaderStyle(
        fillColor: Color::rgb(243, 246, 249),
    ))
    ->addHeaderRow(['Fall', 'Padding', 'Textbild', 'Erwartung'])
    ->addRow([
        'A',
        new TableCell(
            'Standard',
            style: new CellStyle(fillColor: Color::gray(0.96)),
        ),
        "Gleiche Basis\nfuer alle\nVergleiche",
        'Symmetrischer Abstand ohne Schlagseite.',
    ])
    ->addRow([
        'B',
        new TableCell(
            'Top 1 / Bottom 8',
            style: new CellStyle(
                padding: TablePadding::only(top: 1, right: 6, bottom: 8, left: 6),
                fillColor: Color::rgb(250, 245, 232),
            ),
        ),
        "Text soll\nsichtbar nach\noben wandern",
        'Mehr Luft unten als oben.',
    ])
    ->addRow([
        'C',
        new TableCell(
            'Left 12 / Right 2',
            style: new CellStyle(
                padding: TablePadding::only(top: 3, right: 2, bottom: 3, left: 12),
                fillColor: Color::rgb(236, 243, 255),
            ),
        ),
        "Linker Rand\nmuss deutlich\nbreiter wirken",
        'Textblock sichtbar nach rechts versetzt.',
    ])
    ->addRow([
        'D',
        new TableCell(
            'Tight',
            style: new CellStyle(
                padding: TablePadding::only(top: 0.5, right: 1, bottom: 0.5, left: 1),
                fillColor: Color::rgb(245, 245, 245),
            ),
        ),
        "Fast ohne\nInnenabstand",
        'Text darf knapp wirken, aber nicht am Border kleben.',
    ]);

$stylePage->createTable(
    new Position(Units::mm(20), Units::mm(138)),
    Units::mm(170),
    [
        Units::mm(30),
        Units::mm(40),
        Units::mm(45),
        Units::mm(55),
    ],
)
    ->font('NotoSans-Regular', 9)
    ->style(new TableStyle(
        padding: TablePadding::symmetric(Units::mm(1.8), Units::mm(1.2)),
        border: TableBorder::all(color: Color::gray(0.72)),
        verticalAlign: VerticalAlign::MIDDLE,
    ))
    ->rowStyle(new RowStyle(
        border: TableBorder::horizontal(color: Color::rgb(0, 90, 180)),
    ))
    ->headerStyle(new HeaderStyle(
        fillColor: Color::gray(0.92),
    ))
    ->addHeaderRow(['Scope', 'Border', 'Fill', 'Lesart'])
    ->addRow([
        'Table',
        'nur Standard',
        'keine',
        'Referenz fuer alle Kanten der Zeile.',
    ])
    ->addRow([
        'Row',
        new TableCell(
            'horizontal blau',
            style: new CellStyle(fillColor: Color::rgb(239, 246, 255)),
        ),
        'Row-Fill aus',
        'Oben und unten blau, Seiten grau.',
    ])
    ->addRow([
        'Cell',
        new TableCell(
            'links/unten rot',
            style: new CellStyle(
                border: TableBorder::only(['left', 'bottom'], color: Color::rgb(190, 60, 60)),
                fillColor: Color::rgb(255, 246, 246),
            ),
        ),
        new TableCell(
            'grau',
            style: new CellStyle(fillColor: Color::gray(0.95)),
        ),
        'Cell-Border muss Row-Border auf den definierten Seiten uebersteuern.',
    ])
    ->addRow([
        'Merge',
        new TableCell(
            'alle gruen',
            style: new CellStyle(
                border: TableBorder::all(color: Color::rgb(40, 130, 80)),
                fillColor: Color::rgb(241, 250, 244),
            ),
        ),
        'keine',
        'Gleiche Borderfarbe auf allen vier Seiten ohne Doppelstriche.',
    ]);

$stylePage->createTable(
    new Position(Units::mm(20), Units::mm(62)),
    Units::mm(170),
    [
        Units::mm(18),
        Units::mm(62),
        Units::mm(24),
        Units::mm(28),
        Units::mm(38),
    ],
)
    ->font('NotoSans-Regular', 8)
    ->style(new TableStyle(
        padding: TablePadding::symmetric(Units::mm(1.5), Units::mm(1.1)),
        border: TableBorder::all(color: Color::gray(0.7)),
        verticalAlign: VerticalAlign::TOP,
    ))
    ->headerStyle(new HeaderStyle(
        fillColor: Color::rgb(242, 244, 247),
    ))
    ->addHeaderRow(['Pos.', 'Kurztext', 'Menge', 'Preis', 'Kommentar'])
    ->addRow([
        '1',
        'Schmale Spalten mit Zahlen rechts.',
        new TableCell('12', style: new CellStyle(horizontalAlign: HorizontalAlign::CENTER)),
        new TableCell('48,00', style: new CellStyle(horizontalAlign: HorizontalAlign::RIGHT)),
        'Soll auch in enger Tabelle ruhig bleiben.',
    ])
    ->addRow([
        '2',
        'Mehrzeiliger Text mit knapper Breite und bewusst engem Kommentar.',
        new TableCell('3', style: new CellStyle(horizontalAlign: HorizontalAlign::CENTER)),
        new TableCell('120,50', style: new CellStyle(horizontalAlign: HorizontalAlign::RIGHT)),
        'Check fuer Umbruch plus Rechtsbuendigkeit.',
    ])
    ->addRow([
        '3',
        new TableCell(
            [
                TextSegment::plain('Rich '),
                TextSegment::bold('Text'),
                TextSegment::plain(' in enger Zelle'),
            ],
        ),
        new TableCell('1', style: new CellStyle(horizontalAlign: HorizontalAlign::CENTER)),
        new TableCell('9,99', style: new CellStyle(horizontalAlign: HorizontalAlign::RIGHT)),
        'Segment-Mix darf die Zeilenhoehe nicht zerstoeren.',
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
$paginationPage->addTextBox(
    "Pruefpunkte:\n- Fortgesetzte rowspan-Zellen starten auf Folgeseiten ohne oberen Innenborder.\n- Das sichtbare Segment muss unten trotzdem mit dem Standard-Border schliessen.",
    new Rect(Units::mm(12), Units::mm(78), Units::mm(124), Units::mm(10)),
    'NotoSans-Regular',
    7,
    new TextBoxOptions(lineHeight: Units::mm(3.4)),
);

$paginationTable = $paginationPage->createTable(
    new Position(Units::mm(12), Units::mm(70)),
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
    ->addHeaderRow(['Pos.', 'Beschreibung', 'Status', 'Wert'])
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
    ])
    ->addRow([
        new TableCell(
            'G5',
            rowspan: 2,
            style: new CellStyle(
                horizontalAlign: HorizontalAlign::CENTER,
                verticalAlign: VerticalAlign::MIDDLE,
                fillColor: Color::rgb(245, 240, 230),
            ),
        ),
        'Kurze zweite Group zum Gegencheck des Seitenabschlusses mit Bottom-Border.',
        'check',
        '75',
    ])
    ->addRow([
        'Letzte Zeile von G5. Hier sollte die Fortsetzung oben offen, unten aber geschlossen wirken.',
        'ende',
        '80',
    ]);

for ($index = 6; $index <= 14; $index++) {
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

$outputPath = $outputDir . '/test-table.pdf';

$document->writeToFile($outputPath);

printf(
    "Erstellt in %.3f Sekunden: %s\n",
    microtime(true) - $startedAt,
    $outputPath,
);
