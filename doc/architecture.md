# Architecture

Diese Datei beschreibt die aktuelle Architektur der PDF-Library. Sie dokumentiert den Ist-Zustand des Codes und nicht eine Zielarchitektur.

## Ueberblick

Die Library baut ein PDF als kleines Objektmodell in PHP auf und rendert dieses Modell am Ende in die PDF-Syntax.

Der Hauptfluss ist:

1. `Document` sammelt Metadaten, Fonts und Seiten.
2. `Page` nimmt Inhalte wie Text, Bilder und einfache grafische Primitive entgegen.
3. `Contents` und `Resources` sammeln die seitenbezogenen Daten.
4. optionale Verschluesselung wird ueber Security-Handler-Daten und objektbezogene Schluessel vorbereitet.
5. `PdfRenderer` rendert alle indirekten Objekte in der richtigen Reihenfolge.
6. Zum Schluss werden `xref`, `trailer` und `startxref` angehaengt.

## Kernklassen

### Document

`Document` ist der Einstiegspunkt der API und das zentrale Aggregat.

Verantwortlich fuer:

- PDF-Version sowie klassische PDF-Info- und XMP-Metadaten
- globale Font-Registrierung
- optionale dokumenteigene Font-Konfiguration
- Verwaltung aller Seiten
- optionale wiederkehrende Header- und Footer-Renderer fuer neue Seiten
- optionale Outline-/Bookmark-Navigation fuer PDF-Viewer
- optionale benannte Ziele fuer interne PDF-Spruenge
- optionale Dokumentverschluesselung
- Vergabe von Objekt-IDs
- lazy Aufbau der Strukturdaten fuer Tagged-Inhalte
- Start des Renderings

Wichtige Methoden:

- `addPage()` erzeugt eine neue Seite mit eigener `Contents`- und `Resources`-Instanz
- `addHeader()` und `addFooter()` registrieren Seiten-Callbacks, die bei jeder neuen Seite ausgefuehrt werden
- `addPageNumbers()` registriert einen spaet aufgeloesten Seitenzahl-Renderer mit Zugriff auf `{{page}}` und `{{pages}}`
- `addOutline()` registriert einen Bookmark-Eintrag fuer eine Zielseite
- `addDestination()` registriert ein benanntes Ziel fuer interne Spruenge
- `addTableOfContents()` erzeugt Inhaltsverzeichnis-Seiten aus vorhandenen Outlines
- `encrypt()` aktiviert den Standard-Security-Handler mit Passwort, Permissions und passendem Verschluesselungsprofil
- `registerFont()` registriert Fonts im Dokument
- `addKeyword()` pflegt die Dokument-Keywords
- `render()` delegiert an `PdfRenderer`
- `getDocumentObjects()` liefert die Menge aller zu rendernden indirekten Objekte

`Document` haelt die fachlichen Dokumentmetadaten zentral und stellt sie sowohl fuer das klassische PDF-Info-Dictionary als auch fuer den XMP-Metadata-Stream bereit. Der `Catalog` verweist ab PDF `1.4` ueber `/Metadata` auf den XMP-Stream.

Die Metadaten-Rollen sind aktuell bewusst getrennt:

- `author` steht fuer den inhaltlichen Autor oder die verantwortliche Organisation
- `creator` steht fuer das erzeugende System oder den Service
- `creatorTool` steht fuer das konkrete Tool oder UI, das den Export ausgeloest hat
- `producer` steht fuer die technische PDF-Engine und wird aus Paketname und installierter Version abgeleitet

Im Mapping gilt aktuell:

- `Info /Author` und XMP `dc:creator` werden aus `author` gespeist
- `Info /Creator` wird aus `creator` gespeist
- XMP `xmp:CreatorTool` wird aus `creatorTool` gespeist
- `Info /Producer` und XMP `pdf:Producer` werden aus `producer` gespeist

Wenn Outlines vorhanden sind, erzeugt `Document` zusaetzlich ein `OutlineRoot`-Objekt und eine flache Liste von `OutlineItem`-Objekten aus `src/Document/Outline`. Der `Catalog` verweist dann ueber `/Outlines` auf diese Navigationsstruktur und setzt `/PageMode /UseOutlines`.

Wenn benannte Ziele vorhanden sind, rendert der `Catalog` zusaetzlich ein `/Dests`-Dictionary mit Zielnamen und Zielseiten.

`addPageNumbers()` wird erst kurz vor dem finalen Rendern aufgeloest, damit die Gesamtseitenzahl des Dokuments bekannt ist.

`addTableOfContents()` kann die erzeugten TOC-Seiten am Anfang oder Ende der Seitenreihenfolge platzieren. Die Position wird ueber `TableOfContentsPosition::START` oder `::END` gesteuert.

Wenn Verschluesselung aktiv ist, cached `Document` die Security-Handler-Daten einmal zentral. Dadurch verwenden Encrypt-Dictionary und Objektverschluesselung denselben Dateischluessel und dieselben abgeleiteten Werte.

### Verschluesselung

Die Verschluesselung ist aktuell als eigener Teilpfad ueber `src/Encryption` modelliert.

Die wichtigsten Bausteine sind:

- `EncryptionAlgorithm`
- `EncryptionProfile`
- `EncryptionOptions`
- `EncryptionPermissions`
- `EncryptionVersionResolver`
- `StandardSecurityHandler`
- `StandardObjectEncryptor`

Der Ablauf ist:

1. `Document::encrypt(...)` nimmt Passwort und optionalen Algorithmus entgegen.
2. `EncryptionVersionResolver` waehlt anhand der PDF-Version das passende Profil.
3. `StandardSecurityHandler` erzeugt die Security-Handler-Daten fuer das Dokument.
4. `EncryptDictionary` rendert daraus den `/Encrypt`-Eintrag.
5. `PdfRenderer` verschluesselt Strings und Streams objektbezogen ueber `StandardObjectEncryptor`.
6. Der Trailer bekommt `/Encrypt` und `/ID`.

Aktuell unterstuetzt:

- `RC4_40` als `V=1 / R=2`
- `RC4_128` als `V=2 / R=3`
- `AES_128` als `V=4 / R=4` mit `AESV2`
- `AES_256` als `V=5 / R=5` mit `AESV3`

Fuer `AES_128` und `AES_256` werden Strings und Streams mit AES verschluesselt. Bei Streams wird die Laenge nach der Verschluesselung aktualisiert, weil IV und Padding die Stream-Daten vergroessern koennen.

### Pages

`Pages` repraesentiert den `/Pages`-Knoten des PDFs.

Verantwortlich fuer:

- Sammlung aller `Page`-Objekte
- Aufbau des `/Kids`-Arrays
- Pflege des `/Count`-Eintrags

`Document` besitzt genau eine `Pages`-Instanz.

### Page

`Page` repraesentiert eine einzelne PDF-Seite.

Verantwortlich fuer:

- Seitengroesse
- Zuordnung zu `Contents` und `Resources`
- Entgegennahme von Inhaltselementen
- Vergabe lokaler Marked-Content-IDs pro Seite nur bei strukturierten Inhalten

Die wichtigsten APIs sind aktuell `addText(...)`, `addFlowText(...)`, `addTextBox(...)`, `createTextFrame(...)`, `createTable(...)`, `addLine(...)`, `addRectangle(...)`, `addRoundedRectangle(...)`, `addPath()`, `addCircle(...)`, `addEllipse(...)`, `addPolygon(...)`, `addArrow(...)`, `addStar(...)`, `addBadge(...)`, `addPanel(...)`, `addCallout(...)`, `addImage(...)`, `addLink(...)`, `addInternalLink(...)`, `addTextField(...)`, `addCheckbox(...)`, `addRadioButton(...)`, `addComboBox(...)`, `addListBox(...)`, `addSignatureField(...)` und `addPushButton(...)`.

Dabei passiert intern:

1. Die angeforderte Schrift wird in den registrierten Dokument-Fonts gesucht.
2. Der Text wird gegen die Font-Unterstuetzung validiert.
3. Der Text wird fontspezifisch encodiert.
4. Ein `Text`-Element wird im `Contents`-Stream der Seite abgelegt.
5. Nur wenn ein Struktur-Tag gesetzt ist, wird parallel ein `StructElem` fuer das Strukturmodell erzeugt.
6. Falls `link` gesetzt ist, wird zusaetzlich eine `LinkAnnotation` mit passendem Rechteck an die Seite gehaengt.

Beim Absatz-Rendering kommen zusaetzlich dazu:

7. Eingaben werden zu `TextSegment`-Objekten normalisiert.
8. Der Text wird in Tokens und Zeilen fuer den Umbruch zerlegt.
9. Optional werden Alignment, `maxLines` und `TextOverflow` auf die sichtbaren Zeilen angewendet.
10. Stilwechsel innerhalb eines Absatzes werden ueber mehrere `Text`-Elemente gerendert.
11. Segment-spezifische Links werden als einzelne `LinkAnnotation`-Objekte mitgefuehrt.

Bei Tabellen kommt zusaetzlich dazu:

7. `Page::createTable(...)` erzeugt ein `Table`-Objekt mit fester Breite und festen Spaltenbreiten.
8. `Table::addRow(...)` normalisiert Zellinhalte zu `TableCell`-Instanzen.
9. `colspan` und `rowspan` werden ueber vorbereitete Zeilengruppen und aktive Spaltenbelegung aufgeloest.
10. Die Zeilenhoehe wird ueber `countParagraphLines(...)` und die zusammengefassten Zellgruppen berechnet.
11. Zellhintergruende und Borders werden ueber `Rectangle` gerendert.
12. Zelltext wird ueber den vorhandenen Absatzpfad in die jeweilige Zelle geschrieben.
13. Wenn eine Zeile nicht mehr passt, erzeugt `Table` intern eine Folge-Seite und rendert dort weiter.
14. Vor gemerkten Body-Zeilen werden vorhandene Header-Zeilen auf der neuen Seite erneut gerendert.

Bei grafischen Inhalten kommt stattdessen dazu:

7. Linien, Boxen und punktbasierte Geometrie werden ueber `Position` und `Rect` an die `Page`-API uebergeben.
8. `PathBuilder` erzeugt aus `moveTo(...)`, `lineTo(...)`, `curveTo(...)` und `close()` ein `Path`-Element fuer freie Formen.
9. `addCircle(...)` baut darauf einen Kreis aus vier kubischen Bezier-Segmenten auf.
10. `addEllipse(...)`, `addPolygon(...)`, `addArrow(...)` und `addStar(...)` nutzen dieselbe Path-/Line-Infrastruktur fuer weitere Formen.
11. `addRoundedRectangle(...)` baut ein Rechteck mit Bezier-Ecken ueber denselben Path-Builder auf.
12. `addBadge(...)` kombiniert Hintergrundform und Text zu einem kleinen Label-Element und nutzt optional den Rounded-Rectangle-Pfad.
13. `addPanel(...)` kombiniert Box, Titel und Body zu einer einfachen Hinweis- oder Infobox und nutzt dafuer den vorhandenen Rechteck-/Rounded-Rectangle- sowie Absatzpfad.
14. `addCallout(...)` erweitert ein `Panel` um eine Pointer-Spitze und zeichnet diese ueber ein zusaetzliches Polygon.
15. Bilder werden als eigene indirekte XObjects erzeugt und in den Seiten-`Resources` unter `/XObject` registriert.
16. Ein separates `DrawImage`-Element referenziert die Bild-Resource im Content-Stream per `/ImN Do`.
17. Links werden als Annotationen im Seiten-Dictionary unter `/Annots` referenziert.

