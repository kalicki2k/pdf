# Getting Started

Diese Library erzeugt PDFs direkt aus PHP. Der aktuelle Fokus liegt auf einer kleinen, klaren API fuer Dokumente, Seiten, Text, Fonts, Bilder und erste grafische Primitive.

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

## Erstes Dokument

```php
<?php

declare(strict_types=1);

use Kalle\Pdf\Document\Document;
use Kalle\Pdf\Document\PageSize;
use Kalle\Pdf\Document\TableCell;
use Kalle\Pdf\Document\TextAlign;
use Kalle\Pdf\Document\TextOverflow;
use Kalle\Pdf\Document\TextSegment;
use Kalle\Pdf\Document\Units;
use Kalle\Pdf\Element\Image;
use Kalle\Pdf\Graphics\Color;
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
    ->addFont('NotoSans-Regular')
    ->addFont('NotoSans-Bold')
    ->addFont('NotoSans-Italic')
    ->addFont('NotoSans-BoldItalic');

$page = $document->addPage(PageSize::A4());
$frame = $page->textFrame(Units::mm(20), Units::mm(265), Units::mm(170), Units::mm(20));

$frame
    ->heading(
        'Hallo PDF',
        'NotoSans-Regular',
        24,
        'H1',
        color: Color::rgb(220, 20, 60),
    )
    ->paragraph(
        [
            new TextSegment('Achtung: ', Color::rgb(220, 20, 60), bold: true, underline: true),
            new TextSegment('dieser Absatz zeigt gemischte Textstile, Farben und Opacity. ', italic: true),
            new TextSegment('Dieser Teil ist durchgestrichen.', opacity: Opacity::fill(0.5), strikethrough: true),
        ],
        'NotoSans-Regular',
        12,
        'P',
        align: TextAlign::JUSTIFY,
        maxLines: 3,
        overflow: TextOverflow::ELLIPSIS,
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

$page->path()
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
    x: Units::mm(20),
    y: Units::mm(150),
    baseFont: 'NotoSans-Regular',
    size: 12,
    color: Color::rgb(0, 0, 255),
    underline: true,
    link: 'https://example.com',
);

$page->table(
    Units::mm(20),
    Units::mm(135),
    Units::mm(170),
    [Units::mm(22), Units::mm(88), Units::mm(30), Units::mm(30)],
)
    ->font('NotoSans-Regular', 11)
    ->padding(Units::mm(2.5))
    ->headerStyle(Color::gray(0.92), Color::rgb(220, 20, 60))
    ->addRow(['ID', 'Titel', 'Status', 'Preis'], header: true)
    ->addRow([
        '1',
        'Starter-Paket mit kurzer Beschreibung.',
        new TableCell('Aktiv', TextAlign::CENTER, Color::gray(0.94)),
        '19,99 EUR',
    ]);

$pdfContent = $document->render();

file_put_contents('hello.pdf', $pdfContent);
```

## Was im Beispiel passiert

1. `Document` initialisiert das PDF mit Version und Metadaten.
2. `addFont(...)` registriert eingebettete Schriften aus der Font-Konfiguration.
3. `addPage()` erstellt eine neue Seite, standardmaessig im Format A4 in PDF-Points oder explizit ueber `PageSize::A4()`.
4. `textFrame()` erzeugt einen Textbereich mit eigener Cursor-Fuehrung.
5. `heading()` und `paragraph()` rendern Text innerhalb dieses Bereichs, inklusive Umbruch und optionalem Seitenwechsel.
6. `addLine(...)`, `addRectangle(...)`, `path()`, `addCircle(...)`, `addEllipse(...)`, `addPolygon(...)`, `addArrow(...)`, `addStar(...)` und `addImage(...)` platzieren einfache grafische Inhalte direkt auf der Seite.
7. `addText(..., link: ...)` kann Text direkt mit einer klickbaren Link-Annotation verbinden.
8. `table(...)` erzeugt eine erste Tabellen-API mit festen Spaltenbreiten, Header-Zeilen und automatischer Zeilenhoehe.
9. `render()` gibt den kompletten PDF-Inhalt als String zurueck.

## Tabellen

Die erste Tabellenstufe ist bewusst pragmatisch gehalten. Sie deckt bereits haeufige Dokumentfaelle ab:

- feste Spaltenbreiten
- Header-Zeilen
- Zellpadding
- `string`, `TextSegment[]` oder `TableCell` als Zelleninhalt
- automatische Zeilenhoehe durch Textumbruch
- Seitenwechsel, wenn die naechste Zeile nicht mehr auf die aktuelle Seite passt

Ein kompaktes Beispiel:

```php
$table = $page->table(20, 240, 170, [30, 80, 30, 30])
    ->font('NotoSans-Regular', 11)
    ->padding(6)
    ->headerStyle(Color::gray(0.92), Color::rgb(180, 20, 20))
    ->rowStyle(null, Color::gray(0.15));

$table->addRow(['ID', 'Titel', 'Status', 'Preis'], header: true);
$table->addRow([
    '1',
    'Starter-Paket mit kurzer Beschreibung.',
    new TableCell('Aktiv', TextAlign::CENTER, Color::gray(0.94)),
    '19,99 EUR',
]);
$table->addRow([
    '2',
    [
        new TextSegment('Pro Plan', bold: true),
        new TextSegment(' mit Umbruch in der Tabellenzelle.'),
    ],
    new TableCell('Beta', TextAlign::CENTER),
    '49,00 EUR',
]);
```

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
$document->addFont('NotoSansCJKsc-Regular');

