# Architecture

Diese Datei beschreibt die aktuelle Architektur der PDF-Library. Sie dokumentiert den Ist-Zustand des Codes und nicht eine Zielarchitektur.

## Ueberblick

Die Library baut ein PDF als kleines Objektmodell in PHP auf und rendert dieses Modell am Ende in die PDF-Syntax.

Der Hauptfluss ist:

1. `Document` sammelt Metadaten, Fonts und Seiten.
2. `Page` nimmt Inhalte wie Text, Bilder und einfache grafische Primitive entgegen.
3. `Contents` und `Resources` sammeln die seitenbezogenen Daten.
4. `PdfRenderer` rendert alle indirekten Objekte in der richtigen Reihenfolge.
5. Zum Schluss werden `xref`, `trailer` und `startxref` angehaengt.

## Kernklassen

### Document

`Document` ist der Einstiegspunkt der API und das zentrale Aggregat.

Verantwortlich fuer:

- PDF-Version und Metadaten
- globale Font-Registrierung
- optionale dokumenteigene Font-Konfiguration
- Verwaltung aller Seiten
- Vergabe von Objekt-IDs
- lazy Aufbau der Strukturdaten fuer Tagged-Inhalte
- Start des Renderings

Wichtige Methoden:

- `addPage()` erzeugt eine neue Seite mit eigener `Contents`- und `Resources`-Instanz
- `addFont()` registriert Fonts im Dokument
- `addKeyword()` pflegt die Dokument-Keywords
- `render()` delegiert an `PdfRenderer`
- `getDocumentObjects()` liefert die Menge aller zu rendernden indirekten Objekte

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

Die wichtigsten APIs sind aktuell `addText(...)`, `addParagraph(...)`, `textFrame(...)`, `table(...)`, `addLine(...)`, `addRectangle(...)`, `path()`, `addCircle(...)`, `addEllipse(...)`, `addPolygon(...)`, `addArrow(...)`, `addStar(...)`, `addImage(...)` und `addLink(...)`.

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

7. `Page::table(...)` erzeugt ein `Table`-Objekt mit fester Breite und festen Spaltenbreiten.
8. `Table::addRow(...)` normalisiert Zellinhalte zu `TableCell`-Instanzen.
9. Die Zeilenhoehe wird ueber `countParagraphLines(...)` auf Basis des Zellinhalts berechnet.
10. Zellhintergruende und Borders werden ueber `Rectangle` gerendert.
11. Zelltext wird ueber den vorhandenen Absatzpfad in die jeweilige Zelle geschrieben.
12. Wenn eine Zeile nicht mehr passt, erzeugt `Table` intern eine Folge-Seite und rendert dort weiter.
13. Vor gemerkten Body-Zeilen werden vorhandene Header-Zeilen auf der neuen Seite erneut gerendert.

Bei grafischen Inhalten kommt stattdessen dazu:

7. `Line`- und `Rectangle`-Elemente werden direkt in `Contents` abgelegt.
8. `PathBuilder` erzeugt aus `moveTo(...)`, `lineTo(...)`, `curveTo(...)` und `close()` ein `Path`-Element fuer freie Formen.
9. `addCircle(...)` baut darauf einen Kreis aus vier kubischen Bezier-Segmenten auf.
10. `addEllipse(...)`, `addPolygon(...)`, `addArrow(...)` und `addStar(...)` nutzen dieselbe Path-/Line-Infrastruktur fuer weitere Formen.
11. Bilder werden als eigene indirekte XObjects erzeugt und in den Seiten-`Resources` unter `/XObject` registriert.
12. Ein separates `DrawImage`-Element referenziert die Bild-Resource im Content-Stream per `/ImN Do`.
13. Links werden als Annotationen im Seiten-Dictionary unter `/Annots` referenziert.

### TextFrame

`TextFrame` ist eine kleine Layout-Hilfe ueber `Page`.

Verantwortlich fuer:

- gemeinsamen Textbereich mit `x`, `y` und Breite
- Cursor-Fuehrung zwischen mehreren Textbloecken
- Absatzeinzug ueber `paragraph(...)`
- Ueberschriften ueber `heading(...)`
- automatische Folge-Seiten bei Ueberlauf
- Weitergabe von Alignment, `maxLines` und `TextOverflow`
- Listen ueber `bulletList(...)` und `numberedList(...)`

`bulletList(...)` und `numberedList(...)` bauen bewusst auf dem vorhandenen Absatzpfad auf:

- Marker oder Nummer werden als eigenes `Text`-Element gerendert
- der Listeninhalt wird als Absatz mit reduziertem Textbereich gerendert
- dadurch bleiben Umbruch, Links und Folge-Seiten konsistent
- `BulletType` kapselt die aktuell unterstuetzten Standard-Symbole fuer Bullet-Listen
- `numberedList(...)` nutzt denselben Listenpfad mit laufenden Dezimalzahlen und optionalem `startAt`

### Table und TableCell

`Table` ist eine erste Layout-Hilfe fuer tabellarische Inhalte mit fester Spaltenstruktur.

Verantwortlich fuer:

- Startposition, Tabellenbreite und Spaltenbreiten
- Cursor-Fuehrung zwischen den Tabellenzeilen
- Header- und Row-Styles
- Wiederholung von Header-Zeilen bei Seitenwechsel
- Berechnung der Zeilenhoehe ueber den vorhandenen Absatz-Umbruch
- Seitenwechsel, wenn eine komplette Zeile nicht mehr passt

`TableCell` repraesentiert eine einzelne Zelle mit:

- `text` als `string` oder `TextSegment[]`
- `align`
- optionaler `fillColor`
- optionaler `textColor`
- optionaler `opacity`

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

Aktuell wird das ueber `Page::path()` und `PathBuilder` aufgebaut:

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

`Image::fromFile(...)` erkennt aktuell `JPEG` direkt und unterstuetzte `PNG`-Dateien ohne Alpha-Kanal automatisch.

### LinkAnnotation

Links werden aktuell nicht als sichtbares Content-Element gerendert, sondern als Annotation.

`LinkAnnotation` rendert ein eigenes indirektes `/Annot`-Objekt mit:

- `/Subtype /Link`
- einem Rechteck in `/Rect`
- einer URI-Action ueber `/A << /S /URI /URI (...) >>`
- einer Rueckreferenz auf die Seite ueber `/P`

Damit gibt es zwei API-Ebenen:

- `Page::addLink(...)` fuer beliebige klickbare Flaechen
- `Page::addText(..., link: ...)` und `TextSegment::link` fuer aus Textbreite abgeleitete Link-Rechtecke

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

`Page::addParagraph(...)` und `TextFrame::paragraph(...)` akzeptieren entweder einen einfachen `string` oder eine Liste von `TextSegment`-Objekten.

### TextAlign und TextOverflow

Fuer Absatzlayout gibt es aktuell zwei kleine Steuerobjekte:

- `TextAlign` mit `LEFT`, `CENTER`, `RIGHT`, `JUSTIFY`
- `TextOverflow` mit `CLIP` und `ELLIPSIS`

`JUSTIFY` verteilt zusaetzlichen Wortabstand nur auf automatisch umgebrochene Zeilen. Die letzte Absatzzeile und harte Zeilenumbrueche werden nicht gestreckt.

`TextOverflow` greift nach dem Umbruch:

- `CLIP` verwirft alle Zeilen hinter `maxLines`
- `ELLIPSIS` kuerzt die letzte sichtbare Zeile so, dass `...` noch in die Breite passt

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
