# Public API und Feature-Ueberblick

## Einstieg

Es gibt zwei praktische Einstiegspunkte:

- `Kalle\Pdf\Pdf` als kleine Fassade
- `Kalle\Pdf\Xml` als kleine Fassade fuer generische XML-Erzeugung
- `Kalle\Pdf\Document\Document::make()` bzw. `DefaultDocumentBuilder::make()` fuer die Builder-API

Die Fassade deckt vor allem diese Faelle ab:

- `Pdf::document()`
- `Pdf::render()`
- `Pdf::writeToFile()`
- `Pdf::writeToStream()`
- `Pdf::contents()`
- `Pdf::renderSigned()`
- `Pdf::signedContents()`
- `Pdf::measureTextWidth()`

Fuer generische XML-Ausgabe steht ausserdem eine kleine zweite Fassade bereit:

- `Xml::element()`
- `Xml::text()`
- `Xml::document()`
- `Xml::serialize()`

## Minimaler Build

```php
use Kalle\Pdf\Pdf;

$document = Pdf::document()
    ->title('Hello')
    ->author('Example')
    ->text('Hallo PDF')
    ->build();

Pdf::writeToFile($document, __DIR__ . '/hello.pdf');
```

## Dokumentmetadaten und Profilwahl

Die Builder-API erlaubt unter anderem:

- `title()`, `author()`, `subject()`, `keywords()`
- `language()`, `creator()`, `creatorTool()`
- `profile()` fuer Standard-PDF, PDF/A und PDF/UA
- `pdfaOutputIntent()` fuer OutputIntent-Profile
- `encryption()` fuer RC4-128, AES-128 und AES-256

Profile werden ueber `Profile` erstellt, z. B.:

- `Profile::pdf14()`, `Profile::pdf17()`, `Profile::pdf20()`
- `Profile::pdfA1a()` bis `Profile::pdfA4f()`
- `Profile::pdfUa1()`, `Profile::pdfUa2()`

Wichtig: Der Profil-Scope ist bewusst nicht allgemein "alles erlaubt". Die tatsaechlich freigegebenen Teilmengen sind profilabhaengig und im Code stark validiert.

## Seiten, Flow und Text

Wichtige Builder-Methoden fuer Seitenerzeugung:

- `pageSize()`, `margin()`
- `newPage()`
- `content()` fuer rohe Content-Operationen
- `text()` und `textLines()`
- `glyphs()` fuer Standardfont-Glyph-Runs

Text wird ueber `TextOptions` gesteuert. Relevante Optionen aus Code und Beispielen:

- Position und Breite: `x`, `y`, `width`
- Typografie: `fontName`, `embeddedFont`, `fontSize`, `lineHeight`
- Abstand: `spacingBefore`, `spacingAfter`
- Farbe und Ausrichtung
- Semantik: `tag`, `semantic`
- Links ueber `link`

Fuer komplexeren Text sind ausserdem vorhanden:

- `TextSegment` fuer mehrere unterschiedlich formatierte Runs
- `TextLink` fuer explizite Link-Spans
- Bidi- und Script-Shaping im internen Textpfad

Seiteneigenschaften koennen ueber `PageOptions` pro Seite ueberschrieben werden. Der aktuelle Scope umfasst dabei unter anderem `pageSize`, `orientation`, `margin`, `backgroundColor`, `label`, `name` sowie die PDF-Seitenboxen `cropBox`, `bleedBox`, `trimBox` und `artBox`.

## Fonts

Es gibt zwei Fontpfade:

- Standard-PDF-Fonts ueber `StandardFont`
- eingebettete Fonts ueber `EmbeddedFontSource::fromPath(...)`

Im Repository sind Parser und Subsetter fuer TrueType und CFF vorhanden. Tests decken Standardfont-Metriken, Kernings, OpenType-Parser und Subsetter explizit ab.

Fuer Profile wie PDF/A-2u, PDF/A-3u und PDF/UA sind eingebettete Unicode-Fonts im aktuellen Scope wesentlich.

## Bilder

Builder-Methoden:

- `image(ImageSource $source, ImagePlacement $placement, ?ImageAccessibility $accessibility = null)`
- `imageFile(string $path, ImagePlacement $placement, ?ImageAccessibility $accessibility = null)`

Der stabile Dateipfad laeuft ueber `ImageSource::fromPath()`. Der tatsaechliche Importscope ist in [docs/image-import.md](/home/skalicki/Projekte/kalle/pdf2/docs/image-import.md) beschrieben.

## Vektorgrafik

Die API fuer grafische Primitive ist bewusst klein und explizit:

- `line()`
- `rectangle()`
- `roundedRectangle()`
- `path()`

Relevante Value Objects:

- `StrokeStyle`
- `Path` und `PathBuilder`
- `GraphicsAccessibility`
- `Units`

In Tagged-PDF-Profilen werden Grafiken standardmaessig als Artifact behandelt und koennen explizit als semantische `Figure` ausgezeichnet werden.

## Tabellen und Listen

Fuer Tabellen steht eine eigene API auf `Document/`-Ebene zur Verfuegung:

- `Table`
- `TableColumn`
- `TableRow`
- `TableCell`
- `TableOptions`

Der aktuelle Tabellenpfad unterstuetzt laut Code, Tests und Beispielen:

- feste, automatische und proportionale Spaltenbreiten
- Zell-Padding und Borders
- `rowspan` und `colspan`
- Caption
- Header- und Footer-Zeilen
- Wiederholung von Header/Footer auf Folgeseiten
- deterministische Seitenumbrueche

