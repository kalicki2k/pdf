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

Die erste Annotations-Anbindung unterstützt aktuell schlanke Link-Annotationen mit explizitem Rechteck auf der Seite, sowohl fuer externe URLs als auch fuer interne Spruenge auf andere Seiten, Zielpositionen oder Named Destinations. Text kann ausserdem direkt mit `TextOptions(link: ...)` oder mit mehreren unterschiedlich verlinkten `TextSegment`-Runs an Link-Annotationen gebunden werden. Fuer explizitere Inline-Link-Spans steht `TextLink` zur Verfuegung, damit sichtbarer Text, Annotation-`/Contents`, PDF/UA-Alternativtext und Gruppierung getrennt steuerbar bleiben.

Fuer einfache Kommentar-Notizen gibt es ausserdem eine kleine `Text`-Annotation mit festem Rechteck und eigenem `/AP`-Stream, die sich damit auch fuer den aktuellen PDF/A-2u-Pfad eignet. Dasselbe gilt jetzt fuer eine schlanke `Highlight`-Annotation mit festen `QuadPoints`.

```php
use Kalle\Pdf\Document\DefaultDocumentBuilder;
use Kalle\Pdf\Page\LinkAnnotationOptions;
use Kalle\Pdf\Text\TextLink;
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
        TextSegment::link(
            'Docs',
            TextLink::externalUrl(
                'https://example.com/docs',
                contents: 'Open docs section',
                accessibleLabel: 'Read the documentation section',
                groupKey: 'docs-link',
            ),
        ),
        new TextSegment(' und '),
        TextSegment::link('API', TextLink::externalUrl('https://example.com/api')),
    ])
    ->linkWithOptions(
        'https://example.com/spec',
        40,
        500,
        140,
        16,
        new LinkAnnotationOptions(
            contents: 'Open specification',
            accessibleLabel: 'Read the specification document',
            groupKey: 'spec-link',
        ),
    )
    ->textAnnotation(40, 450, 18, 18, 'Kurzer Kommentar', 'QA', 'Comment', true)
    ->highlightAnnotation(40, 420, 120, 12, \Kalle\Pdf\Color\Color::rgb(1, 1, 0), 'Markierung', 'QA')
    ->build();
```

## Tabellen

Die erste Tabelleniteration unterstützt Textzellen mit festen oder proportionalen Spaltenbreiten, Padding, einfachen Borders, `colspan`/`rowspan`, optionale Caption- und Footer-Zeilen, optionale Header-Zeilen mit Wiederholung auf Folgeseiten und deterministische Seitenumbrüche zwischen ganzen Zeilen bzw. zusammenhängenden `rowspan`-Gruppen. Für Tagged-PDF-Profile wird zusätzlich eine minimale Tabellenstruktur mit `Table`, `Caption`, `TR`, `TH` und `TD` geschrieben.

```php
use Kalle\Pdf\Document\DefaultDocumentBuilder;
use Kalle\Pdf\Document\Table;
use Kalle\Pdf\Document\TableCaption;
use Kalle\Pdf\Document\TableColumn;
use Kalle\Pdf\Document\TableHeaderScope;
use Kalle\Pdf\Document\TablePlacement;
use Kalle\Pdf\Document\TableRow;
use Kalle\Pdf\Layout\Table\Border;
use Kalle\Pdf\Layout\Table\CellPadding;
use Kalle\Pdf\Text\TextAlign;

$table = Table::define(
    TableColumn::fixed(120),
    TableColumn::proportional(1),
)
    ->withCaption(TableCaption::text('Produktuebersicht'))
    ->withPlacement(new TablePlacement(72, 320))
    ->withCellPadding(CellPadding::all(6))
    ->withHeaderRows(
        TableRow::fromTexts('Artikel', 'Beschreibung'),
    )
    ->withRepeatedHeaderOnPageBreak()
    ->withRows(
        TableRow::fromCells(
            \Kalle\Pdf\Document\TableCell::text('A-100', rowspan: 2)
                ->withHeaderScope(TableHeaderScope::ROW)
                ->withPadding(CellPadding::symmetric(8, 10)),
            \Kalle\Pdf\Document\TableCell::text('Kompakter Einstieg in das Tabellenlayout von pdf2.')
                ->withHorizontalAlign(TextAlign::RIGHT)
                ->withBorder(Border::all(1)),
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

Ein umfangreicheres Beispiel liegt in `examples/table.php`.

## Verschluesselung

Die aktuelle Encryption-API bleibt bewusst klein: Das Dokument bekommt ein explizites `Encryption`-Value-Object. Unterstuetzt sind aktuell `RC4-128`, `AES-128` und `AES-256` sowie eine kleine `Permissions`-API. PDF/A-Profile verbieten weiterhin Verschluesselung und scheitern deshalb mit einer klaren Exception.

```php
use Kalle\Pdf\Document\DefaultDocumentBuilder;
use Kalle\Pdf\Document\Profile;
use Kalle\Pdf\Encryption\Encryption;
use Kalle\Pdf\Encryption\Permissions;

$document = DefaultDocumentBuilder::make()
    ->profile(Profile::pdf17())
    ->title('Encrypted Example')
    ->author('Kalle PDF')
    ->encryption(
        Encryption::aes256('user-secret', 'owner-secret')->withPermissions(
            new Permissions(
                print: false,
                modify: true,
                copy: false,
                annotate: true,
            ),
        ),
    )
    ->text('Confidential content')
    ->build();
```

Ein kleines ausfuehrbares Beispiel liegt in `examples/encryption.php`. Die externe `qpdf`-Regression fuer Permissions ist ueber `bin/test-encryption-permissions-regression.sh` abgedeckt.

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
