# Getting Started

Diese Library erzeugt PDFs direkt aus PHP. Der aktuelle Fokus liegt auf einer kleinen, klaren API fuer Dokumente, Seiten, Text, Fonts, Bilder, Formulare und grafische Primitive.

## Voraussetzungen

- PHP `^8.4`
- Erweiterungen `ext-iconv` und `ext-mbstring`
- Composer

## Installation

Abhaengigkeiten installieren:

```bash
composer install
```

## Schnellster Einstieg

Ein lauffaehiges Beispiel liegt in `example.php`.

Ausfuehren:

```bash
php example.php
```

Dabei wird eine PDF-Datei im Projektverzeichnis erzeugt.

Ein zweites Beispiel fuer Verschluesselung liegt in `example2.php`.

Ausfuehren:

```bash
php example2.php
```

Dabei werden vier Testdateien in `var/encryption-examples` erzeugt:

- PDF `1.3` mit `RC4_40`
- PDF `1.4` mit `RC4_128`
- PDF `1.6` mit `AES_128`
- PDF `1.7` mit `AES_256`

Alle Dateien verwenden im Beispiel das Passwort `secret`.

## Erstes Dokument

```php
<?php

declare(strict_types=1);

use Kalle\Pdf\Document\Document;
use Kalle\Pdf\Document\FormFieldFlags;
use Kalle\Pdf\Document\GoToAction;
use Kalle\Pdf\Document\GoToRemoteAction;
use Kalle\Pdf\Document\HideAction;
use Kalle\Pdf\Document\ImportDataAction;
use Kalle\Pdf\Document\JavaScriptAction;
use Kalle\Pdf\Document\LaunchAction;
use Kalle\Pdf\Document\NamedAction;
use Kalle\Pdf\Document\Text\ParagraphOptions;
use Kalle\Pdf\Document\LinkTarget;
use Kalle\Pdf\Document\ResetFormAction;
use Kalle\Pdf\Document\SetOcgStateAction;
use Kalle\Pdf\Document\SubmitFormAction;
use Kalle\Pdf\Document\Text\TextOptions;
use Kalle\Pdf\Document\ThreadAction;
use Kalle\Pdf\Document\UriAction;
use Kalle\Pdf\Layout\PageSize;
use Kalle\Pdf\Layout\BulletType;
use Kalle\Pdf\Document\Style\CellStyle;
use Kalle\Pdf\Document\Style\HeaderStyle;
use Kalle\Pdf\Document\Style\RowStyle;
use Kalle\Pdf\Document\Style\TableBorder;
use Kalle\Pdf\Document\Table\TableCell;
use Kalle\Pdf\Document\Style\TablePadding;
use Kalle\Pdf\Document\Style\TableStyle;
use Kalle\Pdf\Layout\HorizontalAlign;
use Kalle\Pdf\Layout\TextOverflow;
use Kalle\Pdf\Document\Text\TextSegment;
use Kalle\Pdf\Layout\Units;
use Kalle\Pdf\Layout\VerticalAlign;
use Kalle\Pdf\Element\Image;
use Kalle\Pdf\Graphics\Color;
use Kalle\Pdf\Document\AnnotationBorderStyle;
use Kalle\Pdf\Document\LineEndingStyle;
use Kalle\Pdf\Graphics\Opacity;

require 'vendor/autoload.php';

$document = new Document(
    version: 1.4,
    title: 'Hello PDF',
    author: 'Example',
    subject: 'Getting Started',
    language: 'de-DE',
    fontConfig: require __DIR__ . '/../config/fonts.php',
);

$document
    ->addKeyword('example')
    ->registerFont('NotoSans-Regular')
    ->registerFont('NotoSans-Bold')
    ->registerFont('NotoSans-Italic')
    ->registerFont('NotoSans-BoldItalic');

$document
    ->addHeader(static function (\Kalle\Pdf\Document\Page $page, int $pageNumber): void {
        $page->addText("Hello PDF - Seite $pageNumber", new \Kalle\Pdf\Document\Geometry\Position(Units::mm(20), $page->getHeight() - Units::mm(10)), 'Helvetica', 9);
    })
    ->addPageNumbers(Units::mm(20), Units::mm(7), 'Helvetica', 9);

$page = $document->addPage(PageSize::A4());
$frame = $page->createTextFrame(Units::mm(20), Units::mm(265), Units::mm(170), Units::mm(20));

$frame
    ->addHeading(
        'Hallo PDF',
        'NotoSans-Regular',
        24,
        new ParagraphOptions(
            structureTag: \Kalle\Pdf\Document\Text\StructureTag::Heading1,
            color: Color::rgb(220, 20, 60),
        ),
    )
    ->addParagraph(
        [
            TextSegment::underlined('Achtung: ', Color::rgb(220, 20, 60)),
            TextSegment::italic('dieser Absatz zeigt gemischte Textstile, Farben und Opacity. '),
            TextSegment::strikethrough('Dieser Teil ist durchgestrichen.')->withDefaults(
                null,
                Opacity::fill(0.5),
            ),
        ],
        'NotoSans-Regular',
        12,
        new ParagraphOptions(
            structureTag: \Kalle\Pdf\Document\Text\StructureTag::Paragraph,
            align: HorizontalAlign::JUSTIFY,
            maxLines: 3,
            overflow: TextOverflow::ELLIPSIS,
        ),
    );

$page->addLine(
    Units::mm(20),
    Units::mm(235),
    Units::mm(190),
    Units::mm(235),
    1.5,
    Color::rgb(220, 20, 60),
    Opacity::stroke(0.4),
);

$page->addRectangle(
    Units::mm(20),
    Units::mm(200),
    Units::mm(60),
    Units::mm(20),
    1.0,
    Color::rgb(220, 20, 60),
    Color::gray(0.92),
);

$page->addPath()
    ->moveTo(Units::mm(50), Units::mm(200))
    ->lineTo(Units::mm(60), Units::mm(210))
    ->lineTo(Units::mm(50), Units::mm(220))
    ->lineTo(Units::mm(40), Units::mm(210))
    ->close()
    ->fillAndStroke(
        1.0,
        Color::rgb(220, 20, 60),
        Color::gray(0.95),
    );

$page->addCircle(
    Units::mm(80),
    Units::mm(210),
    Units::mm(8),
    1.0,
    Color::rgb(220, 20, 60),
    Color::gray(0.95),
);

$page->addEllipse(
    Units::mm(105),
    Units::mm(210),
    Units::mm(12),
    Units::mm(8),
    1.0,
    Color::rgb(220, 20, 60),
    Color::gray(0.95),
);

$page->addPolygon(
    [
        [Units::mm(125), Units::mm(200)],
        [Units::mm(135), Units::mm(210)],
        [Units::mm(125), Units::mm(220)],
        [Units::mm(115), Units::mm(210)],
    ],
    1.0,
    Color::rgb(220, 20, 60),
    Color::gray(0.95),
);

$page->addArrow(
    Units::mm(145),
    Units::mm(210),
    Units::mm(170),
    Units::mm(210),
    1.5,
    Color::rgb(220, 20, 60),
    Opacity::both(0.4),
);

$page->addStar(
    Units::mm(180),
    Units::mm(210),
    5,
    Units::mm(8),
    Units::mm(4),
    1.0,
    Color::rgb(220, 20, 60),
    Color::gray(0.95),
);

$page->addImage(
    Image::fromFile('assets/images/demo.jpg'),
    Units::mm(100),
    Units::mm(170),
    Units::mm(70),
    Units::mm(46.67),
);

$page->addText(
    text: 'Projektseite',
    position: new Position(Units::mm(20), Units::mm(150)),
    fontName: 'NotoSans-Regular',
    size: 12,
    options: new TextOptions(
        color: Color::rgb(0, 0, 255),
        underline: true,
        link: LinkTarget::externalUrl('https://example.com'),
    ),
);

$page->addBadge(
    'Beta',
    Units::mm(20),
    Units::mm(140),
    'NotoSans-Regular',
    11,
    new \Kalle\Pdf\Document\Style\BadgeStyle(
        cornerRadius: Units::mm(2),
        fillColor: Color::gray(0.9),
        borderWidth: 1.0,
        borderColor: Color::rgb(220, 20, 60),
    ),
);

$page->addPanel(
    'Dieses Panel kombiniert Hintergrund, Titel und Text in einer kompakten Hinweisbox.',
    Units::mm(20),
    Units::mm(90),
    Units::mm(170),
    Units::mm(35),
    'Hinweis',
    'NotoSans-Regular',
);

$page->addCallout(
    'Dieses Callout erweitert ein Panel um eine Pointer-Spitze.',
    Units::mm(20),
    Units::mm(40),
    Units::mm(120),
    Units::mm(30),
    Units::mm(60),
    Units::mm(25),
    'Achtung',
    'NotoSans-Regular',
);

$page->addText('Name', new Position(Units::mm(20), Units::mm(25)), 'Helvetica', 11);
$page->addTextField(
    'customer_name',
    Units::mm(20),
    Units::mm(12),
    Units::mm(70),
    Units::mm(10),
    'Ada Lovelace',
    'Helvetica',
    11,
    defaultValue: 'Grace Hopper',
);

$page->addText('Land', new Position(Units::mm(100), Units::mm(25)), 'Helvetica', 11);
$page->addComboBox(
    'country',
    Units::mm(100),
    Units::mm(12),
    Units::mm(50),
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

$page->addCheckbox(
    'accept_terms',
    Units::mm(160),
    Units::mm(14),
    Units::mm(5),
    true,
);

$page->addText('Akzeptiert', new Position(Units::mm(168), Units::mm(14)), 'Helvetica', 11);
$page->addRadioButton('delivery', 'standard', Units::mm(20), Units::mm(2), Units::mm(5), true);
$page->addRadioButton('delivery', 'express', Units::mm(55), Units::mm(2), Units::mm(5), false);

$page->createTable(
    Units::mm(20),
    Units::mm(135),
    Units::mm(170),
    [Units::mm(22), Units::mm(88), Units::mm(30), Units::mm(30)],
)
    ->font('NotoSans-Regular', 11)
    ->style(new TableStyle(
        padding: TablePadding::all(Units::mm(2.5)),
    ))
    ->headerStyle(new HeaderStyle(
        fillColor: Color::gray(0.92),
        textColor: Color::rgb(220, 20, 60),
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
    ]);

$frame->addBulletList(
    [
        'Feste Einzuege fuer Bullet und Textblock.',
        [
            TextSegment::bold('Rich Text'),
            TextSegment::plain(' innerhalb eines Listenpunkts.'),
        ],
    ],
    'NotoSans-Regular',
    12,
    bulletType: BulletType::DISC,
    bulletColor: Color::rgb(220, 20, 60),
);

$frame->addNumberedList(
    [
        'Erster Schritt',
        [TextSegment::bold('Zweiter'), TextSegment::plain(' Schritt')],
    ],
    'NotoSans-Regular',
    12,
    startAt: 3,
    numberColor: Color::rgb(220, 20, 60),
);

$document
    ->addOutline('Hallo PDF', $page)
    ->addDestination('hello-pdf', $page);

$document->addTableOfContents(
    PageSize::A4(),
    title: 'Inhaltsverzeichnis',
    fontName: 'NotoSans-Regular',
    titleSize: 18,
    entrySize: 11,
    margin: Units::mm(20),
    position: \Kalle\Pdf\Layout\TableOfContentsPosition::START,
);

$pdfContent = $document->render();

file_put_contents('hello.pdf', $pdfContent);
```

