<?php

declare(strict_types=1);

use Kalle\Pdf\Layout\BulletType;
use Kalle\Pdf\Styles\CellStyle;
use Kalle\Pdf\Document\Document;
use Kalle\Pdf\Layout\PageSize;
use Kalle\Pdf\Styles\RowStyle;
use Kalle\Pdf\Styles\TableBorder;
use Kalle\Pdf\Document\TableCell;
use Kalle\Pdf\Styles\TablePadding;
use Kalle\Pdf\Styles\TableStyle;
use Kalle\Pdf\Layout\HorizontalAlign;
use Kalle\Pdf\Layout\TextOverflow;
use Kalle\Pdf\Document\TextSegment;
use Kalle\Pdf\Layout\Units;
use Kalle\Pdf\Layout\VerticalAlign;
use Kalle\Pdf\Element\Image;
use Kalle\Pdf\Graphics\Color;
use Kalle\Pdf\Graphics\Opacity;

require 'vendor/autoload.php';

$document = new Document(
    version: 1.4,
    title: 'Kalle PDF Demo',
    author: 'Kalle',
    subject: 'Beispiel für Text, Metadaten und mehrere Seiten',
    language: 'de-DE',
    fontConfig: [
        [
            'baseFont' => 'NotoSans-Regular',
            'path' => 'assets/fonts/NotoSans-Regular.ttf',
            'unicode' => true,
            'subtype' => 'CIDFontType2',
            'encoding' => 'Identity-H',
        ],
        [
            'baseFont' => 'NotoSans-Bold',
            'path' => 'assets/fonts/NotoSans-Bold.ttf',
            'unicode' => true,
            'subtype' => 'CIDFontType2',
            'encoding' => 'Identity-H',
        ],
        [
            'baseFont' => 'NotoSans-Italic',
            'path' => 'assets/fonts/NotoSans-Italic.ttf',
            'unicode' => true,
            'subtype' => 'CIDFontType2',
            'encoding' => 'Identity-H',
        ],
        [
            'baseFont' => 'NotoSans-BoldItalic',
            'path' => 'assets/fonts/NotoSans-BoldItalic.ttf',
            'unicode' => true,
            'subtype' => 'CIDFontType2',
            'encoding' => 'Identity-H',
        ],
        [
            'baseFont' => 'NotoSerif-Regular',
            'path' => 'assets/fonts/NotoSerif-Regular.ttf',
            'unicode' => true,
            'subtype' => 'CIDFontType2',
            'encoding' => 'Identity-H',
        ],
        [
            'baseFont' => 'NotoSansMono-Regular',
            'path' => 'assets/fonts/NotoSansMono-Regular.ttf',
            'unicode' => true,
            'subtype' => 'CIDFontType2',
            'encoding' => 'Identity-H',
        ],
        [
            'baseFont' => 'NotoSansCJKsc-Regular',
            'path' => 'assets/fonts/NotoSansCJKsc-Regular.otf',
            'unicode' => true,
            'subtype' => 'CIDFontType0',
            'encoding' => 'Identity-H',
        ],
    ],
);

$document->addKeyword('demo')
    ->addKeyword('pdf')
    ->addKeyword('tagged')
    ->addFont('Helvetica')
    ->addFont('NotoSans-Regular')
    ->addFont('NotoSans-Bold')
    ->addFont('NotoSans-Italic')
    ->addFont('NotoSans-BoldItalic')
    ->addFont('NotoSerif-Regular')
    ->addFont('NotoSansMono-Regular')
    ->addFont('NotoSansCJKsc-Regular');