Bei Formularen kommt stattdessen oder zusaetzlich dazu:

7. `Document::ensureAcroForm()` baut bei Bedarf einmalig ein `AcroForm` auf.
8. `Page::addTextField(...)`, `addCheckbox(...)`, `addRadioButton(...)`, `addComboBox(...)`, `addListBox(...)`, `addSignatureField(...)` und `addPushButton(...)` erzeugen Widget-Annotationen fuer die jeweilige Feldart.
9. Die Widget-Annotation wird in `/Annots` der Seite eingetragen.
10. Das Feld wird zusaetzlich im `AcroForm` unter `/Fields` registriert.
11. Text- und Choice-Felder registrieren ihre verwendete Font-Resource im `AcroForm`-`DR`-Dictionary.
12. `FormFieldFlags` werden in den jeweiligen `/Ff`-Bitwert uebersetzt.
13. `defaultValue` wird fuer TextField, ComboBox und ListBox als `/DV` gerendert.
14. Checkboxen und Radio-Buttons verwenden eigene Appearance-Streams fuer On/Off-Zustaende.

### Formulare und AcroForm

Die aktuelle Formular-Architektur ist als erste AcroForm-Stufe modelliert.

Die wichtigsten Bausteine sind:

- `AcroForm`
- `TextFieldAnnotation`
- `CheckboxAnnotation`
- `RadioButtonField`
- `RadioButtonWidgetAnnotation`
- `ComboBoxAnnotation`
- `ListBoxAnnotation`
- `SignatureFieldAnnotation`
- `PushButtonAnnotation`
- `FormFieldFlags`
- `ButtonAction`
- `SubmitFormAction`
- `ResetFormAction`
- `JavaScriptAction`

`AcroForm` ist aktuell verantwortlich fuer:

- Sammlung aller Formularfelder unter `/Fields`
- `NeedAppearances`
- Default Resources (`DR`) fuer Fonts von Text- und Choice-Feldern
- Verwaltung gemeinsamer Radio-Gruppen

Die Feldtypen sind aktuell so umgesetzt:

- `TextFieldAnnotation`
  - `/FT /Tx`
  - optional `multiline`
  - optional `DV`

- `CheckboxAnnotation`
  - `/FT /Btn`
  - eigener On/Off-Status ueber `/V` und `/AS`
  - sichtbare Appearance-Streams fuer beide Zustaende

- `RadioButtonField` mit `RadioButtonWidgetAnnotation`
  - Parent-Feld mit `/Kids`
  - Widgets teilen denselben Feldnamen
  - Auswahl wird ueber Export-Werte und Widget-`/AS` synchron gehalten

- `ComboBoxAnnotation`
  - `/FT /Ch`
  - Combo-Flag im `/Ff`
  - optionales Edit-Flag fuer Freitext-Eingabe
  - Optionen ueber `/Opt`
  - optional `DV`

- `ListBoxAnnotation`
  - `/FT /Ch`
  - kein Combo-Flag
  - optionales Multi-Select-Flag
  - Optionen ueber `/Opt`
  - optional `DV`

- `SignatureFieldAnnotation`
  - `/FT /Sig`
  - sichtbares Widget-Feld fuer spaetere Signaturen
  - aktuell noch ohne `V`-Signaturdictionary und ohne kryptografische Signaturerzeugung

- `OptionalContentGroup`
  - `/Type /OCG`
  - eigener Layer-Eintrag mit sichtbarem Namen
  - Registrierung im `Catalog` ueber `/OCProperties`

- `PushButtonAnnotation`
  - `/FT /Btn`
  - Pushbutton-Flag im `/Ff`
  - sichtbare Caption ueber `/MK /CA`
  - optionales Action-Dictionary ueber `/A`

`ButtonAction` kapselt aktuell zwoelf konkrete Action-Typen:

- `SubmitFormAction`
  - `/S /SubmitForm`
  - Ziel-URL ueber `/F (...)`

- `ResetFormAction`
  - `/S /ResetForm`

- `JavaScriptAction`
  - `/S /JavaScript`
  - Skript ueber `/JS (...)`

- `NamedAction`
  - `/S /Named`
  - Viewer-Navigation ueber `/N /NextPage`, `/PrevPage`, `/FirstPage`, `/LastPage`

- `GoToAction`
  - `/S /GoTo`
  - Sprung zu einem benannten Ziel ueber `/D /zielname`

- `GoToRemoteAction`
  - `/S /GoToR`
  - externe PDF-Datei ueber `/F (...)`
  - Ziel in der Zieldatei ueber `/D /zielname`

- `LaunchAction`
  - `/S /Launch`
  - oeffnet eine externe Datei ueber `/F (...)`

- `UriAction`
  - `/S /URI`
  - oeffnet eine URL ueber `/URI (...)`

- `HideAction`
  - `/S /Hide`
  - blendet ein Ziel ueber `/T (...)` ein oder aus
  - optionales Ruecksetzen ueber `/H false`

- `ImportDataAction`
  - `/S /ImportData`
  - importiert FDF/XFDF-Daten ueber `/F (...)`