## Was im Beispiel passiert

1. `Document` initialisiert das PDF mit Version und Metadaten.
2. `registerFont(...)` registriert eingebettete Schriften aus der Font-Konfiguration.
3. `addHeader(...)`, `addFooter(...)` und `addPageNumbers(...)` registrieren wiederkehrende Seiteninhalte fuer alle neu erzeugten Seiten.
4. `addPage()` erstellt eine neue Seite, standardmaessig im Format A4 in PDF-Points oder explizit ueber `PageSize::A4()`.
5. `createTextFrame()` erzeugt einen Textbereich mit eigener Cursor-Fuehrung.
6. `addHeading()` und `addParagraph()` rendern Text innerhalb dieses Bereichs, inklusive Umbruch und optionalem Seitenwechsel.
7. `addLine(...)`, `addRectangle(...)`, `addRoundedRectangle(...)`, `addPath()`, `addCircle(...)`, `addEllipse(...)`, `addPolygon(...)`, `addArrow(...)`, `addStar(...)` und `addImage(...)` platzieren einfache grafische Inhalte direkt auf der Seite.
8. `TextOptions(link: LinkTarget::...)` kann Text direkt mit einer klickbaren Link-Annotation verbinden.
9. `addBadge(...)` rendert kleine Labels mit Padding, Hintergrund, optionalem Border und optional gerundeten Ecken.
10. `addPanel(...)` rendert einfache Hinweis- und Infoboxen mit Titel, Body, Padding und optional gerundeter Box.
11. `addCallout(...)` rendert Hinweisboxen mit Pointer-Spitze auf Basis von Panel und Pfad-Geometrie.
12. `createTable(...)` erzeugt eine erste Tabellen-API mit festen Spaltenbreiten, Header-Zeilen und automatischer Zeilenhoehe.
13. `addBulletList(...)` rendert Listen mit Hanging Indent und vordefinierten `BulletType`-Varianten.
14. `addNumberedList(...)` rendert nummerierte Listen mit demselben Umbruch- und Paging-Verhalten.
15. `addOutline(...)` registriert Bookmarks fuer die Viewer-Navigation im PDF.
16. `addDestination(...)` registriert benannte interne Sprungziele.
17. `addTextField(...)`, `addCheckbox(...)`, `addRadioButton(...)`, `addComboBox(...)` und `addListBox(...)` decken die erste AcroForm-Stufe ab.
18. `addTableOfContents(...)` erzeugt ein klickbares Inhaltsverzeichnis aus vorhandenen Outlines.
19. `render()` gibt den kompletten PDF-Inhalt als String zurueck.

