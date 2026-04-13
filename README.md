# PDF2

## PDF/A Scope

Der aktuelle PDF/A-Scope ist bewusst konservativ und folgt eher dem Prinzip "hart blocken statt halb erlauben":

- `PDF/A-1b`: stabil fuer den aktuell freigegebenen Scope mit eingebetteten Fonts, XMP/Info-Metadaten, OutputIntent, Annotation-APs und den geprueften Farbpfaden.
- `PDF/A-1a`: bewusst enger als das volle Normspektrum. Im Formularpfad sind nur `TextField`, `ComboBoxField` und `ListBoxField` freigegeben. Popup-Related-Objects sowie URI-Annotation-Actions sind im PDF/A-1-Pfad explizit verboten.
- `PDF/A-2b`: explizit freigegeben fuer denselben kleinen Annotation-Scope wie `PDF/A-2u`, aber ohne Unicode-Extraktionspflicht. Freigegeben sind aktuell `Link`, `Text`, `Highlight` und `FreeText`. Popup-Related-Objects, Seiten-Dateianhang-Annotationen und AcroForm-Felder bleiben gesperrt.
- `PDF/A-2a`: aktuell bewusst nicht freigegeben. Der allgemeine Tagged-/A-Scope ausserhalb von `PDF/A-1a` wird noch nicht als belastbar genug eingestuft und wird deshalb hart blockiert.
- `PDF/A-2u`: robuster Positivpfad fuer Unicode-Fonts, Metadaten, OutputIntent und einen bewusst kleinen Annotation-Scope. Freigegeben sind aktuell `Link`, `Text`, `Highlight` und `FreeText`. Externe `URI`-Links sind in diesem Profil ausdruecklich erlaubt. Popup-Related-Objects, Seiten-Dateianhang-Annotationen und AcroForm-Felder sind im aktuellen PDF/A-2-Scope gesperrt.
- `PDF/A-3b`: dokumentweite Embedded Files und Associated Files am Catalog sind im aktuellen Scope abgedeckt. Erlaubt sind dokumentweite Associated Files am Catalog, nicht aber seitennahe Dateianhang-Annotationen, Popup-Related-Objects oder AcroForm-Felder.
- `PDF/A-3a`: aktuell bewusst nicht freigegeben. Der allgemeine Tagged-/A-Scope ausserhalb von `PDF/A-1a` wird noch nicht als belastbar genug eingestuft und wird deshalb hart blockiert.
- `PDF/A-3u`: erweitert den aktuellen `PDF/A-3b`-Scope um den extractable-Unicode-Font-Pfad. Dokumentweite Associated Files am Catalog bleiben freigegeben; seitennahe Dateianhang-Annotationen, Popup-Related-Objects und AcroForm-Felder bleiben gesperrt.
- `PDF/A-4`: aktuell bewusst nicht freigegeben. Der PDF-2.0-basierte PDF/A-4-Scope ist noch nicht als belastbare Capability-Matrix modelliert und wird deshalb hart blockiert.
- `PDF/A-4e`: aktuell bewusst nicht freigegeben. Die zusaetzlichen Engineering-Anforderungen von PDF/A-4e sind noch nicht implementiert und werden deshalb hart blockiert.
- `PDF/A-4f`: aktuell bewusst nicht freigegeben. Teile des Attachment-Plumbings existieren bereits, aber der vollstaendige PDF/A-4f-Scope ist noch nicht sauber validiert und wird deshalb hart blockiert.

Die Engine validiert PDF/A-1 inzwischen nicht nur auf vorbereiteten Zwischenstrukturen, sondern auch gegen den finalen Objektgraphen vor dem Schreiben. Fuer PDF/A-2/3 laeuft derselbe finale Objektgraph-Check inzwischen fuer den gemeinsamen Catalog-, Metadata-, OutputIntent-, Attachment- und Seitenpfad. Die PDF/A-Regressionsskripte pruefen die geschriebenen Dateien zusaetzlich mit `qpdf --check`, bevor veraPDF laeuft.

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

## Graphics