- `SetOcgStateAction`
  - `/S /SetOCGState`
  - schaltet OCG-/Layer-Zustaende ueber `/State [...]`
  - akzeptiert `ON`, `OFF`, `Toggle` und echte `OptionalContentGroup`-Referenzen

- `ThreadAction`
  - `/S /Thread`
  - springt zu einem Artikel-Thread ueber `/D (...)`
  - optional externe Datei ueber `/F (...)`

`FormFieldFlags` kapselt aktuell die erste gemeinsame Flag-Stufe:

- `readOnly`
- `required`
- `password`
- `editable`
- `multiSelect`

und wird je nach Feldtyp mit zusaetzlichen feldspezifischen Bits kombiniert, zum Beispiel:

- `multiline` fuer Textfelder
- Combo-Flag fuer ComboBoxen
- Edit-Flag fuer editierbare ComboBoxen
- Multi-Select-Flag fuer ListBoxen

Die aktuelle Stufe ist bewusst pragmatisch:

- Text- und Choice-Felder verlassen sich fuer die Darstellung noch auf `NeedAppearances`
- Checkboxen und Radio-Buttons haben bereits eigene Appearance-Streams
- komplexere Formular-Features wie echte digitale Signaturen oder komplett eigene Appearances fuer Text- und Choice-Felder sind noch nicht Teil des Kernpfads

### Optional Content und Layer

Die erste OCG-Stufe ist jetzt als echter Dokumentpfad modelliert.

Die wichtigsten Bausteine sind:

- `OptionalContentGroup`
- `Document::addLayer(...)`
- `Page::layer(...)`
- `Resources::addProperty(...)`
- `Catalog` mit `/OCProperties`

Der Ablauf ist:

1. `Document::addLayer(...)` liefert ein `OptionalContentGroup`-Objekt.
2. `Page::layer(...)` registriert dieses Objekt in den Seiten-Resources unter `/Properties`.
3. Der enthaltene Seiteninhalt wird mit `/OC /OCn BDC ... EMC` umschlossen.
4. `Catalog` rendert alle bekannten Layer unter `/OCProperties`.
5. `SetOcgStateAction` kann diese Layer danach ueber echte OCG-Referenzen schalten.

### Attachments und Embedded Files

Die erste Attachment-Stufe ist als eingebettete Datei auf Dokumentebene modelliert.

Die wichtigsten Bausteine sind:

- `EmbeddedFileStream`
- `FileSpecification`
- `Document::addAttachment(...)`
- `Document::addAttachmentFromFile(...)`
- `Catalog` mit `Names` und `EmbeddedFiles`

Der Ablauf ist:

1. `Document::addAttachment(...)` erzeugt einen `EmbeddedFileStream` und ein zugehoeriges `FileSpecification`-Objekt.
2. Beide Objekte werden als indirekte Objekte im Dokument mitgerendert.
3. `Catalog` rendert die Dateireferenzen ueber `/Names << /EmbeddedFiles << /Names [...] >> >>`.
4. PDF-Viewer koennen diese Dateien dann als Dokument-Anhaenge anzeigen.

### Weitere Annotationen

Neben `LinkAnnotation`, Widget-Annotationen und `FileAttachmentAnnotation` sind jetzt weitere Viewer-Annotationen modelliert.

Die wichtigsten Bausteine sind:

- `TextAnnotation`
- `FreeTextAnnotation`
- `HighlightAnnotation`
- `UnderlineAnnotation`
- `StrikeOutAnnotation`
- `SquigglyAnnotation`
- `StampAnnotation`
- `SquareAnnotation`
- `CircleAnnotation`
- `LineAnnotation`
- `PolyLineAnnotation`
- `PolygonAnnotation`
- `CaretAnnotation`
- `InkAnnotation`
- `PopupAnnotation`
- `Page::addTextAnnotation(...)`
- `Page::addFreeTextAnnotation(...)`
- `Page::addHighlightAnnotation(...)`
- `Page::addUnderlineAnnotation(...)`
- `Page::addStrikeOutAnnotation(...)`
- `Page::addSquigglyAnnotation(...)`
- `Page::addStampAnnotation(...)`
- `Page::addSquareAnnotation(...)`
- `Page::addCircleAnnotation(...)`
- `Page::addLineAnnotation(...)`
- `Page::addPolyLineAnnotation(...)`
- `Page::addPolygonAnnotation(...)`
- `Page::addCaretAnnotation(...)`
- `Page::addInkAnnotation(...)`
- `Page::addPopupAnnotation(...)`

Die aktuelle Stufe ist bewusst pragmatisch:

- Markup-Annotationen werden aus einem Rechteck plus `QuadPoints` derselben Box aufgebaut
- `FreeTextAnnotation` nutzt aktuell `DA`, Border- und Fill-Farben, aber noch keine komplexen Appearance-Streams
- `StampAnnotation` verlaesst sich auf den jeweiligen Viewer-Stempel ueber `/Name`
- `SquareAnnotation` und `CircleAnnotation` folgen dem einfachen Viewer-Annotation-Pfad mit `C` und `IC`
- `AnnotationBorderStyle` bildet das `BS`-Dictionary fuer geometrische Viewer-Annotationen ab
- `LineAnnotation` und `PolyLineAnnotation` nutzen `LineEndingStyle` ueber `LE`
- `LineAnnotation`, `PolyLineAnnotation` und `PolygonAnnotation` nutzen die entsprechenden PDF-Viewer-Geometry-Dictionaries ueber `L` bzw. `Vertices`
- `LineAnnotation`, `PolyLineAnnotation` und `PolygonAnnotation` koennen fuer Review-Faelle `Subj` und ein verknuepftes `Popup` tragen
- `CaretAnnotation` nutzt den dedizierten Viewer-Typ `/Caret` mit optionalem `Sy`
- `InkAnnotation` modelliert freie Linienzuege ueber `InkList`
- `PopupAnnotation` wird aktuell als verknuepftes Related Object an Kommentar- und Markup-Annotationen angehaengt