$sansPage = $document->addPage(PageSize::A4());
$sansPage->textFrame(Units::mm(20), Units::mm(265), Units::mm(170), Units::mm(20))
    ->heading('Noto Sans', 'NotoSans-Regular', 16, 'H1')
    ->paragraph(
        implode(PHP_EOL, ['abcdefghijklmnopqrstuvwxyz', 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', '0123456789.:,;()*!?\'@#<>$%&^+-=~']),
        'NotoSans-Regular',
        12,
        'P',
    )->paragraph(
        implode(PHP_EOL, ['abcdefghijklmnopqrstuvwxyz', 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', '0123456789.:,;()*!?\'@#<>$%&^+-=~']),
        'NotoSans-Regular',
        12,
        'P',
        align: HorizontalAlign::CENTER,
    )->paragraph(
        implode(PHP_EOL, ['abcdefghijklmnopqrstuvwxyz', 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', '0123456789.:,;()*!?\'@#<>$%&^+-=~']),
        'NotoSans-Regular',
        12,
        'P',
        align: HorizontalAlign::RIGHT,
    )->paragraph(
        implode(' ', [
            'abcde',
            'fghij',
            'klmno',
            'pqrst',
            'uvwxyz',
            'ABCDE',
            'FGHIJ',
            'KLMNO',
            'PQRST',
            'UVWXYZ',
            '01234',
            '56789',
            '.:,;()',
            '*!?\'@#',
            '<>$%&',
            '^+-=~',
            'abcde',
            'fghij',
            'klmno',
            'pqrst',
            'uvwxyz',
            'ABCDE',
            'FGHIJ',
            'KLMNO',
            'PQRST',
            'UVWXYZ',
        ]),
        'NotoSans-Regular',
        12,
        'P',
        align: HorizontalAlign::JUSTIFY,
    )->paragraph(
        [
            new TextSegment('CLIP: ', Color::rgb(200, 30, 30), bold: true),
            new TextSegment('abcde fghij klmno pqrst uvwxyz ABCDE FGHIJ KLMNO PQRST UVWXYZ 01234 56789 .:,;() *!?"@# <>$%& ^+-=~ abcde fghij klmno pqrst uvwxyz ABCDE FGHIJ KLMNO PQRST UVWXYZ 01234 56789 .:,;() *!?"@# <>$%& ^+-=~ abcde fghij klmno pqrst uvwxyz'),
        ],
        'NotoSans-Regular',
        12,
        'P',
        maxLines: 2,
    )->paragraph(
        [
            new TextSegment('ELLIPSIS: ', Color::rgb(200, 30, 30), bold: true),
            new TextSegment('abcde fghij klmno pqrst uvwxyz ABCDE FGHIJ KLMNO PQRST UVWXYZ 01234 56789 .:,;() *!?"@# <>$%& ^+-=~ abcde fghij klmno pqrst uvwxyz ABCDE FGHIJ KLMNO PQRST UVWXYZ 01234 56789 .:,;() *!?"@# <>$%& ^+-=~ abcde fghij klmno pqrst uvwxyz'),
        ],
        'NotoSans-Regular',
        12,
        'P',
        maxLines: 2,
        overflow: TextOverflow::ELLIPSIS,
    )->paragraph(
        implode(PHP_EOL, ['abcdefghijklmnopqrstuvwxyz', 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', '0123456789.:,;()*!?\'@#<>$%&^+-=~']),
        'NotoSans-Regular',
        12,
        'P',
        color: Color::rgb(0, 0, 255),
    )->paragraph(
        implode(PHP_EOL, ['abcdefghijklmnopqrstuvwxyz', 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', '0123456789.:,;()*!?\'@#<>$%&^+-=~']),
        'NotoSans-Regular',
        12,
        'P',
        opacity: Opacity::fill(0.5),
    )->paragraph(
        implode(PHP_EOL, ['abcdefghijklmnopqrstuvwxyz', 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', '0123456789.:,;()*!?\'@#<>$%&^+-=~']),
        'NotoSans-Regular',
        12,
        'P',
    )->paragraph(
        [
            new TextSegment('Achtung:', Color::rgb(255, 0, 0), bold: true, underline: true),
            new TextSegment(
                implode(PHP_EOL, ['abcdefghijklmnopqrstuvwxyz', 'ABCDEFGHIJKLMNOPQRSTUVWXYZ']),
                italic: true,
            ),
            new TextSegment(PHP_EOL . '0123456789.:,;()*!?\'@#<>$%&^+-=~', strikethrough: true),
        ],
        'NotoSans-Regular',
        12,
        'P',
    );
$sansPage->addLine(
    20,
    180,
    180,
    180,
    2.5,
    Color::rgb(255, 0, 0),
    Opacity::stroke(0.25),
);

$serifPage = $document->addPage(PageSize::A4());
$serifPage->textFrame(Units::mm(20), Units::mm(265), Units::mm(170), Units::mm(20))
    ->heading('Noto Serif', 'NotoSerif-Regular', 16, 'H1')
    ->paragraph(
        'abcdefghijklmnopqrstuvwxyz ABCDEFGHIJKLMNOPQRSTUVWXYZ 0123456789.:,;()*!?\'@#<>$%&^+-=~',
        'NotoSerif-Regular',
        12,
        'P',
    );

