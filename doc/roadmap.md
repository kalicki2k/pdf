# Roadmap

Diese Roadmap beschreibt die naechsten sinnvollen technischen Schritte fuer die Library. Sie trennt bewusst zwischen bereits stabilisierten Grundlagen und offenen Baustellen.

## Aktueller Stand

Die aktuelle Basis ist weiter als eine reine Skizze:

- PDFs werden als indirekte Objekte aufgebaut und gerendert
- `xref`, `trailer` und `startxref` werden erzeugt
- Dokument-Metadaten sind vorhanden
- Seiten, Ressourcen und Content-Streams sind getrennt modelliert
- Header und Footer koennen ueber Dokument-Callbacks auf allen neuen Seiten registriert werden
- PDF-Outlines bzw. Bookmarks koennen ueber `addOutline(...)` registriert werden
- benannte interne Ziele koennen ueber `addDestination(...)` registriert werden
- Text wird ueber registrierte Fonts gerendert
- `addParagraph()` und `TextFrame` decken ersten Absatz- und Flow-Layout-Bedarf ab
- `TextSegment` erlaubt gemischte Inline-Stile innerhalb eines Absatzes
- Textfarbe, Graustufen, CMYK und Opacity sind im Renderpfad angekommen
- `bold`, `italic`, `underline` und `strikethrough` sind vorhanden
- `HorizontalAlign` deckt `LEFT`, `CENTER`, `RIGHT` und `JUSTIFY` ab
- `TextOverflow` deckt `CLIP` und `ELLIPSIS` mit `maxLines` ab
- Listen stehen in einer ersten Stufe ueber `TextFrame::bulletList(...)`, `TextFrame::numberedList(...)` und `BulletType` zur Verfuegung
- Tabellen stehen in einer ersten Stufe ueber `table(...)`, `Table`, `TableCell`, `CellStyle`, `TableBorder` und `TablePadding` zur Verfuegung, inklusive wiederholter Header auf Folgeseiten, `colspan`, erster `rowspan`-Unterstuetzung, partiell ueberschreibbaren Zell-Borders sowie horizontaler und vertikaler Zell-Ausrichtung
- Bilder koennen als XObjects eingebunden und ueber `Image::fromFile(...)` aus Dateien geladen werden
- Linien und Rechtecke sind als erste grafische Primitive vorhanden
- gerundete Rechtecke sind ueber `addRoundedRectangle(...)` verfuegbar
- freie Pfade sind ueber `Page::path()` und `PathBuilder` verfuegbar
- Kreise sind als Convenience-API ueber `addCircle(...)` verfuegbar
- Ellipsen, Polygone, Pfeile und Sterne sind als weitere Convenience-Formen verfuegbar
- Links und URI-Annotationen sind ueber `addLink(...)`, `addText(..., link: ...)` und `TextSegment::link` verfuegbar
- interne Spruenge sind ueber `addDestination(...)`, `addInternalLink(...)` und `#ziel`-Links verfuegbar
- Badges sind als kleines zusammengesetztes Label-Element ueber `addBadge(...)` verfuegbar
- Panels sind als einfache Hinweis- und Infoboxen ueber `addPanel(...)` verfuegbar
- Callouts sind als Hinweisboxen mit Pointer ueber `addCallout(...)` verfuegbar
- Unicode-Fonts und `ToUnicode`-CMaps sind bereits angelegt
- eingebettete Fonts werden ueber `config/fonts.php` und optional dokumenteigene `fontConfig` konfiguriert
- Strukturknoten wie `StructTreeRoot`, `StructElem` und `ParentTree` werden bei Bedarf lazy aufgebaut

Das bedeutet aber nicht, dass bereits alle hoeheren PDF-Ziele erreicht sind.

## Bereits erledigte Grundlagen

Diese Punkte aus der frueheren technischen Vorbereitung sind im aktuellen Code im Kern vorhanden:

