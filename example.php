<?php

declare(strict_types=1);

use Kalle\Pdf\Document\AnnotationBorderStyle;
use Kalle\Pdf\Document\Document;
use Kalle\Pdf\Document\FormFieldFlags;
use Kalle\Pdf\Document\GoToAction;
use Kalle\Pdf\Document\GoToRemoteAction;
use Kalle\Pdf\Document\HideAction;
use Kalle\Pdf\Document\ImportDataAction;
use Kalle\Pdf\Document\JavaScriptAction;
use Kalle\Pdf\Document\LaunchAction;
use Kalle\Pdf\Document\LineEndingStyle;
use Kalle\Pdf\Document\NamedAction;
use Kalle\Pdf\Document\Page;
use Kalle\Pdf\Document\ResetFormAction;
use Kalle\Pdf\Document\SetOcgStateAction;
use Kalle\Pdf\Document\SubmitFormAction;
use Kalle\Pdf\Document\Table\Style\CellStyle;
use Kalle\Pdf\Document\Table\Style\HeaderStyle;
use Kalle\Pdf\Document\Table\Style\RowStyle;
use Kalle\Pdf\Document\Table\Style\TableBorder;
use Kalle\Pdf\Document\Table\Style\TablePadding;
use Kalle\Pdf\Document\Table\Style\TableStyle;
use Kalle\Pdf\Document\Table\TableCell;
use Kalle\Pdf\Document\TextSegment;
use Kalle\Pdf\Document\ThreadAction;
use Kalle\Pdf\Document\UriAction;
use Kalle\Pdf\Element\Image;
use Kalle\Pdf\Graphics\Color;
use Kalle\Pdf\Graphics\Opacity;
use Kalle\Pdf\Layout\BulletType;
use Kalle\Pdf\Layout\HorizontalAlign;
use Kalle\Pdf\Layout\PageSize;
use Kalle\Pdf\Layout\TableOfContentsPosition;
use Kalle\Pdf\Layout\TextOverflow;
use Kalle\Pdf\Layout\Units;
use Kalle\Pdf\Layout\VerticalAlign;
use Kalle\Pdf\Styles\BadgeStyle;
use Kalle\Pdf\Styles\CalloutStyle;
use Kalle\Pdf\Styles\PanelStyle;

require 'vendor/autoload.php';

$scriptStart = microtime(true);

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
    ->registerFont('Helvetica')
    ->registerFont('NotoSans-Regular')
    ->registerFont('NotoSans-Bold')
    ->registerFont('NotoSans-Italic')
    ->registerFont('NotoSans-BoldItalic')
    ->registerFont('NotoSerif-Regular')
    ->registerFont('NotoSansMono-Regular')
    ->registerFont('NotoSansCJKsc-Regular');

$document
    ->addAttachmentFromFile('README.md', description: 'Projekt-README als eingebettete Datei', mimeType: 'text/markdown')
    ->addAttachment(
        'demo-note.txt',
        "Diese Datei ist als Dokument-Anhang eingebettet.\nSie kann im PDF-Viewer als Attachment erscheinen.\n",
        'Kleine Demo-Datei fuer Attachments',
        'text/plain',
    );