`pdf2` stellt jetzt eine kleine explizite Public API fuer grafische Primitive bereit. Die erste Iteration deckt Linie, Rechteck, Rounded Rectangle und freie Pfade ab und bleibt bewusst konservativ: keine Raw-Content-Injection, keine Opacity-API und keine breite Formensammlung aus dem Altprojekt `pdf(1)`.

```php
use Kalle\Pdf\Color\Color;
use Kalle\Pdf\Document\DefaultDocumentBuilder;
use Kalle\Pdf\Drawing\GraphicsAccessibility;
use Kalle\Pdf\Drawing\Path;
use Kalle\Pdf\Drawing\StrokeStyle;

$triangle = Path::builder()
    ->moveTo(60, 640)
    ->lineTo(120, 720)
    ->lineTo(180, 640)
    ->close()
    ->build();

$document = DefaultDocumentBuilder::make()
    ->line(40, 760, 200, 760, new StrokeStyle(1.5, Color::gray(0.25)))
    ->rectangle(40, 680, 120, 50, fillColor: Color::hex('#dbeafe'))
    ->roundedRectangle(180, 680, 120, 50, 10, new StrokeStyle(1, Color::hex('#1d4ed8')))
    ->path(
        $triangle,
        new StrokeStyle(1, Color::hex('#991b1b')),
        Color::hex('#fecaca'),
        GraphicsAccessibility::alternativeText('Warning triangle'),
    )
    ->build();
```

In Tagged-PDF-Profilen rendert die Graphics-API standardmaessig weiter als `/Artifact`. Semantisch relevante Vektorgrafik kann explizit ueber `GraphicsAccessibility::alternativeText(...)` als `/Figure` in die Tagged-Struktur aufgenommen werden; dekorative Grafik bleibt Artifact.

## Header und Footer

Dokumentweite Seitendekorationen koennen ueber `header()` und `footer()` registriert werden. Beide Callbacks laufen fuer jede erzeugte Seite, auch bei `newPage()` und automatischen Seitenumbruechen. Sie rendern in einem isolierten Seitendekorations-Context und greifen daher nicht in den normalen Content-Flow ein.

Ausführbare Beispiele liegen in `examples/header-footer.php` und `examples/header-footer-filters.php`.

- Signatur: `static function (PageDecorationContext $page, int $pageNumber): void`
- Reihenfolge pro Seite: Header, regulaerer Seiteninhalt, Footer
- Der Context stellt ueber `$page->page()` die aktuelle Seite und deren `contentArea()` bereit.
- Die Seitennummer ist 1-basiert.
- Fuer Seitennummern wie `Seite X von Y` steht zusaetzlich `$page->totalPages()` bereit.
- Bedingte Dekorationen koennen direkt im Callback ueber `$page->isFirstPage()`, `$page->isLastPage()` oder `$page->pageNumber()` umgesetzt werden.
- Fuer haeufige Faelle gibt es ausserdem `pageNumbers(...)` sowie praedikatbasierte Varianten `headerOn(...)` und `footerOn(...)`.
- In Tagged-PDF-Profilen werden Header/Footer als Artefakt behandelt und nicht in die logische Dokumentstruktur aufgenommen.

```php
use Kalle\Pdf\Document\PageDecorationContext;
use Kalle\Pdf\Text\TextOptions;

$document = DefaultDocumentBuilder::make()
    ->header(static function (PageDecorationContext $page, int $pageNumber): void {
        $page->text('Projektstatus', new TextOptions(
            x: $page->page()->contentArea()->left,
            y: $page->page()->contentArea()->top,
            fontSize: 12,
        ));
    })
    ->footer(static function (PageDecorationContext $page, int $pageNumber): void {
        $page->text('Seite ' . $pageNumber, new TextOptions(
            x: $page->page()->contentArea()->left,
            y: $page->page()->contentArea()->bottom + 12,
            fontSize: 10,
        ));
    })
    ->text('Langer Inhalt ...')
    ->build();
```