- Text-String-Escaping
- zentrale Vergabe von Objekt-IDs
- Rendern ueber explizite indirekte Objekte
- `xref`- und Trailer-Erzeugung
- grundlegende Dokument-Metadaten
- erste Unicode-Unterstuetzung
- zentrale Font-Konfiguration ueber `config/fonts.php`
- optionale Trennung zwischen normalem Text und strukturiertem Text
- erste Textfluss-API ueber `addParagraph()` und `TextFrame`
- erster Rich-Text-Pfad ueber `TextSegment`
- erste Textstil- und Alignment-API
- erste Listen-API mit Bullet- und nummerierten Listen
- erste Tabellen-API mit festen Spaltenbreiten, Zeilen, wiederholten Headern, `colspan`, erster `rowspan`-Stufe, `CellStyle`, mergebaren Zell-Borders, flexiblem Zell-Padding sowie horizontaler und vertikaler Zell-Ausrichtung
- erste Bild- und Grafik-API ueber `addImage()`, `addLine()` und `addRectangle()`
- gerundete Rechtecke, Badges, Panels und Callouts als erste zusammengesetzte Grafik-/Layout-API
- erste freie Form-API ueber `path()`
- erste Kreis-API auf Basis von Bezier-Pfaden
- weitere Form-APIs fuer Ellipsen, Polygone, Pfeile und Sterne
- erste Link-API ueber Annotationen und klickbaren Text
- erste Outline-/Bookmark-API fuer Viewer-Navigation
- erste interne Dokument-Navigation ueber benannte Ziele

## Prioritaeten

### 1. Layout und Rendering robuster machen

Der Renderpfad ist funktional, aber noch relativ direkt.

Naechste sinnvolle Verbesserungen:

- Content-Stream-Erzeugung weiter kapseln
- Byte-Laengen und Encodings konsequent pruefen
- Objektabhaengigkeiten noch klarer modellieren
- spaeter optionale Stream-Kompression vorbereiten
- Layout-Bausteine ueber `TextFrame` hinaus systematisieren
- Textdekorationen ueber Font-Metriken besser positionieren
- Stilwechsel innerhalb eines Absatzes weiter optimieren, damit nicht unnoetig viele Text-Operatoren entstehen

Warum das wichtig ist:

- Das Rendering ist der kritische Pfad des Projekts.
- Kleine Fehler in Laengen, Offsets oder Encodings machen PDFs schnell unbrauchbar.

### 2. Strukturmodell fachlich absichern

Das Projekt kann bereits Tagged-PDF-nahe Strukturinformationen erzeugen. Dieser Bereich ist technisch heikel und sollte nur kontrolliert erweitert werden.

Offene Punkte:

- erlaubte Struktur-Tags weiter definieren
- Verschachtelung und Eltern-Kind-Beziehungen genauer absichern
- Verhalten fuer komplexere Inhalte festlegen
- Struktur gegen echte Reader und Validatoren pruefen
- klar dokumentieren, welche Inhalte bewusst unstrukturiert bleiben duerfen

Wichtig:

- Eine teilweise richtige Struktur ist riskanter als eine bewusst reduzierte Struktur.
- Neue Features in diesem Bereich sollten immer gegen reale PDF-Werkzeuge validiert werden.

### 3. API fuer weitere Inhalte erweitern

Aktuell ist Text der belastbare End-to-End-Fall. Weitere Inhaltstypen sollten erst dann hinzukommen, wenn sie in API, Ressourcenmodell und Rendering sauber verankert sind.

Naechste Kandidaten:

- weitere grafische Primitive auf Basis des vorhandenen Path-Builders, zum Beispiel Sprechblasen oder komplexere Diagrammformen
- Ausbau der Tabellen-API, zum Beispiel fuer `rowspan` ueber Seitenumbrueche und noch feinere Zellstile
- Ausbau der Bild-API, vor allem fuer PNG mit Alpha-Kanal
- feinere Typografie fuer Dekorationen wie `underline` und `strikethrough`

Vor einem groesseren Inhaltstyp sind im Textsystem noch sinnvolle Zwischenstufen moeglich:

- segment-spezifische Fontgroessen oder Fontfamilien innerhalb eines Absatzes
- Ausbau der Listen-API, zum Beispiel fuer verschachtelte Listen, weitere Nummernformate und Listenstile
- Tabs oder einfache Spalten
- explizite Absatz- und Zeilenabstaende als Style-Objekte statt weiterer Parameter

Ziel:

- neue Features nicht nur als Klassen anlegen, sondern komplett bis zum finalen PDF-Pfad durchziehen

### 4. Ressourcenmodell ausbauen

Die Font-Nutzung pro Seite ist bereits sauber getrennt, und die Font-Definitionen sind jetzt als Konfiguration ausgelagert. Das Ressourcenmodell wird mit mehr Features trotzdem anspruchsvoller.

Offene Erweiterungen:

- Wiederverwendung und Caching von XObjects fuer Bilder
- gemeinsam genutzte Ressourcen sauber modellieren
- Vererbung oder zentrale Verwaltung nur dann einfuehren, wenn sie echten Nutzen bringt
- Konfigurationsmodell fuer groessere Font-Sets weiter ausbauen, ohne `addFont()` nach aussen zu verkomplizieren

### 5. Dokumentstandards klar abgrenzen

Syntaktisch lesbare PDF-Dateien sind nicht automatisch:

- Tagged-PDF-konform
- barrierefrei
- PDF/A-konform
- PDF/UA-konform

Deshalb sollte das Projekt fuer sich selbst klar festlegen, was kurz- und mittelfristig wirklich Ziel ist.

Sinnvolle Reihenfolge:

1. stabile allgemeine PDF-Erzeugung
2. saubere strukturierte PDFs
3. gezielte Konformitaet gegen PDF/A oder PDF/UA

## Empfohlene Umsetzungsreihenfolge

### Phase 1: stabile Kernbibliothek

Fokus:

- Rendering haerten
- Tests erweitern
- API fuer Text und Fonts stabil halten
- TextFrame/Paragraph-Layout weiter schaerfen
- Doku vervollstaendigen

Ergebnis:

- verlaessliche Basis fuer normale PDFs ohne vorschnelle Feature-Ausweitung

### Phase 2: kontrollierte Inhaltserweiterung

Fokus:

- Bilder oder weitere grafische Elemente end-to-end einfuehren
- Ressourcenmodell entsprechend erweitern
- API konsistent halten

Ergebnis:

- groesserer praktischer Nutzen ohne Architekturbruch

### Phase 3: strukturierte und validierbare PDFs

Fokus:

- Strukturmodell fachlich absichern
- Tagged-PDF-Verhalten pruefen
- externe Validatoren in den Entwicklungsprozess aufnehmen

Ergebnis:

- bessere Grundlage fuer Accessibility und weitergehende Standards

## Validierung

Der bisherige interne Fortschritt sollte systematisch gegen mehrere Werkzeuge geprueft werden.

Sinnvolle Kandidaten:

- `qpdf`
- Poppler-Tools
- Acrobat Reader oder Acrobat Preflight
- `veraPDF` fuer PDF/A
- PAC fuer Accessibility und PDF/UA

Diese Werkzeuge pruefen unterschiedliche Ebenen:

- Syntax
- Objektstruktur
- Reader-Kompatibilitaet
- Standard-Konformitaet

## Offene Produktfragen

Diese Fragen beeinflussen die Architektur direkt und sollten frueh beantwortet werden:

- Soll die Library zuerst vor allem gueltige Standard-PDFs erzeugen?
- Ist Tagged PDF ein Kernziel oder ein spaeter Ausbau?
- Sind PDF/A oder PDF/UA echte Projektziele?
- Soll die API langfristig low-level bleiben oder schrittweise mehr Layout-Abstraktion bekommen?

## Kurzempfehlung

Der naechste technisch saubere Schwerpunkt ist nicht sofort ein weiterer Standard, sondern eine noch belastbarere Basis:

1. Rendering und Tests haerten
2. das Layout-System im Textpfad weiter absichern und erst dann ueber Text hinaus erweitern
3. den naechsten echten Inhaltstyp vollstaendig einfuehren
4. Struktur- und Standardthemen danach gezielt validieren