### TextFrame

`TextFrame` ist eine kleine Layout-Hilfe ueber `Page`.

Verantwortlich fuer:

- gemeinsamen Textbereich mit `Position` und Breite
- Cursor-Fuehrung zwischen mehreren Textbloecken
- Absatzeinzug ueber `addFlowText(...)`
- Ueberschriften ueber `addHeading(...)`
- automatische Folge-Seiten bei Ueberlauf
- Weitergabe von Alignment, `maxLines` und `TextOverflow`
- Listen ueber `addBulletList(...)` und `addNumberedList(...)`

`addBulletList(...)` und `addNumberedList(...)` bauen bewusst auf dem vorhandenen Absatzpfad auf:

- Marker oder Nummer werden als eigenes `Text`-Element gerendert
- der Listeninhalt wird als Absatz mit reduziertem Textbereich gerendert
- dadurch bleiben Umbruch, Links und Folge-Seiten konsistent
- `BulletType` kapselt die aktuell unterstuetzten Standard-Symbole fuer Bullet-Listen
- `addNumberedList(...)` nutzt denselben Listenpfad mit laufenden Dezimalzahlen und optionalem `startAt`

### TextLayoutEngine

`TextLayoutEngine` ist eine kleine interne Hilfsklasse unter `src/Document/Text`.

Sie gehoert nicht zur oeffentlichen API. `Page` bleibt der oeffentliche Einstiegspunkt fuer Text-Rendering.

Die Klasse kapselt nur die interne Absatz-Logik:

- Text-Runs normalisieren
- Zeilen umbrechen
- Overflow und Ellipsis anwenden
- trailing spaces fuer Alignment bereinigen

Dadurch bleibt `Page` als API stabil, waehrend die Text-Layout-Logik an einer klareren Stelle liegt.

### Table und TableCell

`Table` ist eine erste Layout-Hilfe fuer tabellarische Inhalte mit fester Spaltenstruktur.

Verantwortlich fuer:

- Startposition, Tabellenbreite und Spaltenbreiten
- Cursor-Fuehrung zwischen den Tabellenzeilen
- Header- und Row-Styles
- `colspan` und `rowspan`, auch ueber Seitenumbrueche mit Fortsetzungszustand
- steuerbare Borders ueber `TableBorder`
- horizontaler und vertikaler Tabellen-Default fuer Zellen
- Tabellen-Default und Zell-Override fuer Padding ueber `TablePadding`
- Tabellen-Defaults ueber `TableStyle`
- Header-Defaults ueber `HeaderStyle`
- Body-Zeilen-Defaults ueber `RowStyle`
- gebuendelte Zell-Stile ueber `CellStyle`
- Wiederholung von Header-Zeilen bei Seitenwechsel
- Berechnung der Zeilenhoehe ueber den vorhandenen Absatz-Umbruch
- Seitenwechsel, wenn eine komplette Zeile nicht mehr passt

`TableCell` repraesentiert eine einzelne Zelle mit:

- `text` als `string` oder `TextSegment[]`
- `colspan`
- `rowspan`
- optionales `style`

`TableBorder` kapselt den Linienstil fuer Tabellen und einzelne Zellen.

`TablePadding` kapselt die Innenabstaende fuer Tabellen und einzelne Zellen.

`TableStyle` kapselt gebuendelt:

- Padding
- Border
- vertikale Ausrichtung
- optionale Fill- und Textfarbe

`RowStyle` kapselt gebuendelt:

- horizontale Ausrichtung
- vertikale Ausrichtung
- Padding
- Fill- und Textfarbe
- Opacity
- Border

`HeaderStyle` ist die entsprechende Spezialisierung fuer Header-Zeilen und nutzt denselben Stilumfang.

`CellStyle` kapselt gebuendelt:

- horizontale Ausrichtung
- vertikale Ausrichtung
- Padding
- Fill- und Textfarbe
- Opacity
- Border

Aktuell unterstuetzt:

- `TableBorder::all(...)`
- `TableBorder::none()`
- `TableBorder::horizontal(...)`
- `TableBorder::vertical(...)`
- `TableBorder::only([...])`

Im Renderpfad gilt:

- `TableStyle` liefert die Defaults fuer die gesamte Tabelle
- `HeaderStyle` liefert Defaults fuer Header-Zeilen
- `RowStyle` liefert Defaults fuer Body-Zeilen
- `CellStyle` liefert die feinsten Overrides pro Zelle
- einzelne `TableCell`-Instanzen koennen einzelne Border-Seiten gezielt ueberschreiben
- einzelne `TableCell`-Instanzen koennen horizontale und vertikale Ausrichtung gezielt ueberschreiben
- einzelne `TableCell`-Instanzen koennen das Zell-Padding gezielt ueberschreiben
- `TableCell::style` liefert den gebuendelten Stil pro Zelle
- nicht gesetzte Zell-Seiten erben weiter vom Tabellen-Default
- partielle Borders werden intern als gezielte Linien statt als komplettes Rechteck gerendert
- vertikale Zell-Ausrichtung wird aus der echten Content-Hoehe innerhalb der Zellbox berechnet
- Zellbreite, Zellhoehe und Text-Startposition werden unter Beruecksichtigung des effektiven Paddings berechnet