$page->addText('漢字とカタカナ', Units::mm(20), Units::mm(225), 'NotoSansCJKsc-Regular', 14, 'P');
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

$document->addFont('CustomSans-Regular');
```

## Wichtige Text-Features

Neben einfachem `addText(...)` unterstuetzt die aktuelle API bereits mehrere Ausbaustufen fuer Text:

- `Page::addParagraph(...)` fuer Umbruch innerhalb einer festen Breite
- `Page::textFrame(...)` fuer Fliesstext mit Cursor-Fuehrung
- `Color::rgb(...)`, `Color::gray(...)`, `Color::cmyk(...)` und `Color::hex(...)`
- `Opacity::fill(...)`, `Opacity::stroke(...)`, `Opacity::both(...)`
- `TextSegment` fuer gemischte Inline-Stile innerhalb eines Absatzes
- `link` direkt in `Page::addText(...)`
- `link` direkt pro `TextSegment`
- `bold`, `italic`, `underline`, `strikethrough` pro `TextSegment`
- `TextAlign::LEFT`, `CENTER`, `RIGHT`, `JUSTIFY`
- `TextOverflow::CLIP` und `TextOverflow::ELLIPSIS` zusammen mit `maxLines`

## Grafische Elemente und Bilder

Neben Text stehen jetzt auch erste grafische Primitive und Bildplatzierung zur Verfuegung:

- `Page::addLine(...)` fuer einfache Linien mit Farbe, Linienstaerke und optionaler Stroke-Opacity
- `Page::addRectangle(...)` fuer Stroke, Fill oder Fill+Stroke
- `Page::path()` fuer freie Pfade mit `moveTo(...)`, `lineTo(...)`, `curveTo(...)`, `close()` und den Paint-Modi `stroke()`, `fill()` und `fillAndStroke()`
- `Page::addCircle(...)` fuer Kreise auf Basis des Path-Builders
- `Page::addEllipse(...)` fuer Ellipsen mit getrennten X- und Y-Radien
- `Page::addPolygon(...)` fuer geschlossene Formen aus einer Punktliste
- `Page::addArrow(...)` fuer Linien mit gefuellter Pfeilspitze
- `Image::fromFile(...)` fuer automatische Erkennung von `jpg`, `jpeg` und unterstuetzten `png`
- `Page::addImage(...)` fuer die Platzierung eines Bildes an einer festen Position
- `Page::addLink(...)` fuer frei positionierbare klickbare Flaechen
- `Page::addText(..., link: ...)` fuer klickbaren Text ohne manuelles Link-Rechteck
- `TextSegment::link` fuer Links innerhalb von `addParagraph(...)` und `textFrame(...)`

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

$page->path()
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
    x: 20,
    y: 50,
    baseFont: 'Helvetica',
    size: 12,
    color: Color::rgb(0, 0, 255),
    underline: true,
    link: 'https://openai.com',
);

$frame->paragraph(
    [
        new TextSegment('Mehr Infos: '),
        new TextSegment(
            text: 'Docs',
            color: Color::rgb(0, 0, 255),
            link: 'https://platform.openai.com/docs',
            underline: true,
        ),
    ],
    'Helvetica',
    12,
);
```

Ein kompakter Absatz mit gemischten Stilen sieht zum Beispiel so aus:

```php
$frame->paragraph(
    [
        new TextSegment('Achtung: ', Color::rgb(255, 0, 0), bold: true, underline: true),
        new TextSegment('weiterer Text in Kursivschrift, ', italic: true),
        new TextSegment('halbtransparent und durchgestrichen', opacity: Opacity::fill(0.5), strikethrough: true),
    ],
    'NotoSans-Regular',
    12,
    'P',
    align: TextAlign::JUSTIFY,
    maxLines: 2,
    overflow: TextOverflow::ELLIPSIS,
);
```

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
- klickbare Links ueber `addLink(...)`, `addText(..., link: ...)` und `TextSegment::link`

## Aktuelle Grenzen

Der Codebestand enthaelt bereits weitere Bausteine, aber fuer den Einstieg solltest du aktuell von diesem Stand ausgehen:

- PNG mit Alpha-Kanal, interlaced PNG und indexed PNG werden aktuell bewusst nicht ueber `Image::fromFile(...)` unterstuetzt
- grafische Primitive sind aktuell auf Linien und Rechtecke fokussiert
- `underline` und `strikethrough` sind aktuell heuristisch positioniert und noch nicht ueber Font-Metriken feinjustiert
- `bold` und `italic` fuer Embedded Fonts haengen derzeit an benannten Font-Varianten wie `-Bold` oder `-Italic`
- die Doku wird schrittweise parallel zum Code aufgebaut

## Naechste Datei

Als sinnvolle Fortsetzung bietet sich [architecture.md](architecture.md) an. Dort wird beschrieben, wie `Document`, `Page`, `Resources`, `Contents` und das Text-Layout zusammenspielen.