$serifPage->addRectangle(10, 20, 100, 40);

$serifPage->addRectangle(
    10,
    80,
    100,
    40,
    null,
    null,
    Color::gray(0.5),
);

$serifPage->addRectangle(
    10,
    140,
    100,
    40,
    2.5,
    Color::rgb(255, 0, 0),
    Color::gray(0.9),
    Opacity::both(0.4),
);

$serifPage->addText(
    text: 'Google Website',
    x: 20,
    y: 235,
    baseFont: 'NotoSans-Regular',
    size: 12,
    tag: 'P',
    color: Color::rgb(0, 0, 255),
    underline: true,
    link: 'https://google.com',
);

$serifPage->addImage(
    Image::fromFile('assets/images/demo.jpg'),
    Units::mm(50),
    Units::mm(0),
    Units::mm(140),
    Units::mm(113.33),
);

$monoPage = $document->addPage(PageSize::A4());
$monoPage->textFrame(Units::mm(20), Units::mm(265), Units::mm(170), Units::mm(20))
    ->heading('Noto Sans Mono', 'NotoSansMono-Regular', 16, 'H1')
    ->paragraph(
        'abcdefghijklmnopqrstuvwxyz ABCDEFGHIJKLMNOPQRSTUVWXYZ 0123456789.:,;()*!?\'@#<>$%&^+-=~',
        'NotoSansMono-Regular',
        12,
        'P',
    );

$monoPage
    ->addPath()
    ->moveTo(60, 240)
    ->lineTo(100, 200)
    ->lineTo(60, 160)
    ->lineTo(20, 200)
    ->close()
    ->fillAndStroke(
        2.5,
        Color::rgb(255, 0, 0),
        Color::gray(0.5),
        Opacity::both(0.4),
    );

$cjkPage = $document->addPage(PageSize::A4());
$cjkPage->textFrame(Units::mm(20), Units::mm(265), Units::mm(170), Units::mm(20))
    ->heading('Noto Sans CJK', 'NotoSansCJKsc-Regular', 16, 'H1')
    ->paragraph(
        '漢字とカタカナ',
        'NotoSansCJKsc-Regular',
        14,
        'P',
    );

$cjkPage->addCircle(
    100,
    100,
    30,
    2.5,
    Color::rgb(255, 0, 0),
    Color::gray(0.5),
    Opacity::both(0.4),
);

$standardPage = $document->addPage(PageSize::A4());
$standardPage->textFrame(Units::mm(20), Units::mm(265), Units::mm(170), Units::mm(20))
    ->heading('Helvetica', 'Helvetica', 16, 'H1')
    ->paragraph(
        'abcdefghijklmnopqrstuvwxyz ABCDEFGHIJKLMNOPQRSTUVWXYZ 0123456789.:,;()*!?\'@#<>$%&^+-=~',
        'Helvetica',
        12,
        'P',
    );

$tablePage = $document->addPage(PageSize::A4());
$tablePage->textFrame(Units::mm(20), Units::mm(265), Units::mm(170), Units::mm(20))
    ->heading('Table Demo', 'NotoSans-Regular', 16, 'H1')
    ->paragraph(
        'Erste Tabellenstufe mit festen Spaltenbreiten, Header-Zeile und automatischem Umbruch in den Zellen.',
        'NotoSans-Regular',
        11,
        'P',
    );

$tablePage->addTable(
    Units::mm(20),
    Units::mm(225),
    Units::mm(170),
    [
        Units::mm(22),
        Units::mm(88),
        Units::mm(30),
        Units::mm(30),
    ],
    Units::mm(20),
)
    ->font('NotoSans-Regular', 11)
    ->style(new TableStyle(
        padding: TablePadding::all(Units::mm(2.5)),
    ))
    ->headerStyle(new RowStyle(
        fillColor: Color::gray(0.92),
        textColor: Color::rgb(180, 20, 20),
    ))
    ->rowStyle(new RowStyle(
        textColor: Color::gray(0.15),
    ))
    ->addRow(['ID', 'Titel', 'Status', 'Preis'], header: true)
    ->addRow([
        '1',
        'Starter-Paket mit kurzer Beschreibung.',
        new TableCell(
            'Aktiv',
            style: new CellStyle(
                horizontalAlign: HorizontalAlign::CENTER,
                fillColor: Color::gray(0.94),
            ),
        ),
        '19,99 EUR',
    ])
    ->addRow([
        '2',
        [
            new TextSegment('Pro Plan', bold: true),
            new TextSegment(' mit zwei Zeilen Text, damit der automatische Zellumbruch sichtbar wird.'),
        ],
        new TableCell(
            'Beta',
            style: new CellStyle(
                horizontalAlign: HorizontalAlign::CENTER,
                fillColor: Color::gray(0.88),
            ),
        ),
        '49,00 EUR',
    ])
    ->addRow([
        '3',
        'Enterprise mit Link zur Dokumentation.',
        new TableCell(
            [new TextSegment('Docs', color: Color::rgb(0, 0, 255), link: 'https://example.com/docs', underline: true)],
            style: new CellStyle(horizontalAlign: HorizontalAlign::CENTER),
        ),
        'auf Anfrage',
    ]);