Die aktuelle `rowspan`-Stufe arbeitet intern mit vorbereiteten Zeilengruppen und einem Fortsetzungszustand fuer offene Zellen:

- `rowspan` funktioniert innerhalb einer zusammenhaengenden Zeilengruppe
- offene `rowspan`-Zellen koennen an Seitengrenzen auf Folge-Seiten fortgesetzt werden
- Text in gesplitteten `rowspan`-Zellen wird segmentweise weitergerendert
- Header-Zeilen werden auf Folge-Seiten erneut gerendert

Noch offen ist vor allem der optische Feinschliff:

- die Verteilung der Textzeilen ueber mehrere Split-Segmente ist noch bewusst pragmatisch
- an der Segmentgrenze kann weiterer Tuning-Bedarf fuer Hoehenbalance und sehr saubere Umbruchentscheidungen bestehen

### Badge, Panel und Callout

`Badge`, `Panel` und `Callout` sind erste zusammengesetzte Dokumentelemente ueber den Basis-Primitiven.

`Badge` nutzt:

- Text
- Padding
- optional Border
- optional `RoundedRectangle`

`Panel` nutzt:

- `Rectangle` oder `RoundedRectangle` als Hintergrundbox
- einen optionalen Titel ueber `addText(...)`
- den bestehenden Absatzpfad fuer den Body ueber `addFlowText(...)` oder `addTextBox(...)` je nach Layoutbedarf
- optional eine Link-Annotation ueber die gesamte Box

`Callout` nutzt:

- die komplette `Panel`-Infrastruktur fuer Box, Titel und Body
- zusaetzlich ein Pointer-Polygon ueber `addPolygon(...)`
- dieselben Link- und Style-Faehigkeiten wie `Panel`

`PanelStyle` kapselt aktuell:

- horizontales und vertikales Padding
- Radius fuer gerundete Ecken
- Abstand zwischen Titel und Body
- Title- und Body-Fontgroesse
- horizontale Ausrichtung fuer Titel und Body
- Fill-, Title- und Body-Farbe
- Border und Opacity

`CalloutStyle` kapselt aktuell:

- optionales `PanelStyle` fuer die Box selbst
- Breite der Pointer-Basis
- optionalen eigenen Stroke, Fill und Opacity fuer die Pointer-Spitze

### Contents

`Contents` kapselt den Content-Stream einer Seite.

Verantwortlich fuer:

- Sammlung von `Element`-Objekten
- Zusammenbau des finalen Stream-Inhalts
- Schreiben des `/Length`-Eintrags

Aktuell werden die Elemente direkt hintereinander in einen einzelnen Stream gerendert.

### Resources

`Resources` kapselt die PDF-Ressourcen einer Seite.

Verantwortlich fuer:

- Zuordnung der auf der Seite verwendeten Fonts
- Zuordnung von `ExtGState`-Eintraegen fuer Text- und Grafik-Opacity
- Zuordnung von Bild-XObjects
- Vergabe interner Ressourcen-Namen wie `F1`, `F2`, ...
- Vergabe interner Bildnamen wie `Im1`, `Im2`, ...
- Vergabe interner Graphics-State-Namen wie `GS1`, `GS2`, ...
- Rendern des `/Font`-Dictionarys
- Rendern des `/XObject`-Dictionarys
- Rendern des `/ExtGState`-Dictionarys

Wichtig: Fonts werden global im `Document` registriert, aber erst beim Einsatz auf einer Seite in deren `Resources` aufgenommen.

### PdfRenderer

`PdfRenderer` erzeugt das finale PDF-Dokument als String.

Verantwortlich fuer:

- PDF-Header schreiben
- indirekte Objekte rendern
- Byte-Offets aller Objekte erfassen
- Cross-Reference-Tabelle erzeugen
- Trailer und `startxref` schreiben

Die Reihenfolge der Objekte kommt aus `Document::getDocumentObjects()`.

## Elemente

### Element

`Element` ist die abstrakte Basisklasse fuer renderbare Seiteninhalte.

Aktuell sind `Text`, `Line`, `Rectangle`, `Path`, `Image` und `DrawImage` relevant.

### Text

`Text` rendert einen einzelnen Textblock in PDF-Operatoren.

Der aktuelle Output folgt bei strukturiertem Text grob diesem Muster:

```text
q
BT
/F1 24 Tf
1 0 0 rg
20 265 Td
/H1 << /MCID 0 >> BDC
(Hallo PDF) Tj
EMC
ET
Q
```

Dabei kombiniert das Element:

- Font-Ressource
- Schriftgroesse
- Position
- encodierten Inhalt
- optionale Textfarbe
- optionalen Graphics State fuer Opacity
- optionale Textdekorationen fuer `underline` und `strikethrough`
- optional Struktur-Tag
- optionale Marked-Content-ID

Die Einfassung mit `q` und `Q` isoliert den Graphics State pro Text-Element, damit Farben und Opacity nicht in nachfolgende Texte leaken.

### Line

`Line` rendert eine einfache stroked Linie mit PDF-Operatoren wie `m`, `l` und `S`.

Optional kombiniert das Element:

- Stroke-Farbe
- Linienstaerke
- Graphics State fuer Stroke-Opacity

### Rectangle

`Rectangle` rendert Rechtecke ueber den PDF-Operator `re`.

Je nach Parametern entstehen drei Modi:

- `S` fuer reines Stroke
- `f` fuer reines Fill
- `B` fuer Fill + Stroke