## Tabellen

Die erste Tabellenstufe ist bewusst pragmatisch gehalten. Sie deckt bereits haeufige Dokumentfaelle ab:

- feste Spaltenbreiten
- Header-Zeilen
- wiederholte Header-Zeilen auf Folgeseiten
- Zellpadding
- `string`, `TextSegment[]` oder `TableCell` als Zelleninhalt
- `CellStyle` als gebuendelter Stil fuer einzelne Zellen
- `colspan`
- `rowspan` innerhalb derselben Seite und ueber Seitenumbrueche mit Split-Fortsetzung
- steuerbare Borders ueber `TableBorder`
- horizontale und vertikale Zell-Ausrichtung
- Default- und Zell-Padding ueber `TablePadding`
- automatische Zeilenhoehe durch Textumbruch
- Seitenwechsel, wenn die naechste Zeile nicht mehr auf die aktuelle Seite passt

Ein kompaktes Beispiel:

```php
$table = $page->createTable(20, 240, 170, [30, 80, 30, 30])
    ->font('NotoSans-Regular', 11)
    ->style(new TableStyle(
        padding: TablePadding::all(6),
    ))
    ->headerStyle(new HeaderStyle(
        fillColor: Color::gray(0.92),
        textColor: Color::rgb(180, 20, 20),
    ))
    ->rowStyle(new RowStyle(
        textColor: Color::gray(0.15),
    ));

$table->addRow(['ID', 'Titel', 'Status', 'Preis'], header: true);
$table->addRow([
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
]);
$table->addRow([
    '2',
    [
        TextSegment::bold('Pro Plan'),
        TextSegment::plain(' mit Umbruch in der Tabellenzelle.'),
    ],
    new TableCell(
        'Beta',
        style: new CellStyle(horizontalAlign: HorizontalAlign::CENTER),
    ),
    '49,00 EUR',
]);
```

Fuer laengere Tabellen werden Header-Zeilen automatisch auf neuen Seiten wiederholt:

```php
$table = $page->createTable(20, 240, 170, [20, 80, 30, 40])
    ->font('NotoSans-Regular', 10)
    ->style(new TableStyle(
        verticalAlign: VerticalAlign::MIDDLE,
    ))
    ->addRow(['#', 'Eintrag', 'Status', 'Kommentar'], header: true);

$table->addRow([
    new TableCell(
        'Gruppe A',
        rowspan: 2,
        style: new CellStyle(
            horizontalAlign: HorizontalAlign::CENTER,
            verticalAlign: VerticalAlign::MIDDLE,
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

$table->addRow([
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
```

`TableCell` kann dabei auch Spalten oder Zeilen zusammenfassen:

```php
$table->addRow([
    new TableCell(
        'Zwischenuebersicht',
        colspan: 4,
        style: new CellStyle(horizontalAlign: HorizontalAlign::CENTER),
    ),
]);

$table->addRow([
    new TableCell(
        'Gruppe A',
        rowspan: 2,
        style: new CellStyle(horizontalAlign: HorizontalAlign::CENTER),
    ),
    'Eintrag 1',
    'Offen',
]);

$table->addRow([
    'Eintrag 2',
    'Aktiv',
]);
```

Wichtig: `rowspan` kann jetzt auch ueber einen Seitenumbruch hinweg laufen. Dabei wird der Block segmentweise auf Folge-Seiten fortgesetzt und der Zelltext ueber den Split weitergerendert.

Der aktuelle Stand ist dabei bewusst pragmatisch:

- `rowspan`-Bloecke koennen ueber Folge-Seiten weiterlaufen
- Header-Zeilen werden auf Folge-Seiten erneut gerendert
- Border und Textfluss ueber die Segmentgrenze funktionieren
- der optische Feinschliff fuer perfekt balancierte Zeilenverteilung an der Split-Grenze ist noch in Arbeit

Fuer die Ausrichtung gilt:

- `HorizontalAlign::LEFT`, `CENTER`, `RIGHT`, `JUSTIFY`
- `VerticalAlign::TOP`, `MIDDLE`, `BOTTOM`
- `TableStyle` setzt den Tabellen-Default
- `HeaderStyle` setzt Defaults fuer Header-Zeilen
- `RowStyle` setzt Defaults fuer Body-Zeilen
- `CellStyle` kann beide Ausrichtungen pro Zelle gebuendelt ueberschreiben

Fuer das Padding gilt:

- `TableStyle` traegt das Tabellen-Padding
- `RowStyle` kann Padding pro Zeile ueberschreiben
- `CellStyle` kann Zell-Padding gezielt ueberschreiben
- `TablePadding::all(...)`, `symmetric(...)` und `only(...)` decken die gaengigen Faelle ab

Beispiel:

```php
$table->style(new TableStyle(
    padding: TablePadding::symmetric(10, 4),
));

$table->addRow([
    'Default',
    new TableCell(
        'Eigenes Padding',
        style: new CellStyle(
            padding: TablePadding::only(top: 2, right: 4, bottom: 8, left: 20),
        ),
    ),
]);
```

Fuer feinere Linien kann die Tabelle oder die einzelne Zelle ein `TableBorder` tragen:

```php
$table->style(new TableStyle(
    border: TableBorder::all(color: Color::gray(0.75)),
));

$table->addRow([
    new TableCell(
        'Nur links rot, Rest grau',
        style: new CellStyle(
            border: TableBorder::only(['left'], color: Color::rgb(180, 20, 20)),
        ),
    ),
    new TableCell(
        'Oben/Unten blau, Seiten grau',
        style: new CellStyle(
            border: TableBorder::horizontal(color: Color::rgb(40, 120, 180)),
        ),
    ),
]);
```

Wichtig: Ein Border im `CellStyle` ersetzt den Tabellen-Border nicht komplett. Es werden nur die explizit gesetzten Seiten der Zelle ueberschrieben, alle anderen Seiten erben weiter vom `TableStyle`.

`CellStyle` ist dabei der empfohlene Weg, um mehrere Zell-Styles zusammenzufassen, statt viele Einzelparameter an `TableCell` zu uebergeben.

## Listen

Fuer einfache Aufzaehlungen steht `TextFrame::addBulletList(...)` zur Verfuegung.

Unterstuetzt werden aktuell:

- Listen aus `string` oder `TextSegment[]`
- Hanging Indent
- eigene Bullet-Farbe
- automatische Zeilenumbrueche
- Seitenwechsel ueber den vorhandenen Text-Flow
- vordefinierte `BulletType`-Varianten

Verfuegbare Bullet-Typen:

- `BulletType::DISC`
- `BulletType::DASH`
- `BulletType::CIRCLE`
- `BulletType::SQUARE`
- `BulletType::ARROW`

Beispiel:

```php
$frame->addBulletList(
    [
        'Erster Punkt',
        [TextSegment::bold('Zweiter'), TextSegment::plain(' Punkt')],
        'Dritter Punkt mit automatischem Umbruch in der Liste.',
    ],
    'NotoSans-Regular',
    12,
    bulletType: BulletType::DISC,
    bulletColor: Color::rgb(180, 20, 20),
);
```

Fuer nummerierte Listen gibt es `TextFrame::addNumberedList(...)`:

```php
$frame->addNumberedList(
    [
        'Erster Schritt',
        [TextSegment::bold('Zweiter'), TextSegment::plain(' Schritt')],
        'Dritter Schritt mit automatischem Umbruch in der Liste.',
    ],
    'NotoSans-Regular',
    12,
    startAt: 3,
    numberColor: Color::rgb(180, 20, 20),
);
```

Aktuell unterstuetzt die API dabei:

- dezimale Nummerierung
- `startAt`
- eigene Nummernfarbe
- denselben Hanging Indent und Seitenwechsel wie bei `addBulletList(...)`

## Einheiten

Die API erwartet numerische Layoutwerte grundsaetzlich in PDF-Points.

Wenn du lieber in physischen Einheiten arbeitest, kannst du die Helper aus `Units` verwenden:

```php
Units::pt(12);
Units::mm(20);
Units::cm(2.5);
Units::inch(1);
```

Typisch ist dabei:

- Schriftgroessen wie `12` oder `16` sind bereits Points
- Seitenmasse, Positionen, Breiten und Margins koennen bei Bedarf explizit ueber `Units` umgerechnet werden

## Font-Konfiguration

Die eingebetteten Fonts werden standardmaessig ueber `config/fonts.php` definiert.

Aktuell sind dort unter anderem registriert:

- `NotoSans-Regular`
- `NotoSans-Bold`
- `NotoSans-Italic`
- `NotoSans-BoldItalic`
- `NotoSerif-Regular`
- `NotoSansMono-Regular`
- `NotoSansCJKsc-Regular`

Standard-PDF-Fonts wie `Helvetica` benoetigen keinen Eintrag in der Config.

Du kannst die globale Config verwenden oder pro Dokument eine eigene Liste ueber `fontConfig` setzen.

## Unicode-Beispiel

Fuer breitere Zeichensaetze kann ein Unicode-Font direkt ueber seinen Fontnamen registriert werden:

```php
$document->registerFont('NotoSansCJKsc-Regular');

$page->addText(
    '漢字とカタカナ',
    new Position(Units::mm(20), Units::mm(225)),
    'NotoSansCJKsc-Regular',
    14,
    new TextOptions(structureTag: \Kalle\Pdf\Document\Text\StructureTag::Paragraph),
);
```

## Dokumenteigene Font-Konfiguration

Zusatzlich zur globalen `config/fonts.php` kann ein Dokument eine eigene Font-Konfiguration erhalten:

```php
$document = new Document(
    version: 1.4,
    fontConfig: [
        [
            'baseFont' => 'CustomSans-Regular',
            'path' => 'assets/fonts/NotoSans-Regular.ttf',
            'unicode' => true,
            'subtype' => 'CIDFontType2',
            'encoding' => 'Identity-H',
        ],
    ],
);

$document->registerFont('CustomSans-Regular');
```

## Wichtige Text-Features

Neben einfachem `addText(...)` unterstuetzt die aktuelle API bereits mehrere Ausbaustufen fuer Text:

- `Page::addParagraph(...)` fuer Umbruch innerhalb einer festen Breite
- `Page::createTextFrame(...)` fuer Fliesstext mit Cursor-Fuehrung
- `Page::addTextBox(...)` fuer Text in einem festen Rechteck
- `Color::rgb(...)`, `Color::gray(...)`, `Color::cmyk(...)` und `Color::hex(...)`
- `Opacity::fill(...)`, `Opacity::stroke(...)`, `Opacity::both(...)`
- `TextSegment` fuer gemischte Inline-Stile innerhalb eines Absatzes
- `TextOptions` fuer optionale Text-Features wie Farbe, Opacity, Dekorationen und Links
- `link` direkt pro `TextSegment`
- `bold`, `italic`, `underline`, `strikethrough` pro `TextSegment`
- `HorizontalAlign::LEFT`, `CENTER`, `RIGHT`, `JUSTIFY`
- `TextOverflow::CLIP` und `TextOverflow::ELLIPSIS` zusammen mit `maxLines`