$tablePage->addTable(
    Units::mm(20),
    Units::mm(95),
    Units::mm(170),
    [
        Units::mm(42.5),
        Units::mm(42.5),
        Units::mm(42.5),
        Units::mm(42.5),
    ],
)
    ->font('NotoSans-Regular', 11)
    ->style(new TableStyle(
        padding: TablePadding::all(Units::mm(2.5)),
    ))
    ->headerStyle(new RowStyle(
        fillColor: Color::gray(0.9),
        textColor: Color::rgb(180, 20, 20),
    ))
    ->addRow(['Standard', 'Nur unten', 'Nur links', 'Nur rechts'], header: true)
    ->addRow([
        new TableCell('Standard', style: new CellStyle(border: TableBorder::all(color: Color::rgb(0, 90, 200)))),
        new TableCell('Nur unten rot', style: new CellStyle(border: TableBorder::only(['bottom'], color: Color::rgb(220, 30, 30)))),
        new TableCell('Nur links', style: new CellStyle(border: TableBorder::only(['left'], color: Color::rgb(20, 140, 60)))),
        new TableCell('Nur rechts', style: new CellStyle(border: TableBorder::only(['right'], color: Color::rgb(180, 90, 20)))),
    ])
    ->addRow([
        new TableCell('Oben/Unten', style: new CellStyle(border: TableBorder::horizontal(color: Color::rgb(120, 40, 180)))),
        new TableCell('Links/Rechts', style: new CellStyle(border: TableBorder::vertical(color: Color::rgb(40, 120, 180)))),
        new TableCell('Nur oben', style: new CellStyle(border: TableBorder::only(['top'], color: Color::rgb(220, 30, 30)))),
        new TableCell('Rahmen', style: new CellStyle(border: TableBorder::all(color: Color::rgb(20, 140, 60)))),
    ]);

$paddingPage = $document->addPage(PageSize::A4());
$paddingPage->textFrame(Units::mm(20), Units::mm(265), Units::mm(170), Units::mm(20))
    ->heading('Padding Demo', 'NotoSans-Regular', 16, 'H1')
    ->paragraph(
        'Die Tabelle zeigt den Unterschied zwischen Tabellen-Default-Padding und gezieltem Zell-Override.',
        'NotoSans-Regular',
        11,
        'P',
    );

$paddingPage->addTable(
    Units::mm(20),
    Units::mm(225),
    Units::mm(170),
    [
        Units::mm(35),
        Units::mm(65),
        Units::mm(70),
    ],
    Units::mm(20),
)
    ->font('NotoSans-Regular', 11)
    ->style(new TableStyle(
        padding: TablePadding::symmetric(Units::mm(5), Units::mm(2)),
    ))
    ->headerStyle(new RowStyle(
        fillColor: Color::gray(0.92),
        textColor: Color::rgb(180, 20, 20),
    ))
    ->addRow(['Typ', 'Padding', 'Kommentar'], header: true)
    ->addRow([
        'Default',
        'Links/Rechts 5 mm, Oben/Unten 2 mm',
        "Standard fuer alle Zellen\nmit etwas Luft seitlich.",
    ])
    ->addRow([
        'Override',
        new TableCell(
            'Links 10 mm, Oben 1 mm, Unten 4 mm',
            style: new CellStyle(
                padding: TablePadding::only(
                    top: Units::mm(1),
                    right: Units::mm(3),
                    bottom: Units::mm(4),
                    left: Units::mm(10),
                ),
            ),
        ),
        new TableCell(
            "Diese Zelle verwendet eigenes Padding\nund wirkt dadurch deutlich anders.",
            style: new CellStyle(
                fillColor: Color::gray(0.96),
                padding: TablePadding::only(
                    top: Units::mm(1),
                    right: Units::mm(3),
                    bottom: Units::mm(4),
                    left: Units::mm(10),
                ),
            ),
        ),
    ]);