```php
$document = DefaultDocumentBuilder::make()
    ->pageNumbers(
        new TextOptions(x: 40, y: 20, fontSize: 10),
        'Seite {{page}} von {{pages}}',
    )
    ->headerOn(
        static fn (PageDecorationContext $page): bool => !$page->isFirstPage(),
        static function (PageDecorationContext $page): void {
            $page->text('Kapitelkopf', new TextOptions(
                x: $page->page()->contentArea()->left,
                y: $page->page()->contentArea()->top,
                fontSize: 12,
            ));
        },
    )
    ->text('Langer Inhalt ...')
    ->build();
```

## Links

Die erste Annotations-Anbindung unterstützt aktuell schlanke Link-Annotationen mit explizitem Rechteck auf der Seite, sowohl fuer externe URLs als auch fuer interne Spruenge auf andere Seiten, Zielpositionen oder Named Destinations. Text kann ausserdem direkt mit `TextOptions(link: ...)` oder mit mehreren unterschiedlich verlinkten `TextSegment`-Runs an Link-Annotationen gebunden werden. Fuer explizitere Inline-Link-Spans steht `TextLink` zur Verfuegung, damit sichtbarer Text, Annotation-`/Contents`, PDF/UA-Alternativtext und Gruppierung getrennt steuerbar bleiben.

Fuer explizite Zeilen ohne eingebettete Newline-Strings steht ausserdem `textLines(...)` zur Verfuegung:

```php
$document = DefaultDocumentBuilder::make()
    ->textLines([
        'DEIN FIRMENNAME',
        'Strasse Hausnummer',
        '12345 Musterstadt',
    ], new TextOptions(fontSize: 12))
    ->build();
```

Explizite Tagged-PDF-Leaf-Rollen fuer Text koennen direkt ueber `TextOptions(tag: ...)` gesetzt werden:

```php
$document = DefaultDocumentBuilder::make()
    ->profile(\Kalle\Pdf\Document\Profile::pdfA1a())
    ->title('Archive Copy')
    ->language('de-DE')
    ->text('Zitat', new TextOptions(
        embeddedFont: \Kalle\Pdf\Font\EmbeddedFontSource::fromPath('/path/to/font.ttf'),
        tag: \Kalle\Pdf\Document\TaggedPdf\TaggedStructureTag::BLOCK_QUOTE,
    ))
    ->build();
```

Dekorativer Text wie Briefkopf, Seitenkopf oder Wasserzeichen kann ueber `TextOptions(semantic: TextSemantic::ARTIFACT)` aus der logischen Struktur herausgenommen werden:

```php
$document = DefaultDocumentBuilder::make()
    ->profile(\Kalle\Pdf\Document\Profile::pdfA1a())
    ->title('Archive Copy')
    ->language('de-DE')
    ->textLines(['DEIN FIRMENNAME', 'Strasse Hausnummer'], new TextOptions(
        semantic: \Kalle\Pdf\Text\TextSemantic::ARTIFACT,
    ))
    ->build();
```

Dokumentweite PDF-Outlines/Bookmarks werden ebenfalls unterstuetzt. Neben Top-Level-Bookmarks koennen Outlines explizit verschachtelt, offen oder geschlossen markiert und mit Stilinformationen versehen werden. Zusaetzlich zu `XYZ` werden auch `Fit`, `FitH`, `FitR`, benannte Ziele sowie lokale und externe `GoTo`-Actions unterstuetzt.

```php
use Kalle\Pdf\Document\DefaultDocumentBuilder;
use Kalle\Pdf\Document\Outline;
use Kalle\Pdf\Document\OutlineStyle;
use Kalle\Pdf\Color\Color;

$document = DefaultDocumentBuilder::make()
    ->namedDestination('intro')
    ->outline('Start')
    ->text('Seite 1')
    ->newPage()
    ->outlineAt('Kapitel 1', 2)
    ->outlineChild('Abschnitt 1.1')
    ->outlineSiblingClosed('Abschnitt 1.2')
    ->addOutline(
        Outline::fitHorizontal('Anhang', 3, 720)
            ->withStyle((new OutlineStyle())->withColor(Color::hex('#1d4ed8'))->withBold())
            ->asGoToAction(),
    )
    ->addOutline(Outline::named('Einleitung', 'intro', 1))
    ->addOutline(Outline::fit('Externes PDF', 4)->withDestination(
        Outline::fit('Externes PDF', 4)->destination->asRemoteGoTo('appendix.pdf', true),
    ))
    ->text('Seite 2')
    ->build();
```

