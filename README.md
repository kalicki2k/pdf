# PDF2

## Struktur

Der Quellcode ist jetzt grob nach Verantwortlichkeiten organisiert:

```text
src/
├─ Color/
├─ Debug/
├─ Document/
├─ Drawing/
├─ Font/
├─ Image/
├─ Page/
├─ Text/
├─ Writer/
└─ Pdf.php
```

## Observability

Die Engine unterstuetzt ein fokussiertes Debug-/Observability-System fuer Lifecycle-, PDF-Struktur- und Performance-Events. Es erzeugt keine visuelle Debug-Ausgabe im PDF, sondern nur strukturierte Events ueber einen `DebugSink`.

```php
use Kalle\Pdf\Debug\DebugConfig;
use Kalle\Pdf\Document\Document;

$document = Document::make()
    ->title('Rechnung 2026-001')
    ->debug(
        DebugConfig::json()
            ->toStdout()
    )
    ->build();
```

- Lifecycle-Logging protokolliert Engine-Schritte wie `document.created`, `page.added`, `write.started` und `write.finished`.
- PDF-Struktur-Debugging protokolliert PDF-nahe Ereignisse wie `object.created`, `object.serialized`, `stream.serialized`, `xref.written` und `trailer.written`.
- Performance-Logging misst Render- und Write-Schritte wie `document.render`, `page.render` und `file.write` inklusive Laufzeit- und Speichermetriken.

Optional kann statt eines formatbasierten Sinks auch direkt ein PSR-3-Logger angebunden werden:

```php
use Kalle\Pdf\Debug\LogLevel;

$document = Document::make()
    ->debug(DebugConfig::make()->logLifecycle(LogLevel::Info))
    ->withLogger($logger)
    ->build();
```

Ein groesseres ausfuehrbares Beispiel mit zehn Seiten und JSON-Logger liegt in `examples/observability.php`.

Fuer Docker- oder Container-Setups kann direkt in `stdout` oder `stderr` geloggt werden:

```php
$document = Document::make()
    ->debug(
        DebugConfig::json()
            ->toStdout()
    )
    ->build();
```

Fuer strukturierte JSON-Lines ohne PSR-3 steht `JsonDebugSink` als direkte Low-Level-Option zur Verfuegung, zum Beispiel fuer Dateien, `stdout` oder `stderr`:

```php
$document = Document::make()
    ->debug(
        DebugConfig::json()
            ->toFile(__DIR__ . '/var/pdf-debug.log')
    )
    ->build();
```

Fuer lesbare Terminal- oder Datei-Ausgaben steht ausserdem `text()` zur Verfuegung:

```php
$document = Document::make()
    ->debug(
        DebugConfig::text()
            ->toStderr()
    )
    ->build();
```

Fuer Tests oder lokale Inspektion kann `InMemoryDebugSink` alle Events im Speicher sammeln:

```php
use Kalle\Pdf\Debug\InMemoryDebugSink;

$sink = new InMemoryDebugSink();
$document = Document::make()
    ->debug(DebugConfig::make()->sink($sink))
    ->build();

$records = $sink->records();
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

Die rechteckbasierten Annotationen (`Link`, `Text`, `Highlight`) nutzen ausserdem jetzt ein kleines gemeinsames Metadaten-Fundament ueber `AnnotationMetadata`, auf das die jeweiligen `...Options`-Value-Objects aufsetzen.

Fuer einfache Kommentar-Notizen gibt es ausserdem eine kleine `Text`-Annotation mit festem Rechteck und eigenem `/AP`-Stream, die sich damit auch fuer den aktuellen PDF/A-2u-Pfad eignet. Dasselbe gilt jetzt fuer eine schlanke `Highlight`-Annotation mit festen `QuadPoints` und fuer `FreeText`, das seinen Appearance-Stream mit dem verwendeten Seitenfont rendert.

```php
use Kalle\Pdf\Document\DefaultDocumentBuilder;
use Kalle\Pdf\Page\FreeTextAnnotationOptions;
use Kalle\Pdf\Page\HighlightAnnotationOptions;
use Kalle\Pdf\Page\LinkAnnotationOptions;
use Kalle\Pdf\Page\TextAnnotationOptions;
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
    ->textAnnotationWithOptions(40, 450, 18, 18, 'Kurzer Kommentar', new TextAnnotationOptions(
        title: 'QA',
        icon: 'Comment',
        open: true,
    ))
    ->highlightAnnotationWithOptions(40, 420, 120, 12, new HighlightAnnotationOptions(
        color: \Kalle\Pdf\Color\Color::rgb(1, 1, 0),
        contents: 'Markierung',
        title: 'QA',
    ))
    ->freeTextAnnotation(
        'Kommentar im Dokument',
        40,
        380,
        140,
        36,
        new \Kalle\Pdf\Text\TextOptions(fontSize: 12, color: \Kalle\Pdf\Color\Color::rgb(0, 0, 0.4)),
        \Kalle\Pdf\Color\Color::rgb(0.2, 0.2, 0.2),
        \Kalle\Pdf\Color\Color::rgb(1, 1, 0.8),
        'QA',
    )
    ->freeTextAnnotationWithOptions(
        'Zweiter Kommentar',
        40,
        330,
        140,
        36,
        new \Kalle\Pdf\Text\TextOptions(fontSize: 12),
        new FreeTextAnnotationOptions(
            textColor: \Kalle\Pdf\Color\Color::rgb(0, 0, 0.4),
            borderColor: \Kalle\Pdf\Color\Color::rgb(0.2, 0.2, 0.2),
            fillColor: \Kalle\Pdf\Color\Color::rgb(1, 1, 0.8),
            metadata: new \Kalle\Pdf\Page\AnnotationMetadata(title: 'QA'),
        ),
    )
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
use Kalle\Pdf\Text\TextLink;
use Kalle\Pdf\Text\TextSegment;