$longTablePage = $document->addPage(PageSize::A4());
$longTablePage->textFrame(Units::mm(20), Units::mm(265), Units::mm(170), Units::mm(20))
    ->heading('Long Table Demo', 'NotoSans-Regular', 16, 'H1')
    ->paragraph(
        'Ein einfaches Beispiel fuer eine lange Tabelle mit wiederholtem Header.',
        'NotoSans-Regular',
        11,
        'P',
    );

$longTable = $longTablePage->addTable(
    Units::mm(20),
    Units::mm(225),
    Units::mm(170),
    [
        Units::mm(18),
        Units::mm(72),
        Units::mm(32),
        Units::mm(48),
    ],
    Units::mm(20),
)
    ->font('NotoSans-Regular', 10)
    ->style(new TableStyle(
        padding: TablePadding::all(Units::mm(2)),
        verticalAlign: VerticalAlign::MIDDLE,
    ))
    ->headerStyle(new RowStyle(
        fillColor: Color::gray(0.92),
        textColor: Color::rgb(180, 20, 20),
    ))
    ->addRow(['#', 'Eintrag', 'Status', 'Kommentar'], header: true);

$longTable->addRow([
    new TableCell(
        'Zwischenuebersicht',
        colspan: 4,
        style: new CellStyle(
            horizontalAlign: HorizontalAlign::CENTER,
            fillColor: Color::gray(0.95),
        ),
    ),
]);

for ($index = 1; $index <= 36; $index++) {
    if ($index === 12) {
        $longTable->addRow([
            new TableCell(
                'Gruppe A',
                rowspan: 2,
                style: new CellStyle(
                    horizontalAlign: HorizontalAlign::CENTER,
                    verticalAlign: VerticalAlign::MIDDLE,
                    fillColor: Color::gray(0.96),
                ),
            ),
            'Eintrag 12',
            new TableCell(
                'Aktiv',
                style: new CellStyle(
                    horizontalAlign: HorizontalAlign::CENTER,
                    verticalAlign: VerticalAlign::TOP,
                ),
            ),
            "Kommentar 12\nMitte",
        ]);

        $longTable->addRow([
            new TableCell('Eintrag 13', style: new CellStyle(horizontalAlign: HorizontalAlign::RIGHT)),
            new TableCell(
                'Offen',
                style: new CellStyle(
                    horizontalAlign: HorizontalAlign::CENTER,
                    verticalAlign: VerticalAlign::BOTTOM,
                ),
            ),
            "Kommentar 13\nUnten",
        ]);

        continue;
    }

    if ($index === 13) {
        continue;
    }

    $longTable->addRow([
        (string) $index,
        'Eintrag ' . $index,
        new TableCell(
            $index % 2 === 0 ? 'Aktiv' : 'Offen',
            style: new CellStyle(horizontalAlign: HorizontalAlign::CENTER),
        ),
        'Kommentar ' . $index,
    ]);
}

$bulletPage = $document->addPage(PageSize::A4());
$bulletPage->textFrame(Units::mm(20), Units::mm(265), Units::mm(170), Units::mm(20))
    ->heading('Bullet List Demo', 'NotoSans-Regular', 16, 'H1')
    ->paragraph(
        'Die Liste verwendet Hanging Indent, automatischen Umbruch und gemischte Textsegmente.',
        'NotoSans-Regular',
        11,
        'P',
    )
    ->bulletList(
        [
            'Feste Einzuege fuer Bullet und Textblock.',
            [
                new TextSegment('Rich Text', bold: true),
                new TextSegment(' mit Farben, Underline und weiteren Inline-Stilen innerhalb eines Listenpunkts.'),
            ],
            [
                new TextSegment('Dokumentation', color: Color::rgb(0, 0, 255), link: 'https://example.com/docs', underline: true),
                new TextSegment(' direkt aus einem Listeneintrag verlinken.'),
            ],
            'Auch laengere Listenpunkte umbrechen automatisch auf die naechste Zeile und bleiben dabei sauber unter dem Textblock eingerueckt.',
        ],
        'NotoSans-Regular',
        12,
        bulletType: BulletType::DISC,
        bulletColor: Color::rgb(180, 20, 20),
    );