Die rechteckbasierten Annotationen (`Link`, `Text`, `Highlight`) nutzen ausserdem jetzt ein kleines gemeinsames Metadaten-Fundament ueber `AnnotationMetadata`, auf das die jeweiligen `...Options`-Value-Objects aufsetzen. Dasselbe Muster deckt inzwischen auch weitere Markup- und Geometrie-Typen wie `Underline`, `StrikeOut`, `Squiggly`, `Stamp`, `Square`, `Circle`, `Caret`, `Ink`, `Line`, `PolyLine` und `Polygon` ab. Popups koennen ueber `popupAnnotation(...)` weiterhin an die zuletzt hinzugefuegte kompatible Seitenannotation gebunden werden oder explizit ueber `lastPageAnnotationReference()` plus `popupAnnotationFor(...)`. Seitengebundene Dateianhaenge setzen auf derselben Builder-API auf und koennen entweder neue Attachments anlegen oder vorhandene Dokument-Attachments ueber `existingFileAttachmentAnnotation(...)` wiederverwenden.

Fuer einfache Kommentar-Notizen gibt es ausserdem eine kleine `Text`-Annotation mit festem Rechteck und eigenem `/AP`-Stream, die sich damit auch fuer den aktuellen PDF/A-2u-Pfad eignet. Dasselbe gilt jetzt fuer eine schlanke `Highlight`-Annotation mit festen `QuadPoints` und fuer `FreeText`, das seinen Appearance-Stream mit dem verwendeten Seitenfont rendert.