Ein einfaches `addTextBox(...)`-Beispiel:

```php
$page->addTextBox(
    text: "DEIN FIRMENNAME\nStrasse 1\n12345 Berlin",
    box: new Rect(Units::mm(20), Units::mm(250), Units::mm(60), Units::mm(18)),
    fontName: 'Helvetica',
    size: 10,
    options: new TextBoxOptions(
        lineHeight: Units::mm(4),
    ),
);
```

Kleine Geometrie-Value-Objects:

- `Position` gruppiert `x` und `y` fuer punktgenaue Platzierung, zum Beispiel bei `addText(...)`
- `Rect` gruppiert `x`, `y`, `width` und `height` fuer feste Boxen, zum Beispiel bei `addTextBox(...)`
- `Insets` gruppiert innere Abstaende fuer `top`, `right`, `bottom` und `left`, zum Beispiel in `TextBoxOptions`

## Grafische Elemente und Bilder

Neben Text stehen jetzt auch erste grafische Primitive und Bildplatzierung zur Verfuegung:

- `Page::addLine(...)` fuer einfache Linien mit Farbe, Linienstaerke und optionaler Stroke-Opacity
- `Page::addRectangle(...)` fuer Stroke, Fill oder Fill+Stroke
- `Page::addPath()` fuer freie Pfade mit `moveTo(...)`, `lineTo(...)`, `curveTo(...)`, `close()` und den Paint-Modi `stroke()`, `fill()` und `fillAndStroke()`
- `Page::addCircle(...)` fuer Kreise auf Basis des Path-Builders
- `Page::addEllipse(...)` fuer Ellipsen mit getrennten X- und Y-Radien
- `Page::addPolygon(...)` fuer geschlossene Formen aus einer Punktliste
- `Page::addArrow(...)` fuer Linien mit gefuellter Pfeilspitze
- `Image::fromFile(...)` fuer automatische Erkennung von `jpg`, `jpeg` und `png`, inklusive Alpha-PNG ueber Soft-Mask
- `Page::addImage(...)` fuer die Platzierung eines Bildes an einer festen Position
- `Page::addLink(...)` fuer frei positionierbare klickbare Flaechen
- `TextOptions(link: LinkTarget::...)` fuer klickbaren Text ohne manuelles Link-Rechteck
- `TextSegment::link` fuer Links innerhalb von `addParagraph(...)` und `createTextFrame(...)`

Beispiele:

```php
$page->addLine(20, 200, 180, 200, 2.5, Color::rgb(255, 0, 0), Opacity::stroke(0.25));

$page->addRectangle(20, 140, 80, 30, null, null, Color::gray(0.9));

$page->addRectangle(
    20,
    90,
    80,
    30,
    1.5,
    Color::rgb(0, 0, 0),
    Color::rgb(240, 240, 240),
    Opacity::both(0.5),
);

$page->addPath()
    ->moveTo(140, 140)
    ->lineTo(160, 160)
    ->lineTo(140, 180)
    ->lineTo(120, 160)
    ->close()
    ->fillAndStroke(
        1.5,
        Color::rgb(0, 0, 0),
        Color::gray(0.92),
    );

$page->addCircle(
    190,
    160,
    18,
    1.5,
    Color::rgb(0, 0, 0),
    Color::gray(0.92),
    Opacity::both(0.5),
);

$page->addEllipse(240, 160, 26, 14, 1.5, Color::rgb(0, 0, 0), Color::gray(0.92));

$page->addPolygon(
    [[280, 140], [300, 160], [280, 180], [260, 160]],
    1.5,
    Color::rgb(0, 0, 0),
    Color::gray(0.92),
);

$page->addArrow(320, 160, 380, 160, 2.0, Color::rgb(200, 30, 30), Opacity::both(0.5));

$image = Image::fromFile('assets/images/demo.jpg');
$page->addImage($image, 110, 80, 70, 46.67);

$page->addText(
    text: 'OpenAI',
    position: new Position(20, 50),
    fontName: 'Helvetica',
    size: 12,
    options: new TextOptions(
        color: Color::rgb(0, 0, 255),
        underline: true,
        link: LinkTarget::externalUrl('https://openai.com'),
    ),
);

$frame->addParagraph(
    [
        new TextSegment('Mehr Infos: '),
        new TextSegment(
            text: 'Docs',
            color: Color::rgb(0, 0, 255),
            link: LinkTarget::externalUrl('https://platform.openai.com/docs'),
            underline: true,
        ),
    ],
    'Helvetica',
    12,
);

$document->addDestination('docs', $page);
$page->addInternalLink(20, 30, 60, 12, 'docs');
$page->addText(
    text: 'Zu Docs springen',
    position: new Position(20, 15),
    fontName: 'Helvetica',
    size: 12,
    options: new TextOptions(
        color: Color::rgb(0, 0, 255),
        underline: true,
        link: LinkTarget::namedDestination('docs'),
    ),
);
```

Ein kompakter Absatz mit gemischten Stilen sieht zum Beispiel so aus:

```php
$frame->addParagraph(
    [
        TextSegment::underlined('Achtung: ', Color::rgb(255, 0, 0)),
        TextSegment::italic('weiterer Text in Kursivschrift, '),
        TextSegment::strikethrough('halbtransparent und durchgestrichen')->withDefaults(
            null,
            Opacity::fill(0.5),
        ),
    ],
    'NotoSans-Regular',
    12,
    new ParagraphOptions(
        structureTag: StructureTag::Paragraph,
        align: HorizontalAlign::JUSTIFY,
        maxLines: 2,
        overflow: TextOverflow::ELLIPSIS,
    ),
);
```

Fuer haeufige Inline-Faelle gibt es kleine Helfer auf `TextSegment`:

```php
$frame->addParagraph(
    [
        TextSegment::plain('Mehr Infos: '),
        TextSegment::link(
            'Docs',
            LinkTarget::externalUrl('https://example.com/docs'),
            Color::rgb(0, 0, 255),
        ),
        TextSegment::plain(' und '),
        TextSegment::bold('wichtige Hinweise'),
        TextSegment::plain(' mit '),
        TextSegment::underlined('Unterstreichung'),
        TextSegment::plain(' sowie '),
        TextSegment::strikethrough('alten Hinweisen'),
        TextSegment::plain('.'),
    ],
    'NotoSans-Regular',
    12,
);
```