$numberedPage = $document->addPage(PageSize::A4());
$numberedPage->textFrame(Units::mm(20), Units::mm(265), Units::mm(170), Units::mm(20))
    ->heading('Numbered List Demo', 'NotoSans-Regular', 16, 'H1')
    ->paragraph(
        'Nummerierte Listen verwenden denselben Flow wie Bullet-Listen, aber mit laufender Nummerierung.',
        'NotoSans-Regular',
        11,
        'P',
    )
    ->numberedList(
        [
            'Projekt initialisieren und Fonts registrieren.',
            [
                new TextSegment('Tabellen', bold: true),
                new TextSegment(' und Listen in den Dokumentfluss integrieren.'),
            ],
            [
                new TextSegment('Dokumentation', color: Color::rgb(0, 0, 255), link: 'https://example.com/docs', underline: true),
                new TextSegment(' nach jedem Feature aktualisieren.'),
            ],
            'Zum Schluss Tests und statische Analyse laufen lassen, damit der Stand belastbar bleibt.',
        ],
        'NotoSans-Regular',
        12,
        numberColor: Color::rgb(180, 20, 20),
        startAt: 3,
    );

//$coverPage = $document->addPage(\Kalle\Pdf\Layout\PageSize::A4());
//$coverFrame = $coverPage->textFrame(20, 265, 170);
//$coverFrame
//    ->heading('Kalle PDF Demo', 'NotoSans-Regular', 24, 'H1')
//    ->paragraph('Aktueller Stand der Library', 'NotoSans-Regular', 12)
//    ->paragraph('Dieses Dokument zeigt, was im Moment bereits funktioniert:', 'NotoSans-Regular', 9)
//    ->paragraph('- mehrere Seiten', 'NotoSans-Regular', 9)
//    ->paragraph('- verschiedene registrierte Fonts', 'NotoSans-Regular', 9)
//    ->paragraph('- einfache Metadaten wie Titel, Autor und Keywords', 'NotoSans-Regular', 9)
//    ->paragraph('- strukturierte Text-Tags wie H1 und P', 'NotoSans-Regular', 9)
//    ->spacer(4)
//    ->paragraph('Naechster Ausbauschritt waere z. B. Bilder, Linien oder Tabellen.', 'NotoSerif-Regular', 11);
//
//$comparisonPage = $document->addPage(\Kalle\Pdf\Layout\PageSize::A4());
//$comparisonFrame = $comparisonPage->textFrame(20, 265, 110);
//$comparisonFrame
//    ->heading('Seite 2', 'NotoSans-Regular', 18, 'H1')
//    ->paragraph('Fonts im direkten Vergleich', 'NotoSans-Regular', 10)
//    ->paragraph('Sans: klar und technisch.', 'NotoSans-Regular', 11)
//    ->paragraph('Serif: klassischer und etwas formeller.', 'NotoSerif-Regular', 12)
//    ->paragraph('Alle Inhalte werden aktuell als Text-Elemente auf die Seite gesetzt.', 'NotoSans-Regular', 9)
//    ->paragraph('Damit eignet sich das Beispiel gut als Ausgangspunkt fuer weitere PDF-Features.', 'NotoSans-Regular', 9);
//
//$unicodePage = $document->addPage(\Kalle\Pdf\Layout\PageSize::A4());
//$unicodePage
//    ->addText('Unicode Font Demo', 20, 265, 'NotoSans-Regular', 18, 'H1')
//    ->addText('Die naechste Zeile verwendet den registrierten UnicodeFont:', 20, 245, 'NotoSans-Regular', 10, 'P')
//    ->addText('漢字とカタカナ', 20, 225, 'NotoSansCJKsc-Regular', 14, 'P')
//    ->addText('Noch ein Beispiel: Привет мир', 20, 205, 'NotoSansCJKsc-Regular', 12, 'P')
//    ->addText('Und gemischt: PDF 1.4 - 你好 - مرحبا', 20, 185, 'NotoSansCJKsc-Regular', 12, 'P');

$pdfContent = $document->render();
$outputPath = 'output_' . new DateTime()->format('Y-m-d-H-i-s') . '.pdf';

file_put_contents($outputPath, $pdfContent);