$table = Table::define(
    TableColumn::fixed(120),
    TableColumn::proportional(1),
)
    ->withCaption(TableCaption::text('Produktuebersicht'))
    ->withPlacement(TablePlacement::at(72, 520, 320))
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
            \Kalle\Pdf\Document\TableCell::segments(
                TextSegment::plain('Kompakter Einstieg in das Tabellenlayout von pdf2. '),
                TextSegment::link('Mehr dazu', TextLink::externalUrl('https://example.com/docs/tables')),
            )
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

Ein umfangreicheres Beispiel liegt in `examples/table.php`. Die aktuelle Tabellen-API deckt damit auch absolutes Start-`y`, Rich-Text-Zellen sowie Tagged-Table-Abschnitte `THead`, `TBody` und `TFoot` ab.

## Listen

Listen laufen ueber genau einen Builder-Einstieg. `ListOptions` steuert nur das Listenverhalten, `TextOptions` weiterhin das Textlayout.

```php
use Kalle\Pdf\Document\DefaultDocumentBuilder;
use Kalle\Pdf\Document\ListOptions;
use Kalle\Pdf\Document\ListType;
use Kalle\Pdf\Text\TextOptions;

$document = DefaultDocumentBuilder::make()
    ->list(
        ['Erster Punkt', 'Zweiter Punkt'],
        new ListOptions(type: ListType::BULLET),
        new TextOptions(x: 72, y: 720, width: 220, fontSize: 12),
    )
    ->list(
        ['Schritt eins', 'Schritt zwei'],
        new ListOptions(type: ListType::NUMBERED, start: 3, marker: '%d)'),
        new TextOptions(width: 220, fontSize: 12, spacingAfter: 12),
    )
    ->build();
```

## PDF/A-1a Umfang

Der aktuell abgesicherte `PDF/A-1a`-Pfad in `pdf2` deckt bewusst einen klar begrenzten Strukturumfang ab: Ueberschriften und Absaetze (`H1` bis `H6`, `P`), Listen (`L`, `LI`, `Lbl`, `LBody`), Tabellen (`Table`, `Caption`, `TR`, `TH`, `TD`), Bilder mit Alternativtext als `Figure` und einfache Link-Annotationen. Diese Kombination ist ueber die `PDF/A-1a`-Regressionen sowie ueber Renderer- und Builder-Tests abgesichert.

Nicht Teil dieses Umfangs sind aktuell reichere Annotationstypen, Formulare, Signaturfelder oder weitergehende Strukturtypen ausserhalb dieses Satzes. Solche Faelle sollen fuer `PDF/A-1a` weiter explizit scheitern oder erst nach eigener Regressionserweiterung freigegeben werden.

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

## Attachments

Dokumentweite eingebettete Dateien koennen direkt ueber den Builder registriert werden. Der aktuelle Stand unterstuetzt nur dokumentweite Associated Files am Catalog (`/AF`), keine objekt- oder seitennahe Zuordnung. Fuer PDF/A-3 und PDF/A-4f werden solche Dokument-Attachments standardmaessig als Associated Files mit `AFRelationship /Data` serialisiert, solange keine explizite Beziehung gesetzt wird. Standard-PDF 2.0 kann dokumentweite Associated Files mit explizitem `AFRelationship` ebenfalls serialisieren.