Damit eignet sich das Element fuer Rahmen, Panels, Hintergruende und einfache Layout-Boxen.

### Path

`Path` rendert freie Zeichenpfade aus einzelnen PDF-Pfadoperatoren.

Aktuell wird das ueber `Page::addPath()` und `PathBuilder` aufgebaut:

- `moveTo(...)`
- `lineTo(...)`
- `curveTo(...)`
- `close()`
- `stroke(...)`
- `fill(...)`
- `fillAndStroke(...)`

Damit lassen sich Formen wie Diamanten, Polygone oder einfache Diagrammformen erzeugen, ohne fuer jede Form ein eigenes High-Level-Element anzulegen.

### Circle

`Page::addCircle(...)` ist ein Convenience-Pfad auf Basis des vorhandenen `PathBuilder`.

Intern wird der Kreis nicht ueber einen speziellen PDF-Kreisoperator erzeugt, sondern ueber vier kubische Bezier-Segmente approximiert. Dadurch bleibt der Kreis fachlich ein normaler `Path` und nutzt dieselben Paint-Modi wie andere freie Formen.

### Ellipse, Polygon, Arrow und Star

Weitere grafische Convenience-Methoden bauen auf denselben Grundbausteinen auf:

- `Page::addEllipse(...)` approximiert eine Ellipse ueber vier kubische Bezier-Segmente mit getrennten X- und Y-Radien
- `Page::addPolygon(...)` erzeugt einen geschlossenen Pfad aus einer Liste von Punkten
- `Page::addArrow(...)` kombiniert eine Linie mit einer gefuellten polygonalen Pfeilspitze
- `Page::addStar(...)` erzeugt ueber alternierende Aussen- und Innenpunkte einen geschlossenen Stern-Pfad auf Basis von `addPolygon(...)`

Damit bleibt auch dieser Teil der API konsistent: Neue Formen sind fachlich keine Sonderobjekte, sondern nur bequeme High-Level-Zugaenge auf bestehende Pfad- und Linien-Operationen.

### Image und DrawImage

Die Bildintegration ist bewusst in zwei Teile getrennt:

- `Image` beschreibt den eigentlichen `/Subtype /Image`-XObject-Stream
- `DrawImage` beschreibt die Platzierung im Content-Stream per Matrix `cm` und `/ImN Do`

`Page::addImage(...)` verbindet beide Ebenen:

1. Das Bild wird als indirektes Objekt angelegt.
2. Die Seite registriert das Bild in `Resources` unter `/XObject`.
3. Ein `DrawImage`-Element wird im Content-Stream abgelegt.

`Image::fromFile(...)` erkennt aktuell `JPEG` direkt sowie unterstuetzte `PNG`-Dateien, inklusive Alpha-PNG ueber eine separate Soft-Mask (`SMask`).

### LinkAnnotation

Links werden aktuell nicht als sichtbares Content-Element gerendert, sondern als Annotation.

`LinkAnnotation` rendert ein eigenes indirektes `/Annot`-Objekt mit:

- `/Subtype /Link`
- einem Rechteck in `/Rect`
- einer URI-Action ueber `/A << /S /URI /URI (...) >>`
- einer Rueckreferenz auf die Seite ueber `/P`

Damit gibt es zwei API-Ebenen:

- `Page::addLink(...)` fuer beliebige klickbare Flaechen
- `Page::addInternalLink(...)` fuer frei positionierte interne Link-Flaechen
- `TextOptions(link: LinkTarget::...)` und `TextSegment::link` fuer aus Textbreite abgeleitete Link-Rechtecke
- `LinkTarget::namedDestination(...)` fuer interne Ziele ohne String-Shortcut

### TextSegment

`TextSegment` repraesentiert einen zusammenhaengenden Inline-Abschnitt mit einheitlichem Stil innerhalb eines Absatzes.

Ein Segment traegt aktuell:

- `text`
- optionale `Color`
- optionale `Opacity`
- optionalen `link`
- `bold`
- `italic`
- `underline`
- `strikethrough`

`Page::addFlowText(...)`, `Page::addTextBox(...)` und `TextFrame::addParagraph(...)` akzeptieren entweder einen einfachen `string` oder eine Liste von `TextSegment`-Objekten.

Fuer haeufige Inline-Faelle gibt es kleine Factory-Methoden direkt auf `TextSegment`, zum Beispiel `plain(...)`, `link(...)`, `bold(...)`, `italic(...)`, `underlined(...)` und `strikethrough(...)`.

### HorizontalAlign und TextOverflow

Fuer Absatzlayout gibt es aktuell zwei kleine Steuerobjekte:

- `HorizontalAlign` mit `LEFT`, `CENTER`, `RIGHT`, `JUSTIFY`
- `TextOverflow` mit `CLIP` und `ELLIPSIS`

`JUSTIFY` verteilt zusaetzlichen Wortabstand nur auf automatisch umgebrochene Zeilen. Die letzte Absatzzeile und harte Zeilenumbrueche werden nicht gestreckt.

`TextOverflow` greift nach dem Umbruch:

- `CLIP` verwirft alle Zeilen hinter `maxLines`
- `ELLIPSIS` kuerzt die letzte sichtbare Zeile so, dass ein Ellipsis noch in die Breite passt

Wenn der verwendete Font das Zeichen `…` unterstuetzt, wird dieses verwendet. Andernfalls faellt der Renderer auf `...` zurueck.

## Font-Modell

Das Font-System ist zweistufig aufgebaut.

### FontRegistry

`FontRegistry` liest die eingebetteten Fontdefinitionen aus `config/fonts.php`.

Sie arbeitet mit echten Fontnamen als Schluessel, zum Beispiel:

