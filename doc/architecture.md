# Architecture

Diese Datei beschreibt die aktuelle Architektur der PDF-Library. Sie dokumentiert den Ist-Zustand des Codes und nicht eine Zielarchitektur.

## Ueberblick

Die Library baut ein PDF als kleines Objektmodell in PHP auf und rendert dieses Modell am Ende in die PDF-Syntax.

Der Hauptfluss ist:

1. `Document` sammelt Metadaten, Fonts und Seiten.
2. `Page` nimmt Inhalte wie Text entgegen.
3. `Contents` und `Resources` sammeln die seitenbezogenen Daten.
4. `PdfRenderer` rendert alle indirekten Objekte in der richtigen Reihenfolge.
5. Zum Schluss werden `xref`, `trailer` und `startxref` angehaengt.

## Kernklassen

### Document

`Document` ist der Einstiegspunkt der API und das zentrale Aggregat.

Verantwortlich fuer:

- PDF-Version und Metadaten
- globale Font-Registrierung
- Verwaltung aller Seiten
- Vergabe von Objekt-IDs
- Aufbau der Strukturdaten fuer PDF 1.4
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
- Vergabe lokaler Marked-Content-IDs pro Seite

Die wichtigste API ist aktuell `addText(...)`.

Dabei passiert intern:

1. Die angeforderte Schrift wird in den registrierten Dokument-Fonts gesucht.
2. Der Text wird gegen die Font-Unterstuetzung validiert.
3. Der Text wird fontspezifisch encodiert.
4. Ein `Text`-Element wird im `Contents`-Stream der Seite abgelegt.
5. Parallel wird ein `StructElem` fuer das Strukturmodell erzeugt.

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
- Vergabe interner Ressourcen-Namen wie `F1`, `F2`, ...
- Rendern des `/Font`-Dictionarys

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

Aktuell ist im produktiven Pfad vor allem `Text` relevant.

### Text

`Text` rendert einen einzelnen Textblock in PDF-Operatoren.

Der aktuelle Output folgt grob diesem Muster:

```text
BT
/F1 24 Tf
20 265 Td
/H1 << /MCID 0 >> BDC
(Hallo PDF) Tj
EMC
ET
```

Dabei kombiniert das Element:

- Font-Ressource
- Schriftgroesse
- Position
- encodierten Inhalt
- Struktur-Tag
- Marked-Content-ID

## Font-Modell

Das Font-System ist zweistufig aufgebaut.

### FontRegistry

`FontRegistry` liefert vordefinierte Font-Gruppen:

- `sans`
- `serif`
- `mono`
- `global`

Diese Gruppen werden in konkrete Fontdefinitionen uebersetzt, inklusive Dateipfad und Unicode-Flag.

### FontDefinition

Im Dokument landen konkrete Fontobjekte, nicht nur Gruppennamen.

Aktuell gibt es zwei Hauptarten:

- `StandardFont`
- `UnicodeFont`

Bei Unicode-Fonts kommen weitere Objekte dazu, zum Beispiel:

- `CidFont`
- `FontDescriptor`
- `FontFileStream`
- `CidToGidMap`
- `ToUnicodeCMap`

Das ist der Grund, warum `Document::getDocumentObjects()` bei Fonts mehrere abhaengige Objekte einsammelt.

## Strukturmodell

Ab PDF-Version `1.4` erzeugt das Dokument zusaetzlich Strukturknoten fuer Tagged-PDF-nahe Ausgaben.

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

- Das Layout-System ist minimal; Positionen werden direkt gesetzt.
- Bilder sind noch nicht als fertiger End-to-End-Pfad ausgearbeitet.
- Content-Streams werden noch nicht komprimiert.
- Die Objektliste wird explizit im Dokument zusammengestellt und nicht ueber ein generisches Graph-Modell aufgeloest.

## Empfohlene Anschlussdoku

Nach dieser Datei sind zwei weitere Dokumente sinnvoll:

- `doc/rendering-flow.md` fuer eine detaillierte Beschreibung des PDF-Aufbaus
- [roadmap.md](roadmap.md) fuer geplante technische Erweiterungen und bekannte Luecken