## Formulare

Die Library unterstuetzt aktuell eine erste AcroForm-Stufe ueber Widget-Annotationen.

Vorhandene Feldtypen:

- `addTextField(...)`
- `addCheckbox(...)`
- `addRadioButton(...)`
- `addComboBox(...)`
- `addListBox(...)`
- `addSignatureField(...)`
- `addPushButton(...)`

### TextField

```php
$page->addTextField(
    'customer_name',
    Units::mm(20),
    Units::mm(200),
    Units::mm(80),
    Units::mm(10),
    'Ada Lovelace',
    'Helvetica',
    11,
    defaultValue: 'Grace Hopper',
);
```

Mehrzeilige Felder:

```php
$page->addTextField(
    'notes',
    Units::mm(20),
    Units::mm(160),
    Units::mm(80),
    Units::mm(20),
    "Erste Zeile\nZweite Zeile",
    'Helvetica',
    11,
    multiline: true,
);
```

Feld-Flags:

```php
$page->addTextField(
    'pin',
    Units::mm(20),
    Units::mm(140),
    Units::mm(40),
    Units::mm(10),
    '1234',
    'Helvetica',
    11,
    flags: new FormFieldFlags(
        required: true,
        password: true,
    ),
);
```

### Checkbox

```php
$page->addCheckbox(
    'accept_terms',
    Units::mm(20),
    Units::mm(120),
    Units::mm(6),
    true,
);
```

### RadioButton

```php
$page->addRadioButton('delivery', 'standard', Units::mm(20), Units::mm(100), Units::mm(6), true);
$page->addRadioButton('delivery', 'express', Units::mm(50), Units::mm(100), Units::mm(6), false);
```

Radio-Buttons mit demselben Feldnamen bilden automatisch eine Gruppe.

### ComboBox

```php
$page->addComboBox(
    'country',
    Units::mm(20),
    Units::mm(80),
    Units::mm(60),
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
```

Editierbare ComboBox:

```php
$page->addComboBox(
    'custom_country',
    Units::mm(90),
    Units::mm(80),
    Units::mm(60),
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
```

### ListBox

```php
$page->addListBox(
    'topics',
    Units::mm(20),
    Units::mm(40),
    Units::mm(60),
    Units::mm(25),
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
```

Mehrfachauswahl:

```php
$page->addListBox(
    'topic_selection',
    Units::mm(90),
    Units::mm(40),
    Units::mm(60),
    Units::mm(25),
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
```

### SignatureField

```php
$page->addSignatureField(
    'approval_signature',
    Units::mm(20),
    Units::mm(10),
    Units::mm(80),
    Units::mm(18),
);
```

### PushButton

```php
$page->addPushButton(
    'save_form',
    'Speichern',
    Units::mm(110),
    Units::mm(10),
    Units::mm(40),
    Units::mm(10),
);
```

Mit Actions:

```php
$page->addPushButton(
    'save_form',
    'Speichern',
    Units::mm(20),
    Units::mm(10),
    Units::mm(40),
    Units::mm(10),
    action: new SubmitFormAction('https://example.com/form-submit'),
);

$page->addPushButton(
    'reset_form',
    'Zuruecksetzen',
    Units::mm(65),
    Units::mm(10),
    Units::mm(45),
    Units::mm(10),
    action: new ResetFormAction(),
);

$page->addPushButton(
    'validate_form',
    'Pruefen',
    Units::mm(115),
    Units::mm(10),
    Units::mm(35),
    Units::mm(10),
    action: new JavaScriptAction("app.alert('Formular pruefen');"),
);

$page->addPushButton(
    'prev_page',
    'Zurueck',
    Units::mm(155),
    Units::mm(10),
    Units::mm(35),
    Units::mm(10),
    action: new NamedAction('PrevPage'),
);

$page->addPushButton(
    'goto_table',
    'Zur Tabelle',
    Units::mm(20),
    Units::mm(22),
    Units::mm(50),
    Units::mm(10),
    action: new GoToAction('table-demo'),
);

$page->addPushButton(
    'open_remote',
    'Extern',
    Units::mm(75),
    Units::mm(22),
    Units::mm(35),
    Units::mm(10),
    action: new GoToRemoteAction('guide.pdf', 'chapter-1'),
);

$page->addPushButton(
    'open_file',
    'Datei',
    Units::mm(115),
    Units::mm(22),
    Units::mm(35),
    Units::mm(10),
    action: new LaunchAction('guide.pdf'),
);

$page->addPushButton(
    'open_site',
    'Website',
    Units::mm(155),
    Units::mm(22),
    Units::mm(35),
    Units::mm(10),
    action: new UriAction('https://example.com'),
);

$page->addPushButton(
    'hide_notes',
    'Ausblenden',
    Units::mm(20),
    Units::mm(34),
    Units::mm(40),
    Units::mm(10),
    action: new HideAction('notes_panel'),
);

$page->addPushButton(
    'import_data',
    'Import',
    Units::mm(65),
    Units::mm(34),
    Units::mm(35),
    Units::mm(10),
    action: new ImportDataAction('form-data.fdf'),
);

$page->addPushButton(
    'toggle_layer',
    'Layer',
    Units::mm(105),
    Units::mm(34),
    Units::mm(35),
    Units::mm(10),
    action: new SetOcgStateAction(['Toggle', $notesLayer], preserveRb: false),
);

$page->addPushButton(
    'open_thread',
    'Thread',
    Units::mm(145),
    Units::mm(34),
    Units::mm(45),
    Units::mm(10),
    action: new ThreadAction('article-1', 'threads.pdf'),
);
```

Wichtig:

- `value` und `defaultValue` muessen bei ComboBox und ListBox auf vorhandene Export-Werte in `options` zeigen
- `defaultValue` wird als `DV` im PDF gespeichert
- `editable` aktiviert bei ComboBoxen Freitext-Eingabe zusaetzlich zu den vorhandenen Optionen
- `multiSelect` erlaubt bei ListBoxen mehrere gleichzeitig gesetzte Werte
- `addSignatureField(...)` erzeugt aktuell ein sichtbares Signaturfeld als Formular-Widget, aber noch keine echte kryptografische Signatur
- `addPushButton(...)` unterstuetzt aktuell `SubmitFormAction`, `ResetFormAction`, `JavaScriptAction`, `NamedAction`, `GoToAction`, `GoToRemoteAction`, `LaunchAction`, `UriAction`, `HideAction`, `ImportDataAction`, `SetOcgStateAction` und `ThreadAction`
- `AcroForm` wird automatisch aufgebaut, sobald das erste Feld erzeugt wird
- Text- und Choice-Felder verlassen sich aktuell noch auf `NeedAppearances`
- Checkboxen und Radio-Buttons haben bereits eigene Appearance-Streams

### Layer und OCG

```php
$notesLayer = $document->addLayer('Notes');
$gridLayer = $document->addLayer('Grid', false);

$page->layer($notesLayer, static function (\Kalle\Pdf\Document\Page $page): void {
    $page->addText('Interne Hinweise', new \Kalle\Pdf\Document\Geometry\Position(Units::mm(20), Units::mm(220)), 'NotoSans-Regular', 12);
});

$page->layer($gridLayer, static function (\Kalle\Pdf\Document\Page $page): void {
    $page->addLine(Units::mm(20), Units::mm(200), Units::mm(190), Units::mm(200), 0.5, Color::gray(0.7));
});

$page->addPushButton(
    'toggle_notes',
    'Notes',
    Units::mm(20),
    Units::mm(20),
    Units::mm(30),
    Units::mm(8),
    action: new SetOcgStateAction(['Toggle', $notesLayer], preserveRb: false),
);
```

Wichtig:

- `Document::addLayer(...)` erzeugt oder liefert ein `OptionalContentGroup`-Objekt
- `Page::layer(...)` kapselt Inhalt in `BDC`/`EMC` mit einer echten OCG-Referenz
- `SetOcgStateAction` arbeitet jetzt mit echten Layer-Referenzen statt nur mit Layer-Namen
- `example.php` enthaelt dafuer jetzt eine eigene `Layer Demo`- und eine eigene `Action Demo`-Seite

### Attachments

```php
$document->addAttachment(
    'demo-note.txt',
    "Diese Datei ist als Dokument-Anhang eingebettet.\n",
    'Kleine Demo-Datei fuer Attachments',
    'text/plain',
);

$document->addAttachmentFromFile(
    'README.md',
    description: 'Projekt-README als eingebettete Datei',
    mimeType: 'text/markdown',
);
```

Wichtig:

- eingebettete Dateien werden aktuell ueber den `EmbeddedFiles`-Name-Tree im `Catalog` registriert
- `example.php` enthaelt dafuer jetzt eine eigene `Attachment Demo`-Seite
- eingebettete Dateien koennen zusaetzlich ueber `addFileAttachment(...)` als sichtbare `FileAttachment`-Annotation auf einer Seite erscheinen

### Weitere Annotationen

```php
$page->addTextAnnotation(20, 200, 8, 8, 'Kurzer Kommentar', 'QA', 'Comment', true);
$annotation = $page->getAnnotations()[0];
$page->addPopupAnnotation($annotation, 32, 180, 60, 40, true);

$page->addFreeTextAnnotation(
    35,
    190,
    70,
    18,
    'Direkter Hinweis auf der Seite',
    'Helvetica',
    11,
    Color::rgb(180, 20, 20),
    Color::gray(0.5),
    Color::gray(0.95),
    'QA',
);

$page->addHighlightAnnotation(20, 170, 65, 6, Color::rgb(255, 255, 0), 'Highlight', 'QA');
$page->addUnderlineAnnotation(88, 170, 28, 6, Color::rgb(0, 0, 255), 'Underline', 'QA');
$page->addStrikeOutAnnotation(119, 170, 28, 6, Color::rgb(255, 0, 0), 'StrikeOut', 'QA');
$page->addSquigglyAnnotation(150, 170, 40, 6, Color::rgb(255, 0, 255), 'Squiggly', 'QA');
$page->addStampAnnotation(20, 150, 35, 14, 'Approved', Color::rgb(0, 128, 0), 'Freigegeben', 'QA');
$page->addSquareAnnotation(20, 130, 24, 14, Color::rgb(255, 0, 0), Color::gray(0.92), 'Square', 'QA', AnnotationBorderStyle::solid(2.0));
$page->addCircleAnnotation(52, 130, 24, 14, Color::rgb(0, 0, 255), Color::gray(0.92), 'Circle', 'QA', AnnotationBorderStyle::dashed(1.5, [2.0, 1.0]));
$page->addFileAttachment(65, 130, 8, 8, $attachment, 'PushPin', 'Sichtbarer Dateianhang');
$page->addLineAnnotation(
    20,
    110,
    48,
    102,
    Color::rgb(220, 40, 40),
    'Line',
    'QA',
    LineEndingStyle::OPEN_ARROW,
    LineEndingStyle::CLOSED_ARROW,
    'Messlinie',
    AnnotationBorderStyle::dashed(2.0, [4.0, 2.0]),
);
$line = $page->getAnnotations()[count($page->getAnnotations()) - 1];
$page->addPopupAnnotation($line, 18, 84, 34, 14, true);
$page->addPolyLineAnnotation(
    [
        [58.0, 104.0],
        [66.0, 112.0],
        [74.0, 102.0],
        [82.0, 110.0],
    ],
    Color::rgb(30, 90, 210),
    'PolyLine',
    'QA',
    LineEndingStyle::CIRCLE,
    LineEndingStyle::SLASH,
    'Korrekturpfad',
    AnnotationBorderStyle::solid(2.5),
);
$polyLine = $page->getAnnotations()[count($page->getAnnotations()) - 1];
$page->addPopupAnnotation($polyLine, 58, 84, 34, 14);
$page->addPolygonAnnotation(
    [
        [95.0, 102.0],
        [106.0, 114.0],
        [118.0, 106.0],
        [112.0, 98.0],
    ],
    Color::rgb(20, 130, 60),
    Color::gray(0.9),
    'Polygon',
    'QA',
    'Flaechenhinweis',
    AnnotationBorderStyle::dashed(),
);
$polygon = $page->getAnnotations()[count($page->getAnnotations()) - 1];
$page->addPopupAnnotation($polygon, 98, 84, 34, 14);
$page->addCaretAnnotation(128, 98, 10, 12, 'Einfuemarke', 'QA', 'P');
$page->addInkAnnotation(
    85,
    127,
    45,
    20,
    [
        [
            [88.0, 132.0],
            [95.0, 140.0],
            [102.0, 130.0],
        ],
    ],
    Color::rgb(0, 0, 0),
    'Ink',
    'QA',
);
```