- `NotoSans-Regular`
- `NotoSans-Bold`
- `NotoSans-Italic`
- `NotoSans-BoldItalic`
- `NotoSerif-Regular`
- `NotoSansMono-Regular`
- `NotoSansCJKsc-Regular`

Optional kann ein `Document` statt der globalen Config auch eine dokumenteigene `fontConfig` erhalten.

### FontDefinition

Im Dokument landen konkrete Fontobjekte, nicht nur Gruppennamen.

Aktuell gibt es zwei Hauptarten:

- `StandardFont`
- `UnicodeFont`

`StandardFont` ist fuer die PDF-Standard-14-Schriften gedacht, zum Beispiel `Helvetica`.

`UnicodeFont` ist der Pfad fuer eingebettete Fonts aus der Registry.

Bei Unicode-Fonts kommen weitere Objekte dazu, zum Beispiel:

- `CidFont`
- `FontDescriptor`
- `FontFileStream`
- `CidToGidMap`
- `ToUnicodeCMap`

Das ist der Grund, warum `Document::getDocumentObjects()` bei Fonts mehrere abhaengige Objekte einsammelt.

Fuer Stilvarianten bei Textsegmenten gilt aktuell:

- Standardfonts wie `Helvetica` werden ueber bekannte PDF-Varianten wie `Helvetica-Bold` oder `Helvetica-Oblique` aufgeloest
- eingebettete Fonts werden ueber Namenskonventionen wie `-Bold`, `-Italic` oder `-BoldItalic` gesucht
- wenn keine Variante gefunden wird, bleibt der Basisfont aktiv

## Strukturmodell

Ab PDF-Version `1.4` kann das Dokument zusaetzlich Strukturknoten fuer Tagged-PDF-nahe Ausgaben erzeugen.
Der Aufbau passiert aber nur noch dann, wenn Text oder andere Inhalte tatsaechlich mit Struktur-Tags angelegt werden.

Aktuell beteiligte Klassen:

- `Catalog`
- `StructTreeRoot`
- `StructElem`
- `ParentTree`
- `Page`

### Catalog

Der `Catalog` ist der Root-Knoten des Dokuments.

Bei PDF `>= 1.4` setzt er aktuell unter anderem:

- `/MarkInfo`
- `/Lang`
- `/StructTreeRoot`

Diese Eintraege werden nur gerendert, wenn strukturierte Inhalte vorhanden sind.

### StructTreeRoot

`StructTreeRoot` ist der Einstieg in den Strukturbaum. Er haelt:

- die obersten Strukturknoten in `/K`
- optional den Verweis auf den `ParentTree`

### StructElem

`StructElem` repraesentiert ein semantisches Strukturelement wie `Document`, `H1` oder `P`.

Aktuell unterstuetzte Tags sind auf eine feste Allowlist begrenzt.

Ein `StructElem` kann entweder:

- Kinder enthalten
- oder auf ein konkretes Marked-Content-Stueck per `MCID` zeigen

### ParentTree

`ParentTree` ordnet `StructParents`-Werte von Seiten den zugehoerigen `StructElem`-Referenzen zu.

Das ist die Bruecke zwischen Seiteninhalt und Strukturbaum.

## Render-Reihenfolge

Das PDF wird nicht aus einer einzelnen Vorlage zusammengesetzt, sondern aus indirekten Objekten.

Die aktuelle Render-Reihenfolge ist im Wesentlichen:

1. `Catalog`
2. `Pages`
3. optional `StructTreeRoot`
4. optional `ParentTree`
5. weitere `StructElem`-Objekte
6. `Info`
7. Fontobjekte inklusive Unterobjekten
8. pro Seite:
   `Page`, `Resources`, `Contents`

Wichtig: Die Struktur-Objekte tauchen nur auf, wenn strukturierte Inhalte wirklich verwendet wurden.

Danach erzeugt `PdfRenderer`:

1. `xref`
2. `trailer`
3. `startxref`
4. `%%EOF`

## Typ-System

Unter `src/Types` liegen kleine Value-Objekte fuer PDF-Grundbausteine, zum Beispiel:

- `Dictionary`
- `ArrayValue`
- `Name`
- `Reference`
- `StringValue`
- `RawValue`

Diese Klassen kapseln die PDF-Syntax auf niedriger Ebene und halten die `render()`-Methoden der Fachobjekte kompakter.

## Design-Entscheidungen im aktuellen Stand

- Das Modell ist bewusst direkt an PDF-Konzepte angelehnt, nicht an ein hohes Layout-API.
- Objekt-IDs werden zentral im `Document` vergeben.
- Seiteninhalte werden aktuell als einzelne Stream-Sequenz ohne weitere Optimierungen geschrieben.
- Ressourcen sind pro Seite getrennt.
- Tagged-PDF-nahe Struktur wird bereits aufgebaut, ist aber ein fortgeschrittener Bereich und sollte weiterhin vorsichtig erweitert werden.

## Aktuelle Einschraenkungen

- Das Layout-System ist trotz `TextFrame`, `TextSegment`, Alignment und Overflow noch textzentriert.
- Bilder sind noch nicht als fertiger End-to-End-Pfad ausgearbeitet.
- Content-Streams werden noch nicht komprimiert.
- Die Objektliste wird explizit im Dokument zusammengestellt und nicht ueber ein generisches Graph-Modell aufgeloest.

## Empfohlene Anschlussdoku

Nach dieser Datei ist [roadmap.md](roadmap.md) die sinnvollste Fortsetzung fuer geplante technische Erweiterungen und bekannte Luecken.