```php
use Kalle\Pdf\Document\Attachment\AssociatedFileRelationship;
use Kalle\Pdf\Document\DefaultDocumentBuilder;
use Kalle\Pdf\Document\Profile;

$document = DefaultDocumentBuilder::make()
    ->profile(Profile::pdfA3b())
    ->title('Invoice with source data')
    ->attachment(
        'invoice.xml',
        '<invoice id="2026-001"/>',
        'Machine-readable source data',
        'application/xml',
        AssociatedFileRelationship::DATA,
    )
    ->text('Human-readable invoice preview')
    ->build();
```

Alternativ laesst sich eine Datei direkt von der Platte einlesen:

```php
$document = DefaultDocumentBuilder::make()
    ->attachmentFromFile(
        __DIR__ . '/fixtures/source-data.xml',
        description: 'Imported XML payload',
        mimeType: 'application/xml',
    )
    ->build();
```

## Formulare

Die aktuelle AcroForm-API deckt fuer Standard-PDFs Textfelder, Checkboxen, Radio Buttons, ComboBoxen, ListBoxen, Push Buttons und Signaturfelder ab. Fuer PDF/UA-1 ist jetzt der erste Tagged-Form-Structure-Schritt fuer Single-Widget-Felder umgesetzt: Textfelder, Checkboxen, ComboBoxen, ListBoxen, Push Buttons und Signaturfelder werden als `/Form`-Strukturelemente mit `OBJR`/`ParentTree` serialisiert. Radio-Button-Gruppen bleiben im aktuellen Stand bewusst gesperrt, bis ihre Mehrfach-Widget-Struktur vollstaendig abgedeckt ist. Sichtbare Signaturfelder werden direkt ueber die Formular-API erzeugt; die kryptographische Signaturintegration fuer unterstuetzte Dokumente ist unten als separater Signier-Schritt beschrieben.

```php
use Kalle\Pdf\Document\DefaultDocumentBuilder;

$document = DefaultDocumentBuilder::make()
    ->text('Customer details')
    ->textField('customer_name', 40, 720, 180, 18, 'Ada Lovelace', 'Customer name')
    ->checkbox('accept_terms', 40, 680, 14, true, 'Accept terms')
    ->radioButton('delivery', 'standard', 40, 640, 12, true, groupAlternativeName: 'Delivery method')
    ->radioButton('delivery', 'express', 80, 640, 12, alternativeName: 'Express delivery')
    ->comboBox('status', 40, 600, 140, 18, ['new' => 'New', 'done' => 'Done'], 'done', 'Status')
    ->listBox('skills', 40, 540, 140, 48, ['php' => 'PHP', 'pdf' => 'PDF'], ['php', 'pdf'], 'Skills')
    ->pushButton('open_docs', 'Open docs', 40, 470, 120, 20, 'Open documentation', 'https://example.com/docs')
    ->signatureField('approval_signature', 40, 420, 140, 28, 'Approval signature')
    ->build();
```

## PDF-Signaturen

Kryptographische PDF-Signaturen koennen fuer vorhandene `signatureField(...)`-Widgets direkt ueber die OpenSSL-PHP-Erweiterung erzeugt werden. Der aktuelle Stand unterstuetzt detached CMS-/PKCS#7-Signaturen fuer unverschluesselte Standard-PDFs. Verschluesselte Dokumente und mehrere inkrementelle Signaturrunden sind bewusst noch nicht Teil der API.

```php
use Kalle\Pdf\Document\Signature\OpenSslPemSigningCredentials;
use Kalle\Pdf\Document\Signature\PdfSignatureOptions;
use Kalle\Pdf\Pdf;

$document = Pdf::document()
    ->text('Approval')
    ->signatureField('approval_signature', 40, 420, 140, 28, 'Approval signature')
    ->build();

$signedPdf = Pdf::signedContents(
    $document,
    new OpenSslPemSigningCredentials($certificatePem, $privateKeyPem, $privateKeyPassphrase),
    new PdfSignatureOptions(
        fieldName: 'approval_signature',
        signerName: 'Ada Lovelace',
        reason: 'Approval',
        location: 'Berlin',
        contactInfo: 'ada@example.com',
    ),
);
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
make test-pdfa1b-regressions
make test-pdfa1a-regression
make test-pdfa1a-list-regression
make test-pdfa1a-table-regression
make test-pdfa1a-mixed-regression
make test-pdfa1a-multipage-regression
make check-pdf PDF=var/example.pdf
```

Alternativ direkt über die Skripte:

```bash
bin/validate-pdfa.sh var/example.pdf
bin/validate-pdfua.sh var/example.pdf
bin/test-pdfa1b-regression.sh
bin/test-pdfa1b-regressions.sh
bin/test-pdfa1a-regression.sh
bin/test-pdfa1a-list-regression.sh
bin/test-pdfa1a-table-regression.sh
bin/test-pdfa1a-mixed-regression.sh
bin/test-pdfa1a-multipage-regression.sh
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