Fuer Listen gibt es `list(array $items, ?ListOptions $list = null, ?TextOptions $text = null)` sowie Tagged-PDF-Unterstuetzung fuer Listenstrukturen.

## Links, Ziele, Outlines und TOC

Fuer Navigation gibt es mehrere Ebenen:

- Link-Annotationen ueber `link*()`-Methoden
- Named Destinations ueber `namedDestination()` und `namedDestinationPosition()`
- Bookmarks/Outlines ueber `outline*()` und `addOutline()`
- Inhaltsverzeichnis ueber `tableOfContents()` und `tableOfContentsEntry*()`

Unterstuetzte Linkziele im Builder:

- externe URL
- andere Seite
- Position auf anderer Seite
- Named Destination

Die TOC nutzt in der aktuellen Iteration entweder explizite TOC-Eintraege oder vorhandene Outlines. Beispiele liegen in `examples/table-of-contents*.php`.

## Header und Footer

Seitendekorationen werden ueber `header()`, `headerOn()`, `footer()`, `footerOn()` und `pageNumbers()` registriert. Die Callbacks laufen fuer jede erzeugte Seite in einem eigenen `PageDecorationContext`.

Wesentliche Eigenschaften:

- Header vor regularem Inhalt, Footer danach
- auch bei `newPage()` und automatischen Umbruechen aktiv
- in Tagged-PDF-Profilen als Artefakt behandelt

## Attachments

Dokumentweite Anhaenge:

- `attachment()`
- `attachmentFromFile()`

Seitengebundene File-Attachment-Annotationen:

- `fileAttachmentAnnotation()`
- `existingFileAttachmentAnnotation()`

Ob ein Profil diese Pfade zulaesst, entscheidet `Profile`.

## Formulare

Die Builder-API deckt mehrere AcroForm-Typen ab:

- `textField()`
- `checkbox()`
- `radioButton()`
- `comboBox()`
- `listBox()`
- `pushButton()`
- `pushButtonOptionalContentState()`
- `signatureField()`

Die Freigabe in PDF/A ist bewusst enger als der Standardumfang. Tests zeigen explizit, dass nur Teilmengen der Formular-API in einzelnen PDF/A-Profilen freigegeben sind.

## Annotationen

Neben Links und Formular-Widgets existiert ein breiter Satz allgemeiner Seitenannotationstypen:

- `textAnnotation()`
- `highlightAnnotation()`
- `underlineAnnotation()`
- `strikeOutAnnotation()`
- `squigglyAnnotation()`
- `freeTextAnnotation()`
- `stampAnnotation()`
- `squareAnnotation()`
- `circleAnnotation()`
- `caretAnnotation()`
- `inkAnnotation()`
- `lineAnnotation()`
- `polyLineAnnotation()`
- `polygonAnnotation()`
- `popupAnnotation()`
- `popupAnnotationFor()`

Viele dieser Typen haben zusaetzlich `...WithOptions()`-Varianten und Value Objects fuer Metadaten, Farben, Border-Styles und Accessibility.

## Tagged PDF und Accessibility

Fuer strukturierte Ausgabe sind mehrere API-Punkte relevant:

- `beginStructure()` und `endStructure()` fuer Container
- `TextOptions(tag: ...)` fuer Blattrollen
- `ImageAccessibility`
- `GraphicsAccessibility`
- Alternativtexte fuer Links, Annotationen und Formulare in passenden Profilen

Aus Tests und Profilregeln geht klar hervor:

- Nicht jeder Inhalt wird automatisch semantisch sinnvoll.
- In Profilen mit Tagged-PDF- oder Accessibility-Anforderungen wird fehlende Semantik aktiv validiert.

## Debugging und Observability

Der Builder kann mit `debug()`, `withDebugSink()` und `withLogger()` konfiguriert werden. `DebugConfig` bietet vordefinierte Konfigurationen fuer JSON- und Text-Ausgabe sowie Senken nach Datei, `stdout`, `stderr` oder PSR-3.

## Verschluesselung und Signaturen

Verschluesselung:

- `Encryption::rc4_128()`
- `Encryption::aes128()`
- `Encryption::aes256()`
- optional `withPermissions(...)`

Signaturen:

- `signatureField(...)` fuer das unsignierte Dokument
- `Pdf::renderSigned()` oder `Pdf::signedContents()` fuer den Signaturpfad
- Credentials ueber `OpenSslPemSigningCredentials`
- Optionen ueber `PdfSignatureOptions`

Die aktuelle Implementierung signiert per inkrementellem Update und blockiert signierte verschluesselte Dokumente explizit.

## Beispiele im Repository

Die `examples/`-Dateien bilden den tatsaechlichen API-Scope gut ab. Besonders nuetzlich:

- `invoice.php` fuer ein groesseres zusammengesetztes Dokument
- `text-layout.php` fuer Flow-Text
- `table.php` und `table-repeated-footer.php`
- `graphics-primitives.php`
- `complex-text-shaping.php`
- `encryption.php`
- `observability.php`
- `outlines*.php`
- `table-of-contents*.php`

## Bekannte Grenzen des aktuellen Public Scope

Aus Code, Tests und Beispielen klar ableitbar:

- PDF/A-Unterstuetzung ist absichtlich profil- und featurespezifisch begrenzt.
- Das Text-Shaping ist intern implementiert und nicht als generische Vollabdeckung aller OpenType-Skripte dokumentiert.
- WebP-Import ist optional und haengt von GD+WebP ab.
- Signaturen und Verschluesselung koennen aktuell nicht kombiniert werden.
- Fuer viele Features existiert ein enger, explizit getesteter Positivpfad statt einer breiten "alles irgendwie"-Unterstuetzung.