```php
use Kalle\Pdf\Document\DefaultDocumentBuilder;
use Kalle\Pdf\Page\FreeTextAnnotationOptions;
use Kalle\Pdf\Page\HighlightAnnotationOptions;
use Kalle\Pdf\Page\LinkAnnotationOptions;
use Kalle\Pdf\Page\TextAnnotationOptions;
use Kalle\Pdf\Text\TextLink;
use Kalle\Pdf\Text\TextSegment;

$builder = DefaultDocumentBuilder::make()
    ->text('Projektseite')
    ->link('https://example.com', 40, 500, 120, 16, 'Projektseite oeffnen')
    ->namedDestination('intro')
    ->newPage()
    ->linkToPage(1, 40, 500, 120, 16, 'Zurueck zur ersten Seite')
    ->linkToPagePosition(1, 72, 720, 40, 470, 160, 16, 'Zur Ueberschrift')
    ->text('Zur Einleitung', new \Kalle\Pdf\Text\TextOptions(
        link: \Kalle\Pdf\Page\LinkTarget::namedDestination('intro'),
    ))
    ->text([
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
    ->underlineAnnotation(40, 400, 120, 12, \Kalle\Pdf\Color\Color::rgb(1, 0, 0), 'Unterstrichen', 'QA')
    ->squareAnnotationWithOptions(40, 350, 140, 36, new \Kalle\Pdf\Page\ShapeAnnotationOptions(
        borderColor: \Kalle\Pdf\Color\Color::rgb(0.8, 0.1, 0.1),
        fillColor: \Kalle\Pdf\Color\Color::rgb(1, 0.98, 0.9),
        borderStyle: \Kalle\Pdf\Page\AnnotationBorderStyle::dashed(1.5),
        contents: 'Bereichshinweis',
        title: 'QA',
        subject: 'Freigabebereich',
    ))
    ->lineAnnotationWithOptions(40, 310, 180, 330, new \Kalle\Pdf\Page\LineAnnotationOptions(
        color: \Kalle\Pdf\Color\Color::rgb(0, 0.2, 0.8),
        startStyle: \Kalle\Pdf\Page\LineEndingStyle::CIRCLE,
        endStyle: \Kalle\Pdf\Page\LineEndingStyle::CLOSED_ARROW,
        metadata: new \Kalle\Pdf\Page\AnnotationMetadata(
            contents: 'Verweislinie',
            title: 'QA',
            subject: 'Pruefpfad',
        ),
    ));

$lineAnnotation = $builder->lastPageAnnotationReference();

$document = $builder
    ->popupAnnotationFor($lineAnnotation, 190, 300, 160, 70, true)
    ->attachment('demo.txt', 'hello', 'Demo attachment', 'text/plain')
    ->existingFileAttachmentAnnotation(
        'demo.txt',
        40,
        270,
        12,
        14,
        'Graph',
        'Anhang',
    )
    ->fileAttachmentAnnotationWithOptions(
        'demo.txt',
        new \Kalle\Pdf\Document\Attachment\EmbeddedFile('hello', 'text/plain'),
        70,
        270,
        12,
        14,
        new \Kalle\Pdf\Page\FileAttachmentAnnotationOptions(
            description: 'Demo attachment',
            icon: 'Graph',
            contents: 'Anhang',
        ),
    )
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

Die aktuelle `pdf2`-Implementierung deckt damit nun auch Popups und seitengebundene Dateianhang-Annotationen ab. Fuer PDF/UA-1 werden neben Links und Formularfeldern jetzt auch allgemeine Seitenannotationen als getaggte `Annot`-StructElems mit `/OBJR`, `/StructParent` und Alternativtext geschrieben. Die Freigabe bleibt dabei bewusst streng und explizit: nur Annotationtypen mit dediziertem PDF/UA-Opt-in werden in diesem Pfad akzeptiert, und fehlt fuer eine solche Annotation ein brauchbarer Alternativtext, scheitert `pdf2` weiterhin explizit.

```php
use Kalle\Pdf\Document\DefaultDocumentBuilder;
use Kalle\Pdf\Document\TableOfContents\TableOfContentsLeaderStyle;
use Kalle\Pdf\Document\TableOfContents\TableOfContentsOptions;
use Kalle\Pdf\Document\TableOfContents\TableOfContentsPlacement;
use Kalle\Pdf\Document\TableOfContents\TableOfContentsStyle;

$document = DefaultDocumentBuilder::make()
    ->outline('Start')
    ->text('Seite 1')
    ->newPage()
    ->outlineAt('Details', 2, 72, 640)
    ->text('Seite 2')
    ->tableOfContents(new TableOfContentsOptions(
        placement: TableOfContentsPlacement::start(),
        style: new TableOfContentsStyle(
            leaderStyle: TableOfContentsLeaderStyle::DOTS,
        ),
    ))
    ->build();