Wichtig:

- `TextAnnotation` ist die klassische Sticky-Note-Annotation
- `FreeTextAnnotation` rendert sichtbaren Kommentartext direkt als Annotation
- `Highlight`, `Underline`, `StrikeOut` und `Squiggly` werden aktuell ueber ein Rechteck plus `QuadPoints` modelliert
- `StampAnnotation` nutzt einen Viewer-Stempel ueber `/Name`
- `SquareAnnotation` und `CircleAnnotation` bilden einfache Viewer-Formannotation mit Border- und optionaler Fill-Farbe ab
- `LineAnnotation` und `PolyLineAnnotation` unterstuetzen zusaetzlich `LineEndingStyle` ueber `LE`
- `AnnotationBorderStyle` setzt fuer geometrische Viewer-Annotationen das PDF-`BS`-Dictionary, inklusive gestrichelter Linien
- `LineAnnotation`, `PolyLineAnnotation` und `PolygonAnnotation` bilden lineare und geschlossene Viewer-Geometrien mit `L` oder `Vertices` ab
- `LineAnnotation`, `PolyLineAnnotation` und `PolygonAnnotation` koennen ausserdem `Subj` und verknuepfte `PopupAnnotation` tragen
- `CaretAnnotation` bildet Einfuegemarken oder Korrekturhinweise mit optionalem Symbol `None` oder `P` ab
- `FileAttachmentAnnotation` wird ueber `addFileAttachment(...)` sichtbar auf einer Seite referenziert
- `InkAnnotation` verwendet `InkList` fuer freie Stift- oder Handschriftzuege
- `PopupAnnotation` wird ueber `addPopupAnnotation(...)` an bestehende Kommentar- oder Markup-Annotationen gehaengt
- `example.php` enthaelt dafuer jetzt eine eigene `Annotation Demo`-Seite

## Verschluesselung

Die Library kann Dokumente aktuell ueber den Standard-Security-Handler verschluesseln.

Ein einfaches Beispiel:

```php
use Kalle\Pdf\Encryption\EncryptionAlgorithm;
use Kalle\Pdf\Encryption\EncryptionOptions;

$document = new Document(version: 1.6);
$document->registerFont('Helvetica');

$document->encrypt(new EncryptionOptions(
    userPassword: 'secret',
    ownerPassword: 'secret',
    algorithm: EncryptionAlgorithm::AES_128,
));

$page = $document->addPage(PageSize::A4());
$page->addText('Geschuetzter Inhalt', new \Kalle\Pdf\Document\Geometry\Position(Units::mm(20), Units::mm(260)), 'Helvetica', 14);

file_put_contents('encrypted.pdf', $document->render());
```

Aktuell verfuegbar:

- `RC4_40` fuer PDF `1.3`
- `RC4_128` fuer PDF `1.4` und `1.5`
- `AES_128` fuer PDF `1.6`
- `AES_256` fuer PDF `1.7`

Wenn kein Algorithmus explizit gesetzt wird, waehlt die Library den zur PDF-Version passenden Standardpfad automatisch.

## Aktueller Funktionsumfang

Der derzeit belastbare Einstieg ist:

- Dokument anlegen
- Metadaten setzen
- Keywords hinzufuegen
- globale oder dokumenteigene Fonts konfigurieren
- eine oder mehrere Seiten anlegen
- Text mit registrierten Fonts rendern
- Unicode-Text mit eingebetteten Fonts rendern
- Farben und Opacity fuer Text setzen
- Paragraphen mit Umbruch und Ausrichtung rendern
- TextFrames ueber mehrere Bloecke und Seiten verwenden
- gemischte Inline-Stile mit `TextSegment` rendern
- Absatzumfang mit `maxLines` und `TextOverflow` begrenzen
- Linien rendern
- Rechtecke rendern
- freie Pfade und Formen wie Diamanten rendern
- Kreise rendern
- Ellipsen rendern
- Polygone rendern
- Pfeile rendern
- Bilder aus Dateien laden und platzieren
- klickbare Links ueber `addLink(...)`, `TextOptions(link: LinkTarget::...)` und `TextSegment::link`
- interne Spruenge ueber `addDestination(...)`, `addInternalLink(...)` und `LinkTarget::namedDestination(...)`
- Passwortverschluesselung ueber `encrypt(...)` fuer `RC4_40`, `RC4_128`, `AES_128` und `AES_256`
- Formularfelder ueber `addTextField(...)`, `addCheckbox(...)`, `addRadioButton(...)`, `addComboBox(...)` und `addListBox(...)`

## Aktuelle Grenzen

Der Codebestand enthaelt bereits weitere Bausteine, aber fuer den Einstieg solltest du aktuell von diesem Stand ausgehen:

- interlaced PNG und indexed PNG werden aktuell bewusst nicht ueber `Image::fromFile(...)` unterstuetzt
- PNG mit Alpha-Kanal wird aktuell ueber eine PDF-Soft-Mask (`SMask`) eingebettet, was bei grossen Dateien deutlich teurer sein kann als JPEG oder PNG ohne Alpha
- grafische Primitive sind aktuell auf Linien und Rechtecke fokussiert
- `underline` und `strikethrough` sind aktuell heuristisch positioniert und noch nicht ueber Font-Metriken feinjustiert
- `bold` und `italic` fuer Embedded Fonts haengen derzeit an benannten Font-Varianten wie `-Bold` oder `-Italic`
- Text- und Choice-Felder haben aktuell noch keine komplett eigene Appearance-Generierung
- echte digitale Signaturen und komplett eigene Appearances fuer Text-/Choice-Felder sind noch nicht Teil der aktuellen Formular-Stufe
- die Doku wird schrittweise parallel zum Code aufgebaut

## Naechste Datei

Als sinnvolle Fortsetzung bietet sich [architecture.md](architecture.md) an. Dort wird beschrieben, wie `Document`, `Page`, `Resources`, `Contents` und das Text-Layout zusammenspielen.