$document
    ->addHeader(static function (Page $page, int $pageNumber): void {
        $page->addText(
            "Kalle PDF Demo - Seite $pageNumber",
            Units::mm(20),
            $page->getHeight() - Units::mm(10),
            'Helvetica',
            9,
            color: Color::gray(0.35),
        );
        $page->addLine(
            Units::mm(20),
            $page->getHeight() - Units::mm(12),
            $page->getWidth() - Units::mm(20),
            $page->getHeight() - Units::mm(12),
            0.5,
            Color::gray(0.75),
        );
    })
    ->addFooter(static function (Page $page): void {
        $page->addLine(
            Units::mm(20),
            Units::mm(12),
            $page->getWidth() - Units::mm(20),
            Units::mm(12),
            0.5,
            Color::gray(0.75),
        );
    })
    ->addPageNumbers(
        Units::mm(20),
        Units::mm(7),
        'Helvetica',
        9,
        'Seite {{page}} von {{pages}}',
    );

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
    ->headerStyle(new HeaderStyle(
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
    ->headerStyle(new HeaderStyle(
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
    ->headerStyle(new HeaderStyle(
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
        'Ein Beispiel fuer eine lange Tabelle mit wiederholtem Header und einem gezielten Rowspan-Split ab Eintrag 24.',
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
    ->headerStyle(new HeaderStyle(
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
    if ($index === 24) {
        $longTable->addRow([
            '24',
            new TableCell(
                "Rowspan ab 24 mit bewusst langem Text.\n"
                . "Der Inhalt soll ueber den Seitenwechsel sichtbar weiterlaufen,\n"
                . 'damit Border, Textfluss und Vertikalverhalten leichter pruefbar sind.',
                rowspan: 4,
                style: new CellStyle(
                    verticalAlign: VerticalAlign::TOP,
                    fillColor: Color::gray(0.96),
                ),
            ),
            new TableCell(
                'Aktiv',
                style: new CellStyle(
                    horizontalAlign: HorizontalAlign::CENTER,
                ),
            ),
            'Kommentar 24',
        ]);

        $longTable->addRow([
            '25',
            new TableCell(
                'Offen',
                style: new CellStyle(
                    horizontalAlign: HorizontalAlign::CENTER,
                ),
            ),
            'Kommentar 25',
        ]);

        $longTable->addRow([
            '26',
            new TableCell(
                'Aktiv',
                style: new CellStyle(
                    horizontalAlign: HorizontalAlign::CENTER,
                ),
            ),
            'Kommentar 26',
        ]);

        $longTable->addRow([
            '27',
            new TableCell(
                'Offen',
                style: new CellStyle(
                    horizontalAlign: HorizontalAlign::CENTER,
                ),
            ),
            'Kommentar 27',
        ]);

        continue;
    }

    if ($index >= 25 && $index <= 27) {
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

$badgePage = $document->addPage(PageSize::A4());
$badgePage->textFrame(Units::mm(20), Units::mm(265), Units::mm(170), Units::mm(20))
    ->heading('Badge Demo', 'NotoSans-Regular', 16, 'H1')
    ->paragraph(
        'Badges sind kleine Labels fuer Status, Tags oder kurze Hervorhebungen.',
        'NotoSans-Regular',
        11,
        'P',
    );

$badgePage->addBadge('Standard', Units::mm(20), Units::mm(220), 'NotoSans-Regular', 11);
$badgePage->addBadge(
    'Aktiv',
    Units::mm(55),
    Units::mm(220),
    'NotoSans-Regular',
    11,
    new BadgeStyle(
        cornerRadius: Units::mm(2),
        fillColor: Color::rgb(225, 240, 225),
        textColor: Color::rgb(20, 110, 50),
        borderWidth: 1.0,
        borderColor: Color::rgb(20, 110, 50),
    ),
);
$badgePage->addBadge(
    'Beta',
    Units::mm(90),
    Units::mm(220),
    'NotoSans-Regular',
    11,
    new BadgeStyle(
        cornerRadius: Units::mm(2),
        fillColor: Color::rgb(230, 235, 250),
        textColor: Color::rgb(40, 70, 140),
        borderWidth: 1.0,
        borderColor: Color::rgb(40, 70, 140),
        opacity: Opacity::both(0.7),
    ),
);
$badgePage->addBadge(
    'Docs',
    Units::mm(125),
    Units::mm(220),
    'NotoSans-Regular',
    11,
    new BadgeStyle(
        cornerRadius: Units::mm(2),
        fillColor: Color::rgb(245, 245, 245),
        textColor: Color::rgb(0, 0, 255),
        borderWidth: 1.0,
        borderColor: Color::rgb(120, 120, 120),
    ),
    'https://example.com/docs',
);
$badgePage->addBadge(
    'Entwurf',
    Units::mm(20),
    Units::mm(195),
    'NotoSans-Regular',
    14,
    new BadgeStyle(
        paddingHorizontal: Units::mm(4),
        paddingVertical: Units::mm(2),
        cornerRadius: Units::mm(3),
        fillColor: Color::rgb(255, 240, 220),
        textColor: Color::rgb(160, 90, 20),
        borderWidth: 1.5,
        borderColor: Color::rgb(160, 90, 20),
    ),
);

$panelPage = $document->addPage(PageSize::A4());
$panelPage->textFrame(Units::mm(20), Units::mm(265), Units::mm(170), Units::mm(20))
    ->heading('Panel Demo', 'NotoSans-Regular', 16, 'H1')
    ->paragraph(
        'Panels kombinieren Rahmen, Hintergrund, Titel und Text zu einer kompakten Hinweis- oder Infobox.',
        'NotoSans-Regular',
        11,
        'P',
    );

$panelPage->addText(
    text: 'Zur Table Demo springen',
    x: Units::mm(20),
    y: Units::mm(240),
    baseFont: 'NotoSans-Regular',
    size: 11,
    color: Color::rgb(0, 0, 255),
    underline: true,
    link: '#table-demo',
);

$panelPage->addPanel(
    'Dieses Panel zeigt die Standardwerte mit dezenter Hinterlegung und einer einfachen Titelzeile.',
    Units::mm(20),
    Units::mm(180),
    Units::mm(80),
    Units::mm(55),
    'Hinweis',
    'NotoSans-Regular',
);

$panelPage->addPanel(
    [
        new TextSegment('Mehrzeiliger Inhalt mit '),
        new TextSegment('Rich Text', bold: true),
        new TextSegment(' und staerkerer visueller Betonung.'),
    ],
    Units::mm(110),
    Units::mm(180),
    Units::mm(80),
    Units::mm(55),
    'Status',
    'NotoSans-Regular',
    new PanelStyle(
        cornerRadius: Units::mm(3),
        fillColor: Color::gray(0.92),
        titleColor: Color::rgb(180, 20, 20),
        bodyColor: Color::gray(0.2),
        borderWidth: 1.2,
        borderColor: Color::rgb(180, 20, 20),
    ),
);

$panelPage->addPanel(
    'Dieses Panel ist als komplette Box verlinkt und eignet sich fuer kompakte Navigation oder Callouts.',
    Units::mm(20),
    Units::mm(110),
    Units::mm(170),
    Units::mm(45),
    'Docs',
    'NotoSans-Regular',
    new PanelStyle(
        cornerRadius: Units::mm(2),
        fillColor: Color::gray(0.95),
        borderWidth: 1.0,
        borderColor: Color::rgb(0, 0, 255),
        titleColor: Color::rgb(0, 0, 255),
    ),
    link: 'https://example.com',
);

$calloutPage = $document->addPage(PageSize::A4());
$calloutPage->textFrame(Units::mm(20), Units::mm(265), Units::mm(170), Units::mm(20))
    ->heading('Callout Demo', 'NotoSans-Regular', 16, 'H1')
    ->paragraph(
        'Callouts erweitern Panels um eine kleine Pointer-Spitze und eignen sich fuer Hinweise, Annotationen oder Diagramm-Beschriftungen.',
        'NotoSans-Regular',
        11,
        'P',
    );

$calloutPage->addCallout(
    'Dieses Callout zeigt die Standardwerte mit Pointer nach unten.',
    Units::mm(20),
    Units::mm(180),
    Units::mm(80),
    Units::mm(45),
    Units::mm(55),
    Units::mm(165),
    'Hinweis',
    'NotoSans-Regular',
);

$calloutPage->addCallout(
    [
        new TextSegment('Ein Callout mit '),
        new TextSegment('Rich Text', bold: true),
        new TextSegment(' und Pointer nach rechts.'),
    ],
    Units::mm(25),
    Units::mm(105),
    Units::mm(80),
    Units::mm(45),
    Units::mm(115),
    Units::mm(125),
    'Kommentar',
    'NotoSans-Regular',
    new CalloutStyle(
        panelStyle: new PanelStyle(
            cornerRadius: Units::mm(3),
            fillColor: Color::gray(0.92),
            titleColor: Color::rgb(180, 20, 20),
            bodyColor: Color::gray(0.2),
            borderWidth: 1.2,
            borderColor: Color::rgb(180, 20, 20),
        ),
        pointerBaseWidth: Units::mm(8),
    ),
);

$calloutPage->addCallout(
    'Auch komplette Callouts koennen verlinkt werden.',
    Units::mm(110),
    Units::mm(105),
    Units::mm(80),
    Units::mm(45),
    Units::mm(150),
    Units::mm(90),
    'Docs',
    'NotoSans-Regular',
    new CalloutStyle(
        panelStyle: new PanelStyle(
            cornerRadius: Units::mm(2),
            fillColor: Color::gray(0.95),
            borderWidth: 1.0,
            borderColor: Color::rgb(0, 0, 255),
            titleColor: Color::rgb(0, 0, 255),
        ),
        pointerBaseWidth: Units::mm(7),
    ),
    link: 'https://example.com',
);

$backgroundPage = $document->addPage(PageSize::A4());
$backgroundPage->addImage(
    Image::fromFile('assets/images/geometric-background.png'),
    0,
    0,
    $backgroundPage->getWidth(),
    $backgroundPage->getHeight(),
);
$backgroundPage->addPanel(
    [
        new TextSegment('Diese Seite zeigt ein vollflaechig eingebettetes Hintergrundbild. '),
        new TextSegment('Darueber liegt normaler PDF-Text', bold: true),
        new TextSegment(' in einer halbtransparenten Box, damit Titel und Fliesstext lesbar bleiben.'),
    ],
    Units::mm(20),
    Units::mm(175),
    Units::mm(120),
    Units::mm(55),
    'Background Demo',
    'NotoSans-Regular',
    new PanelStyle(
        cornerRadius: Units::mm(3),
        fillColor: Color::gray(1.0),
        titleColor: Color::rgb(30, 30, 30),
        bodyColor: Color::gray(0.15),
        borderWidth: 1.0,
        borderColor: Color::gray(0.8),
        opacity: Opacity::both(0.82),
    ),
);
$backgroundPage->addBadge(
    'Full Page Image',
    Units::mm(20),
    Units::mm(240),
    'NotoSans-Regular',
    11,
    new BadgeStyle(
        paddingHorizontal: Units::mm(3),
        paddingVertical: Units::mm(1.5),
        cornerRadius: Units::mm(2),
        fillColor: Color::rgb(245, 245, 245),
        textColor: Color::rgb(50, 50, 50),
        borderWidth: 1.0,
        borderColor: Color::gray(0.75),
        opacity: Opacity::both(0.9),
    ),
);

$attachmentPage = $document->addPage(PageSize::A4());
$attachmentPage->textFrame(Units::mm(20), Units::mm(265), Units::mm(170), Units::mm(20))
    ->heading('Attachment Demo', 'NotoSans-Regular', 16, 'H1')
    ->paragraph(
        'Dieses Dokument enthaelt eingebettete Dateien ueber den EmbeddedFiles-Name-Tree im Catalog.',
        'NotoSans-Regular',
        11,
        'P',
    );

$attachmentPage->addPanel(
    "Eingebettet sind:\n- README.md\n- demo-note.txt",
    Units::mm(20),
    Units::mm(185),
    Units::mm(80),
    Units::mm(42),
    'Anhaenge',
    'NotoSans-Regular',
    new PanelStyle(
        cornerRadius: Units::mm(2),
        fillColor: Color::gray(0.96),
        borderWidth: 1.0,
        borderColor: Color::gray(0.75),
    ),
);

$attachmentPage->addPanel(
    'Je nach PDF-Viewer erscheinen die eingebetteten Dateien in einer Attachments- oder Dateianhaenge-Seitenleiste.',
    Units::mm(110),
    Units::mm(185),
    Units::mm(80),
    Units::mm(42),
    'Viewer',
    'NotoSans-Regular',
    new PanelStyle(
        cornerRadius: Units::mm(2),
        fillColor: Color::rgb(245, 248, 255),
        borderWidth: 1.0,
        borderColor: Color::rgb(120, 140, 190),
        titleColor: Color::rgb(60, 80, 140),
    ),
);

$attachmentPage->addText(
    text: 'Zur Action Demo springen',
    x: Units::mm(20),
    y: Units::mm(165),
    baseFont: 'NotoSans-Regular',
    size: 11,
    color: Color::rgb(0, 0, 255),
    underline: true,
    link: '#action-demo',
);

$readmeAttachment = $document->getAttachment('README.md');
$noteAttachment = $document->getAttachment('demo-note.txt');

if ($readmeAttachment !== null) {
    $attachmentPage->addFileAttachment(
        Units::mm(25),
        Units::mm(150),
        Units::mm(8),
        Units::mm(8),
        $readmeAttachment,
        'Graph',
        'README.md als eingebettete Datei',
    );
    $attachmentPage->addText('README.md', Units::mm(38), Units::mm(152), 'Helvetica', 11);
}

if ($noteAttachment !== null) {
    $attachmentPage->addFileAttachment(
        Units::mm(25),
        Units::mm(138),
        Units::mm(8),
        Units::mm(8),
        $noteAttachment,
        'Paperclip',
        'demo-note.txt als eingebettete Datei',
    );
    $attachmentPage->addText('demo-note.txt', Units::mm(38), Units::mm(140), 'Helvetica', 11);
}

$notesLayer = $document->addLayer('Notes');
$gridLayer = $document->addLayer('Grid', false);

$actionPage = $document->addPage(PageSize::A4());
$actionPage->textFrame(Units::mm(20), Units::mm(265), Units::mm(170), Units::mm(20))
    ->heading('Action Demo', 'NotoSans-Regular', 16, 'H1')
    ->paragraph(
        'Diese Seite sammelt alle aktuellen Push-Button-Actions auf einen Blick.',
        'NotoSans-Regular',
        11,
        'P',
    );

$actionPage->addPanel(
    'Die Buttons unten decken Submit, Reset, JavaScript, Navigation, Datei-, URI- und Layer-Actions ab.',
    Units::mm(20),
    Units::mm(210),
    Units::mm(170),
    Units::mm(28),
    'Uebersicht',
    'NotoSans-Regular',
    new PanelStyle(
        cornerRadius: Units::mm(2),
        fillColor: Color::gray(0.96),
        borderWidth: 1.0,
        borderColor: Color::gray(0.75),
    ),
);

$buttonWidth = Units::mm(38);
$buttonHeight = Units::mm(9);
$firstColumnX = Units::mm(20);
$secondColumnX = Units::mm(65);
$thirdColumnX = Units::mm(110);
$fourthColumnX = Units::mm(155);

$row1Y = Units::mm(185);
$row2Y = Units::mm(172);
$row3Y = Units::mm(159);

$actionPage->addPushButton(
    'demo_submit',
    'Submit',
    $firstColumnX,
    $row1Y,
    $buttonWidth,
    $buttonHeight,
    action: new SubmitFormAction('https://example.com/form-submit'),
);
$actionPage->addPushButton(
    'demo_reset',
    'Reset',
    $secondColumnX,
    $row1Y,
    $buttonWidth,
    $buttonHeight,
    action: new ResetFormAction(),
);
$actionPage->addPushButton(
    'demo_js',
    'JavaScript',
    $thirdColumnX,
    $row1Y,
    $buttonWidth,
    $buttonHeight,
    action: new JavaScriptAction("app.alert('Action Demo');"),
);
$actionPage->addPushButton(
    'demo_named',
    'PrevPage',
    $fourthColumnX,
    $row1Y,
    $buttonWidth,
    $buttonHeight,
    action: new NamedAction('PrevPage'),
);

$actionPage->addPushButton(
    'demo_goto',
    'GoTo',
    $firstColumnX,
    $row2Y,
    $buttonWidth,
    $buttonHeight,
    action: new GoToAction('table-demo'),
);
$actionPage->addPushButton(
    'demo_gotor',
    'GoToR',
    $secondColumnX,
    $row2Y,
    $buttonWidth,
    $buttonHeight,
    action: new GoToRemoteAction('guide.pdf', 'chapter-1'),
);
$actionPage->addPushButton(
    'demo_launch',
    'Launch',
    $thirdColumnX,
    $row2Y,
    $buttonWidth,
    $buttonHeight,
    action: new LaunchAction('guide.pdf'),
);
$actionPage->addPushButton(
    'demo_uri',
    'URI',
    $fourthColumnX,
    $row2Y,
    $buttonWidth,
    $buttonHeight,
    action: new UriAction('https://example.com'),
);

$actionPage->addPushButton(
    'demo_hide',
    'Hide',
    $firstColumnX,
    $row3Y,
    $buttonWidth,
    $buttonHeight,
    action: new HideAction('notes_panel'),
);
$actionPage->addPushButton(
    'demo_import',
    'Import',
    $secondColumnX,
    $row3Y,
    $buttonWidth,
    $buttonHeight,
    action: new ImportDataAction('form-data.fdf'),
);
$actionPage->addPushButton(
    'demo_ocg',
    'Layer',
    $thirdColumnX,
    $row3Y,
    $buttonWidth,
    $buttonHeight,
    action: new SetOcgStateAction(['Toggle', $notesLayer], false),
);
$actionPage->addPushButton(
    'demo_thread',
    'Thread',
    $fourthColumnX,
    $row3Y,
    $buttonWidth,
    $buttonHeight,
    action: new ThreadAction('article-1', 'threads.pdf'),
);

$annotationPage = $document->addPage(PageSize::A4());
$annotationPage->textFrame(Units::mm(20), Units::mm(265), Units::mm(170), Units::mm(20))
    ->heading('Annotation Demo', 'NotoSans-Regular', 16, 'H1')
    ->paragraph(
        'Diese Seite zeigt die aktuellen nicht-formularbasierten Viewer-Annotationen.',
        'NotoSans-Regular',
        11,
        'P',
    );

$annotationPage->addPanel(
    'Die Anmerkungen unten werden als echte PDF-Annotationen gespeichert und je nach Viewer direkt visualisiert.',
    Units::mm(20),
    Units::mm(215),
    Units::mm(170),
    Units::mm(24),
    'Uebersicht',
    'NotoSans-Regular',
    new PanelStyle(
        cornerRadius: Units::mm(2),
        fillColor: Color::gray(0.96),
        borderWidth: 1.0,
        borderColor: Color::gray(0.75),
    ),
);

$annotationPage->addText('Kommentar und FreeText:', Units::mm(20), Units::mm(192), 'Helvetica', 11);
$annotationPage->addTextAnnotation(
    Units::mm(20),
    Units::mm(175),
    Units::mm(8),
    Units::mm(8),
    'Kurzer Kommentar als Sticky Note',
    'QA',
    'Comment',
    true,
);
$annotationPage->addFreeTextAnnotation(
    Units::mm(35),
    Units::mm(168),
    Units::mm(70),
    Units::mm(18),
    'Direkter Hinweis auf der Seite',
    'Helvetica',
    11,
    Color::rgb(180, 20, 20),
    Color::gray(0.5),
    Color::gray(0.95),
    'QA',
);

$annotationPage->addText('Markup-Annotationen:', Units::mm(20), Units::mm(150), 'Helvetica', 11);
$annotationPage->addText('Markierter Beispielsatz fuer Highlight, Underline, StrikeOut und Squiggly.', Units::mm(20), Units::mm(136), 'Helvetica', 11);
$annotationPage->addHighlightAnnotation(
    Units::mm(20),
    Units::mm(132),
    Units::mm(65),
    Units::mm(6),
    Color::rgb(255, 255, 0),
    'Highlight',
    'QA',
);
$annotationPage->addUnderlineAnnotation(
    Units::mm(88),
    Units::mm(132),
    Units::mm(28),
    Units::mm(6),
    Color::rgb(0, 0, 255),
    'Underline',
    'QA',
);
$annotationPage->addStrikeOutAnnotation(
    Units::mm(119),
    Units::mm(132),
    Units::mm(28),
    Units::mm(6),
    Color::rgb(255, 0, 0),
    'StrikeOut',
    'QA',
);
$annotationPage->addSquigglyAnnotation(
    Units::mm(150),
    Units::mm(132),
    Units::mm(40),
    Units::mm(6),
    Color::rgb(255, 0, 255),
    'Squiggly',
    'QA',
);

$annotationPage->addText('Stamp und FileAttachment:', Units::mm(20), Units::mm(110), 'Helvetica', 11);
$annotationPage->addStampAnnotation(
    Units::mm(20),
    Units::mm(92),
    Units::mm(35),
    Units::mm(14),
    'Approved',
    Color::rgb(0, 128, 0),
    'Freigegeben',
    'QA',
);

$annotationAttachment = $document->getAttachment('demo-note.txt');
if ($annotationAttachment !== null) {
    $annotationPage->addFileAttachment(
        Units::mm(65),
        Units::mm(92),
        Units::mm(8),
        Units::mm(8),
        $annotationAttachment,
        'PushPin',
        'demo-note.txt als sichtbarer Dateianhang',
    );
    $annotationPage->addText('demo-note.txt', Units::mm(78), Units::mm(94), 'Helvetica', 11);
}

$annotationPage->addText('Square, Circle und Ink:', Units::mm(20), Units::mm(72), 'Helvetica', 11);
$annotationPage->addSquareAnnotation(
    Units::mm(20),
    Units::mm(48),
    Units::mm(24),
    Units::mm(14),
    Color::rgb(255, 0, 0),
    Color::gray(0.92),
    'Square',
    'QA',
    AnnotationBorderStyle::solid(2.0),
);
$annotationPage->addCircleAnnotation(
    Units::mm(52),
    Units::mm(48),
    Units::mm(24),
    Units::mm(14),
    Color::rgb(0, 0, 255),
    Color::gray(0.92),
    'Circle',
    'QA',
    AnnotationBorderStyle::dashed(1.5, [2.0, 1.0]),
);
$annotationPage->addInkAnnotation(
    Units::mm(85),
    Units::mm(45),
    Units::mm(45),
    Units::mm(20),
    [
        [
            [Units::mm(88), Units::mm(50)],
            [Units::mm(95), Units::mm(58)],
            [Units::mm(102), Units::mm(48)],
            [Units::mm(110), Units::mm(57)],
            [Units::mm(118), Units::mm(49)],
        ],
    ],
    Color::rgb(0, 0, 0),
    'Ink',
    'QA',
);
$annotationPage->addText('Line, PolyLine und Polygon:', Units::mm(20), Units::mm(40), 'Helvetica', 11);
$annotationPage->addLineAnnotation(
    Units::mm(20),
    Units::mm(28),
    Units::mm(48),
    Units::mm(20),
    Color::rgb(220, 40, 40),
    'Line',
    'QA',
    LineEndingStyle::OPEN_ARROW,
    LineEndingStyle::CLOSED_ARROW,
    'Messlinie',
    AnnotationBorderStyle::dashed(2.0, [4.0, 2.0]),
);
$lineAnnotation = $annotationPage->getAnnotations()[count($annotationPage->getAnnotations()) - 1];
$annotationPage->addPopupAnnotation($lineAnnotation, Units::mm(18), Units::mm(2), Units::mm(34), Units::mm(14), true);
$annotationPage->addPolyLineAnnotation(
    [
        [Units::mm(58), Units::mm(22)],
        [Units::mm(66), Units::mm(30)],
        [Units::mm(74), Units::mm(20)],
        [Units::mm(82), Units::mm(28)],
    ],
    Color::rgb(30, 90, 210),
    'PolyLine',
    'QA',
    LineEndingStyle::CIRCLE,
    LineEndingStyle::SLASH,
    'Korrekturpfad',
    AnnotationBorderStyle::solid(2.5),
);
$polyLineAnnotation = $annotationPage->getAnnotations()[count($annotationPage->getAnnotations()) - 1];
$annotationPage->addPopupAnnotation($polyLineAnnotation, Units::mm(58), Units::mm(2), Units::mm(34), Units::mm(14));
$annotationPage->addPolygonAnnotation(
    [
        [Units::mm(95), Units::mm(20)],
        [Units::mm(106), Units::mm(32)],
        [Units::mm(118), Units::mm(24)],
        [Units::mm(112), Units::mm(16)],
    ],
    Color::rgb(20, 130, 60),
    Color::gray(0.9),
    'Polygon',
    'QA',
    'Flaechenhinweis',
    AnnotationBorderStyle::dashed(),
);
$polygonAnnotation = $annotationPage->getAnnotations()[count($annotationPage->getAnnotations()) - 1];
$annotationPage->addPopupAnnotation($polygonAnnotation, Units::mm(98), Units::mm(2), Units::mm(34), Units::mm(14));
$annotationPage->addCaretAnnotation(
    Units::mm(128),
    Units::mm(18),
    Units::mm(10),
    Units::mm(12),
    'Einfuemarke',
    'QA',
    'P',
);
$annotationPage->addText(
    text: 'Die Geometrie-Annotationen unten tragen Betreff und Popup.',
    x: Units::mm(20),
    y: Units::mm(8),
    baseFont: 'Helvetica',
    size: 9,
    color: Color::gray(0.35),
);

$annotationPage->addText(
    text: 'Zur Layer Demo springen',
    x: Units::mm(20),
    y: Units::mm(12),
    baseFont: 'NotoSans-Regular',
    size: 11,
    color: Color::rgb(0, 0, 255),
    underline: true,
    link: '#layer-demo',
);

$layerPage = $document->addPage(PageSize::A4());
$layerPage->textFrame(Units::mm(20), Units::mm(265), Units::mm(170), Units::mm(20))
    ->heading('Layer Demo', 'NotoSans-Regular', 16, 'H1')
    ->paragraph(
        'Diese Seite zeigt echte OCG-Layer. Hinweise und Raster liegen auf eigenen Ebenen und koennen ueber Buttons geschaltet werden.',
        'NotoSans-Regular',
        11,
        'P',
    );

$layerPage->addPanel(
    'Der Basisinhalt liegt ausserhalb der Layer und bleibt immer sichtbar.',
    Units::mm(20),
    Units::mm(180),
    Units::mm(75),
    Units::mm(35),
    'Basis',
    'NotoSans-Regular',
    new PanelStyle(
        cornerRadius: Units::mm(2),
        fillColor: Color::gray(0.95),
        borderWidth: 1.0,
        borderColor: Color::gray(0.75),
    ),
);

$layerPage->layer($notesLayer, static function (Page $page): void {
    $page->addPanel(
        'Dieser Hinweis liegt auf dem Layer Notes.',
        Units::mm(110),
        Units::mm(180),
        Units::mm(80),
        Units::mm(35),
        'Notes',
        'NotoSans-Regular',
        new PanelStyle(
            cornerRadius: Units::mm(2),
            fillColor: Color::rgb(255, 248, 220),
            titleColor: Color::rgb(160, 90, 20),
            bodyColor: Color::rgb(120, 80, 20),
            borderWidth: 1.0,
            borderColor: Color::rgb(180, 130, 40),
        ),
    );
});

$layerPage->layer($gridLayer, static function (Page $page): void {
    for ($x = 20; $x <= 190; $x += 10) {
        $page->addLine(
            Units::mm((float) $x),
            Units::mm(60),
            Units::mm((float) $x),
            Units::mm(170),
            0.35,
            Color::gray(0.75),
            Opacity::stroke(0.35),
        );
    }

    for ($y = 60; $y <= 170; $y += 10) {
        $page->addLine(
            Units::mm(20),
            Units::mm((float) $y),
            Units::mm(190),
            Units::mm((float) $y),
            0.35,
            Color::gray(0.75),
            Opacity::stroke(0.35),
        );
    }
});

$layerPage->addPushButton(
    'toggle_notes',
    'Notes',
    Units::mm(20),
    Units::mm(145),
    Units::mm(35),
    Units::mm(10),
    action: new SetOcgStateAction(['Toggle', $notesLayer], false),
);

$layerPage->addPushButton(
    'toggle_grid',
    'Grid',
    Units::mm(60),
    Units::mm(145),
    Units::mm(35),
    Units::mm(10),
    action: new SetOcgStateAction(['Toggle', $gridLayer], false),
);

$layerPage->addPushButton(
    'show_all_layers',
    'Alle an',
    Units::mm(100),
    Units::mm(145),
    Units::mm(40),
    Units::mm(10),
    action: new SetOcgStateAction(['ON', $notesLayer, 'ON', $gridLayer], false),
);

$layerPage->addText(
    text: 'Zur Form Demo springen',
    x: Units::mm(20),
    y: Units::mm(130),
    baseFont: 'NotoSans-Regular',
    size: 11,
    color: Color::rgb(0, 0, 255),
    underline: true,
    link: '#form-demo',
);

$formPage = $document->addPage(PageSize::A4());
$formPage->textFrame(Units::mm(20), Units::mm(265), Units::mm(170), Units::mm(20))
    ->heading('Form Demo', 'NotoSans-Regular', 16, 'H1')
    ->paragraph(
        'Diese Seite zeigt die erste Formularstufe mit einfachen Textfeldern ueber ein AcroForm.',
        'NotoSans-Regular',
        11,
        'P',
    );

$formPage->addText('Name', Units::mm(20), Units::mm(225), 'Helvetica', 11);
$formPage->addTextField(
    'customer_name',
    Units::mm(20),
    Units::mm(210),
    Units::mm(70),
    Units::mm(10),
    'Ada Lovelace',
    'Helvetica',
    11,
    defaultValue: 'Grace Hopper',
);

$formPage->addText('E-Mail', Units::mm(20), Units::mm(190), 'Helvetica', 11);
$formPage->addTextField(
    'customer_email',
    Units::mm(20),
    Units::mm(175),
    Units::mm(90),
    Units::mm(10),
    'ada@example.com',
    'Helvetica',
    11,
    false,
    null,
    new FormFieldFlags(required: true),
);

$formPage->addCheckbox(
    'accept_terms',
    Units::mm(20),
    Units::mm(150),
    Units::mm(6),
    true,
);
$formPage->addText(
    'Ich bestaetige die Testdaten fuer dieses Formular.',
    Units::mm(30),
    Units::mm(151),
    'Helvetica',
    11,
);

$formPage->addText('Versand', Units::mm(20), Units::mm(132), 'Helvetica', 11);
$formPage->addRadioButton(
    'delivery',
    'standard',
    Units::mm(20),
    Units::mm(118),
    Units::mm(6),
    true,
);
$formPage->addText(
    'Standard',
    Units::mm(30),
    Units::mm(119),
    'Helvetica',
    11,
);
$formPage->addRadioButton(
    'delivery',
    'express',
    Units::mm(70),
    Units::mm(118),
    Units::mm(6),
    false,
);
$formPage->addText(
    'Express',
    Units::mm(80),
    Units::mm(119),
    'Helvetica',
    11,
);

$formPage->addText('Land', Units::mm(20), Units::mm(100), 'Helvetica', 11);
$formPage->addComboBox(
    'country',
    Units::mm(20),
    Units::mm(85),
    Units::mm(70),
    Units::mm(10),
    [
        'de' => 'Deutschland',
        'at' => 'Oesterreich',
        'ch' => 'Schweiz',
    ],
    'de',
    'Helvetica',
    11,
    defaultValue: 'at',
);

$formPage->addText('Freitext Land', Units::mm(115), Units::mm(100), 'Helvetica', 11);
$formPage->addComboBox(
    'custom_country',
    Units::mm(115),
    Units::mm(85),
    Units::mm(35),
    Units::mm(10),
    [
        'de' => 'Deutschland',
        'at' => 'Oesterreich',
        'ch' => 'Schweiz',
    ],
    'de',
    'Helvetica',
    11,
    flags: new FormFieldFlags(editable: true),
    defaultValue: 'at',
);

$formPage->addText('Notizen', Units::mm(20), Units::mm(68), 'Helvetica', 11);
$formPage->addTextField(
    'notes',
    Units::mm(20),
    Units::mm(42),
    Units::mm(90),
    Units::mm(20),
    "Erste Zeile\nZweite Zeile",
    'Helvetica',
    11,
    true,
);

$formPage->addText('Unterschrift', Units::mm(20), Units::mm(34), 'Helvetica', 11);
$formPage->addSignatureField(
    'approval_signature',
    Units::mm(20),
    Units::mm(12),
    Units::mm(90),
    Units::mm(16),
);

$formPage->addPushButton(
    'save_form',
    'Speichern',
    Units::mm(20),
    Units::mm(2),
    Units::mm(40),
    Units::mm(8),
    action: new SubmitFormAction('https://example.com/form-submit'),
);

$formPage->addPushButton(
    'reset_form',
    'Zuruecksetzen',
    Units::mm(65),
    Units::mm(2),
    Units::mm(45),
    Units::mm(8),
    action: new ResetFormAction(),
);

$formPage->addPushButton(
    'validate_form',
    'Pruefen',
    Units::mm(115),
    Units::mm(2),
    Units::mm(35),
    Units::mm(8),
    action: new JavaScriptAction("app.alert('Formular pruefen');"),
);

$formPage->addPushButton(
    'prev_page',
    'Zurueck',
    Units::mm(155),
    Units::mm(2),
    Units::mm(35),
    Units::mm(8),
    action: new NamedAction('PrevPage'),
);

$formPage->addPushButton(
    'goto_table',
    'Zur Tabelle',
    Units::mm(20),
    Units::mm(22),
    Units::mm(50),
    Units::mm(8),
    action: new GoToAction('table-demo'),
);

$formPage->addText('PIN', Units::mm(155), Units::mm(106), 'Helvetica', 11);
$formPage->addTextField(
    'pin',
    Units::mm(155),
    Units::mm(92),
    Units::mm(35),
    Units::mm(10),
    '1234',
    'Helvetica',
    11,
    false,
    null,
    new FormFieldFlags(password: true),
);

$formPage->addText('Themen', Units::mm(115), Units::mm(88), 'Helvetica', 11);
$formPage->addListBox(
    'topics',
    Units::mm(115),
    Units::mm(52),
    Units::mm(35),
    Units::mm(30),
    [
        'pdf' => 'PDF',
        'forms' => 'Forms',
        'tables' => 'Tables',
    ],
    'forms',
    'Helvetica',
    11,
    defaultValue: 'pdf',
);

$formPage->addText('Mehrfachwahl', Units::mm(155), Units::mm(88), 'Helvetica', 11);
$formPage->addListBox(
    'topic_selection',
    Units::mm(155),
    Units::mm(52),
    Units::mm(35),
    Units::mm(30),
    [
        'pdf' => 'PDF',
        'forms' => 'Forms',
        'tables' => 'Tables',
    ],
    ['pdf', 'forms'],
    'Helvetica',
    11,
    flags: new FormFieldFlags(multiSelect: true),
    defaultValue: ['forms', 'tables'],
);

$formPage->addPanel(
    'Die Felder sind als Widget-Annotationen im PDF hinterlegt und werden ueber ein zentrales AcroForm im Catalog referenziert.',
    Units::mm(155),
    Units::mm(4),
    Units::mm(35),
    Units::mm(40),
    'Technik',
    'NotoSans-Regular',
    new PanelStyle(
        cornerRadius: Units::mm(2),
        fillColor: Color::gray(0.96),
        borderColor: Color::gray(0.75),
        borderWidth: 1.0,
    ),
);

$document
    ->addDestination('table-demo', $tablePage)
    ->addDestination('attachment-demo', $attachmentPage)
    ->addDestination('action-demo', $actionPage)
    ->addDestination('annotation-demo', $annotationPage)
    ->addDestination('layer-demo', $layerPage)
    ->addDestination('form-demo', $formPage)
    ->addOutline('Noto Sans', $sansPage)
    ->addOutline('Noto Serif', $serifPage)
    ->addOutline('Noto Sans Mono', $monoPage)
    ->addOutline('Noto Sans CJK', $cjkPage)
    ->addOutline('Helvetica', $standardPage)
    ->addOutline('Table Demo', $tablePage)
    ->addOutline('Padding Demo', $paddingPage)
    ->addOutline('Long Table Demo', $longTablePage)
    ->addOutline('Bullet List Demo', $bulletPage)
    ->addOutline('Numbered List Demo', $numberedPage)
    ->addOutline('Badge Demo', $badgePage)
    ->addOutline('Panel Demo', $panelPage)
    ->addOutline('Callout Demo', $calloutPage)
    ->addOutline('Background Demo', $backgroundPage)
    ->addOutline('Attachment Demo', $attachmentPage)
    ->addOutline('Action Demo', $actionPage)
    ->addOutline('Annotation Demo', $annotationPage)
    ->addOutline('Layer Demo', $layerPage)
    ->addOutline('Form Demo', $formPage);

$document->addTableOfContents(
    PageSize::A4(),
    title: 'Inhaltsverzeichnis',
    baseFont: 'NotoSans-Regular',
    titleSize: 18,
    entrySize: 11,
    margin: Units::mm(20),
    position: TableOfContentsPosition::START,
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

$renderStart = microtime(true);
$pdfContent = $document->render();
$renderDuration = microtime(true) - $renderStart;
$outputPath = 'output_' . new DateTime()->format('Y-m-d-H-i-s') . '.pdf';

file_put_contents($outputPath, $pdfContent);

$totalDuration = microtime(true) - $scriptStart;

printf("PDF geschrieben: %s\n", $outputPath);
printf("Renderzeit: %.3f s\n", $renderDuration);
printf("Gesamtzeit: %.3f s\n", $totalDuration);