```

Alternativ koennen TOC-Eintraege explizit ueber `tableOfContentsEntry(...)` oder `tableOfContentsEntryAt(...)` registriert werden. In der aktuellen ersten Iteration verwendet die TOC entweder die expliziten TOC-Eintraege oder, wenn keine gesetzt sind, die vorhandenen Outlines. Platzierungen am Anfang, Ende oder nach einer bestimmten Seite werden unterstuetzt; logische Seitennummerierung und Header/Footer auf automatisch erzeugten TOC-Seiten sind gegenueber dem Altprojekt noch nicht uebernommen.

Ein ausfuehrbares Beispiel liegt in `examples/table-of-contents.php`.

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

Der folgende Umfang ist der offizielle `PDF/A-1a`-Support-Scope der aktuellen `pdf2`-Version. Abgesichert und freigegeben sind Ueberschriften und Absaetze (`H1` bis `H6`, `P`), zusaetzliche Text-Strukturtypen (`BibEntry`, `BlockQuote`, `Code`, `Em`, `Note`, `Quote`, `Reference`, `Span`, `Strong`, `Title`), frei modellierbare Struktur-Container (`Art`, `BlockQuote`, `Div`, `Index`, `NonStruct`, `Note`, `Part`, `Private`, `Sect`, `TOC`, `TOCI`), Listen (`L`, `LI`, `Lbl`, `LBody`), Tabellen (`Table`, `Caption`, `TR`, `TH`, `TD`), Bilder mit Alternativtext als `Figure`, getaggte Seitenannotationen als `Annot`, getaggte Formular-/Widgetfelder als `Form` sowie Link-Annotationen. Dokumente in diesem Pfad brauchen ausserdem einen gesetzten Sprachwert auf Dokumentebene (`/Lang`). Fuer diese Struktur prueft der Build-Pfad jetzt nicht nur die Existenz von Tagged Content, sondern auch die Konsistenz von `StructTreeRoot`, Dokumentwurzel, `ParentTree`, `/StructParents`, `MCID`-Zuordnung und der unterstuetzten Strukturelement-Hierarchie. Interne `PDF/A-1a`-Dokumentmodelle mit nicht freigegebenen Strukturtypen, mit leeren Tagged-Containern/Listen/Tabellen oder mit inkonsistenten Tagged-Referenzen werden explizit verworfen. Fuer Annotationen und Formularfelder erzwingt der `PDF/A-1a`-Pfad Alternativtexte, `/StructParent`-Eintraege und `OBJR`-basierte Strukturreferenzen.

Der aktuelle Formularumfang im offiziell freigegebenen `PDF/A-1a`-Pfad ist bewusst enger und umfasst nur `TextField`, `ComboBoxField` und `ListBoxField`. Checkboxen, Radio-Button-Gruppen, Push Buttons und Signaturfelder bleiben dort weiterhin gesperrt.

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

Dokumentweite eingebettete Dateien koennen direkt ueber den Builder registriert werden. Der aktuelle Stand unterstuetzt nur dokumentweite Associated Files am Catalog (`/AF`), keine objekt- oder seitennahe Zuordnung. Fuer PDF/A-3 werden solche Dokument-Attachments standardmaessig als Associated Files mit `AFRelationship /Data` serialisiert, solange keine explizite Beziehung gesetzt wird. Das gleiche Defaulting existiert im Attachment-Plumbing auch fuer den aktuell noch gesperrten PDF/A-4f-Pfad, ohne dass damit bereits PDF/A-4f-Claiming freigegeben waere. Standard-PDF 2.0 kann dokumentweite Associated Files mit explizitem `AFRelationship` ebenfalls serialisieren.

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

Die aktuelle AcroForm-API deckt fuer Standard-PDFs Textfelder, Checkboxen, Radio Buttons, ComboBoxen, ListBoxen, Push Buttons und Signaturfelder ab. Fuer Tagged-Profile ist der Strukturpfad inzwischen breiter: Textfelder, Checkboxen, Radio-Button-Gruppen, ComboBoxen, ListBoxen, Signaturfelder und inerte Push Buttons werden als `/Form`-Strukturelemente mit `OBJR`/`ParentTree` serialisiert. Das gilt fuer den aktuellen `PDF/UA-1`-Pfad und fuer den offiziell freigegebenen `PDF/A-1a`-Form-Scope. Push Buttons mit URI-Aktionen bleiben fuer `PDF/A-1a` weiterhin explizit gesperrt, weil der Widget-`/A`-Pfad veraPDF-relevante PDF/A-1a-Verstoesse erzeugt. Sichtbare Signaturfelder werden direkt ueber die Formular-API erzeugt; die kryptographische Signaturintegration fuer unterstuetzte Dokumente ist unten als separater Signier-Schritt beschrieben.

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
make test-pdfa1a-forms-and-annotations-regression
make test-pdfa1a-radio-regression
make test-pdfa1a-choice-fields-regression
make test-pdfa1a-negative-regressions
make check-pdf PDF=var/example.pdf
```

Dieselben `PDF/A-1a`-veraPDF-Regressionslaeufe laufen auch automatisiert in GitHub Actions ueber `.github/workflows/pdfa-regressions.yml`.

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
bin/test-pdfa1a-forms-and-annotations-regression.sh
bin/test-pdfa1a-radio-regression.sh
bin/test-pdfa1a-choice-fields-regression.sh
bin/test-pdfa1a-negative-regressions.sh
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
