# PDF2

## Struktur

Der Quellcode ist jetzt grob nach Verantwortlichkeiten organisiert:

```text
src/
├─ Color/
├─ Document/
├─ Drawing/
├─ Font/
├─ Image/
├─ Page/
├─ Text/
├─ Writer/
└─ Pdf.php
```

## Bilder

Das Bildfundament ist über `ImageSource` und `ImagePlacement` angebunden. Die aktuelle API erwartet bereits vorbereitete Bilddaten, die als PDF-Image-XObject eingebettet werden.

```php
use Kalle\Pdf\Document\DefaultDocumentBuilder;
use Kalle\Pdf\Image\ImageColorSpace;
use Kalle\Pdf\Image\ImagePlacement;
use Kalle\Pdf\Image\ImageSource;

$document = DefaultDocumentBuilder::make()
    ->image(
        ImageSource::jpeg($jpegBytes, 600, 300, ImageColorSpace::RGB),
        ImagePlacement::at(40, 500, width: 180),
    )
    ->build();
```

## Links

Die erste Annotations-Anbindung unterstützt aktuell schlanke Link-Annotationen mit explizitem Rechteck auf der Seite, sowohl fuer externe URLs als auch fuer interne Spruenge auf andere Seiten, Zielpositionen oder Named Destinations. Text kann ausserdem direkt mit `TextOptions(link: ...)` oder mit mehreren unterschiedlich verlinkten `TextSegment`-Runs an Link-Annotationen gebunden werden. Bei PDF/UA-Profilen dient der letzte Parameter aktuell zugleich als Annotation-`/Contents` und als Alternativtext fuer die Link-Struktur.

```php
use Kalle\Pdf\Document\DefaultDocumentBuilder;
use Kalle\Pdf\Text\TextSegment;

$document = DefaultDocumentBuilder::make()
    ->text('Projektseite')
    ->link('https://example.com', 40, 500, 120, 16, 'Projektseite oeffnen')
    ->namedDestination('intro')
    ->newPage()
    ->linkToPage(1, 40, 500, 120, 16, 'Zurueck zur ersten Seite')
    ->linkToPagePosition(1, 72, 720, 40, 470, 160, 16, 'Zur Ueberschrift')
    ->text('Zur Einleitung', new \Kalle\Pdf\Text\TextOptions(
        link: \Kalle\Pdf\Page\LinkTarget::namedDestination('intro'),
    ))
    ->textSegments([
        new TextSegment('Docs', \Kalle\Pdf\Page\LinkTarget::externalUrl('https://example.com/docs')),
        new TextSegment(' und '),
        new TextSegment('API', \Kalle\Pdf\Page\LinkTarget::externalUrl('https://example.com/api')),
    ])
    ->build();
```

## Tabellen

Die erste Tabelleniteration unterstützt Textzellen mit festen oder proportionalen Spaltenbreiten, Padding, einfachen Borders, `colspan`/`rowspan`, optionale Caption- und Footer-Zeilen, optionale Header-Zeilen mit Wiederholung auf Folgeseiten und deterministische Seitenumbrüche zwischen ganzen Zeilen bzw. zusammenhängenden `rowspan`-Gruppen. Für Tagged-PDF-Profile wird zusätzlich eine minimale Tabellenstruktur mit `Table`, `Caption`, `TR`, `TH` und `TD` geschrieben.

```php
use Kalle\Pdf\Document\DefaultDocumentBuilder;
use Kalle\Pdf\Document\Table;
use Kalle\Pdf\Document\TableCaption;
use Kalle\Pdf\Document\TableColumn;
use Kalle\Pdf\Document\TableRow;
use Kalle\Pdf\Layout\Table\CellPadding;

$table = Table::define(
    TableColumn::fixed(120),
    TableColumn::proportional(1),
)
    ->withCaption(TableCaption::text('Produktuebersicht'))
    ->withCellPadding(CellPadding::all(6))
    ->withHeaderRows(
        TableRow::fromTexts('Artikel', 'Beschreibung'),
    )
    ->withRepeatedHeaderOnPageBreak()
    ->withRows(
        TableRow::fromCells(
            \Kalle\Pdf\Document\TableCell::text('A-100', rowspan: 2),
            \Kalle\Pdf\Document\TableCell::text('Kompakter Einstieg in das Tabellenlayout von pdf2.'),
        ),
        TableRow::fromCells(
            \Kalle\Pdf\Document\TableCell::text('Mit zusammenhängender Zeilengruppe über zwei Tabellenzeilen.'),
        ),
    )
    ->withFooterRows(
        TableRow::fromTexts('Summe', '2 Positionen'),
    );

$document = DefaultDocumentBuilder::make()
    ->table($table)
    ->build();
```

## Docker

Die Entwicklung kann innerhalb des Docker-Containers erfolgen. Der Projektordner wird per Bind-Mount nach `/app` eingebunden, dadurch sind lokale Dateien direkt im Container sichtbar.
Die `make`-Targets reichen dabei automatisch deine lokale `UID` und `GID` an Docker weiter, damit Dateien im gemounteten Projektordner nicht `root` gehören.

### Voraussetzungen

- Docker
- Docker Compose
- `make`

### Image bauen

Vor der ersten Nutzung das PHP-Image bauen:

```bash
make build
```

Wenn sich deine lokale Benutzer- oder Gruppen-ID geändert hat oder das Basis-Image aktualisiert wurde, das Image danach neu bauen:

```bash
make rebuild
```

### Arbeiten über Make

Eine Shell im Container starten:

```bash
make shell
```

Composer-Abhängigkeiten im Container installieren:

```bash
make composer-install
```

PHPStan im Container ausführen:

```bash
make phpstan
```

PHP-CS-Fixer im Container ausführen:

```bash
make cs
```

PHP-CS-Fixer im Prüfmodus ausführen:

```bash
make cs-check
```

PHPUnit im Container ausführen:

```bash
make test
```

veraPDF im Container ausführen:

```bash
make verapdf-version
make validate-pdfa PDF=var/example.pdf
make validate-pdfua PDF=var/example.pdf
make test-pdfa1b-regression
make check-pdf PDF=var/example.pdf
```

Alternativ direkt über die Skripte:

```bash
bin/validate-pdfa.sh var/example.pdf
bin/validate-pdfua.sh var/example.pdf
bin/test-pdfa1b-regression.sh
```

Compose-Services starten:

```bash
make up
```

Compose-Services stoppen:

```bash
make down
```

### PHP-Version prüfen

Die verlässliche Prüfung der Container-Version ist:

```bash
make php-version
```

Ein lokales `php -v` prüft dagegen nur die Host-Installation.
