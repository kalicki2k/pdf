# Architektur

## Zielbild

`pdf2` ist als native PHP-PDF-Engine aufgebaut. Die Kernarchitektur rendert PDF-Dateien selbst und benutzt keine externen PDF-Binaertools als eigentlichen Renderpfad. Externe Werkzeuge wie `qpdf` und `veraPDF` werden nur fuer Validierung und Regressionstests verwendet.

## Schichten im aktuellen Code

Der Code ist grob entlang dieser Verantwortlichkeiten aufgeteilt:

```text
src/
├─ Color/        Farbmodelle und Hilfswerte
├─ Debug/        strukturierte Debug- und Performance-Events
├─ Document/     Public API, Builder, Profilregeln, PDF-nahe Objektplanung
├─ Drawing/      Vektorprimitive, Pfade, Units, Grafik-Accessibility
├─ Encryption/   Sicherheitsprofile, Permission-Bits, Objektverschluesselung
├─ Font/         Standardfonts, Embedded Fonts, Parser und Subsetter
├─ Image/        Decoder, Rastermodell, PDF-Bildquellen
├─ Layout/       Tabellenlayout
├─ Page/         Seitendaten, Annotationen, Margins, Page-Objekte
├─ Text/         Bidi, Script-Shaping, Segmentierung, Textoptionen
├─ Writer/       Low-level-Serializer, XRef, Trailer, Outputs
└─ Pdf.php       kleine Fassade fuer Builder, Rendern, Signieren und Messen
```

Die Public API liegt praktisch in `Pdf`, `Document::make()` und `DefaultDocumentBuilder`. Der tatsaechliche Build- und Schreibpfad ist aber mehrstufig und stark in `Document/` und `Writer/` gekapselt.

## Render-Pipeline

Der normale Renderpfad besteht aus drei Hauptphasen:

1. Builder-Phase
   `DefaultDocumentBuilder` sammelt Dokumentmetadaten, Seiteninhalte, Tabellen, Bilder, Annotationen, Formulare, Outlines, TOC-Eintraege, Attachments und optionale Tagged-PDF-Strukturen.
2. Plan-Phase
   `DocumentSerializationPlanBuilder` validiert das `Document`, alloziert Objekt-IDs und baut daraus einen serialisierbaren Objektgraphen.
3. Write-Phase
   `Kalle\Pdf\Writer\Renderer` schreibt den fertigen Plan inklusive Body, XRef und Trailer in `FileOutput`, `StreamOutput` oder `StringOutput`.

Das ist im Code explizit getrennt:

- `DocumentRenderer` orchestriert Plan- und Write-Phase
- `DocumentSerializationPlanBuilder` baut den PDF-Objektgraphen
- `Writer\Renderer` serialisiert Bytes

## Builder-Phase im Detail

`DefaultDocumentBuilder` ist die groesste Orchestrierungsklasse im Repository. Sie haelt waehrend des Aufbaus unter anderem:

- aktuelle Seitendaten und Cursor-Position fuer Flow-Text
- Font- und Image-Ressourcen pro Seite
- Annotationen und Named Destinations pro Seite
- Tabellenlayout-Zwischenzustand
- Header/Footer-Renderer
- Tagged-PDF-Sammelstrukturen fuer Text, Tabellen, Listen, Figuren und Formulare
- Dokumentweite Metadaten, Profil, Verschluesselung, Outlines, TOC und Attachments

Der Builder ist auf Clone-basierte fluente API ausgelegt. Viele Methoden liefern ein neues Builder-Objekt zurueck, statt globalen Zustand nach aussen freizugeben.

## Plan-Phase im Detail

`DocumentSerializationPlanBuilder` ist der zentrale Uebergang von hohem Dokumentmodell zu PDF-Objekten. Relevante Teilbuilder sind:

- `DocumentPageAndFormObjectBuilder`
- `DocumentFontAndImageObjectBuilder`
- `DocumentAttachmentObjectBuilder`
- `DocumentMetadataObjectBuilder`
- `DocumentOutlineObjectBuilder`
- `DocumentTaggedPdfObjectBuilder`

Vor dem eigentlichen Schreiben laufen mehrere Validierungen:

- `DocumentSerializationPlanValidator` fuer allgemeine Buildbarkeit
- `PdfA1aTaggedStructureValidator` fuer den engen Tagged-PDF/A-1a-Pfad
- `PdfAObjectGraphValidator` und `PdfA1ObjectGraphValidator` auf dem finalen Objektgraphen
- Profil- und Policy-Checks ueber `Profile` und zugehoerige Helper

Diese Trennung ist wichtig: Viele Fehler zeigen sich nicht schon beim Builder-Aufruf, sondern erst wenn klar ist, welche PDF-Objekte wirklich entstehen.

## Textsystem

Das Textsystem besteht nicht nur aus einem String-Writer. Im aktuellen Code sind getrennte Bausteine vorhanden fuer:

- Textoptionen und Segmentierung (`TextOptions`, `TextSegment`, `TextLink`)
- Breitenmessung (`TextMeasurer`)
- Bidi-Aufloesung (`BidiResolver`, `BidiRun`)
- Script-Erkennung und Font-Run-Mapping
- Shaping-Pfade fuer mehrere Schriftsysteme, u. a. Arabisch, Devanagari, Bengali und Gujarati
- Embedded-Font-Parsing und Subsetting in `Font/`

Wichtig fuer Wartung: Das Projekt verwendet keinen externen Shaping-Stack. Das Verhalten ist daher bewusst auf den im Code und in Tests abgedeckten Scope begrenzt.

## Tagged PDF und logische Struktur

Tagged-PDF-Unterstuetzung ist kein nachtraeglicher Export-Schalter, sondern zieht sich durch den Builder- und Objektaufbau:

- Builder sammelt strukturierte Referenzen fuer Text, Tabellen, Listen, Figuren und Formulare
- `DocumentTaggedPdfObjectBuilder` erzeugt daraus `StructTreeRoot`, `StructElem`, ParentTree und OBJR/MCID-Verknuepfungen
- Profile bestimmen, ob Tagged PDF erforderlich, optional oder unzulaessig ist

Fuer semantischen Inhalt sind zwei Ebenen relevant:

- Container-Struktur ueber `beginStructure()` und `endStructure()`
- Blattrollen ueber `TextOptions(tag: ...)`, `GraphicsAccessibility`, `ImageAccessibility` und Annotation-/Form-Accessibility

## Tabellenlayout

Tabellen liegen in einer eigenen Layoutschicht unter `src/Layout/Table/`. Diese Schicht berechnet:

- Spaltenbreiten
- Zell-Padding und Borders
- `rowspan`/`colspan`
- wiederholte Header- und Footer-Zeilen
- deterministische Seitenumbrueche zwischen Zeilen und zusammenhaengenden Rowspan-Gruppen

Die PDF-nahe Ausgabe der Tabelle passiert spaeter wieder im Dokumentpfad. Dadurch bleibt Layout-Berechnung von der eigentlichen Serialisierung getrennt.

## Bilder und Fonts

Bilder und Fonts folgen demselben Architekturprinzip:

- Import/Parsing und Normalisierung in eigenen Subsystemen
- spaetere Einbettung als PDF-Objekte erst im Dokumentplan

Relevante Einstiegspunkte:

- Bilder: `ImageSource`, `ImageSourceImporter`
- Fonts: `EmbeddedFontSource`, `EmbeddedFontDefinition`, Subsetter und Parser in `Font/`

## Verschluesselung und Signaturen

Verschluesselung und Signaturen sind bewusst getrennte Pfade:

- Verschluesselung wird waehrend des normalen Plan-/Write-Pfads ueber `Encryption`, `EncryptionProfileResolver`, `StandardSecurityHandler` und `ObjectEncryptor` verarbeitet.
- Signaturen laufen ueber `DocumentSigner` als inkrementelles Update auf ein bereits gerendertes PDF.

Der aktuelle Code blockiert kryptographisches Signieren verschluesselter Dokumente explizit.

## Observability

Das Debugsystem erzeugt keine Overlay-Ausgaben im PDF, sondern strukturierte Events. Drei Eventkanaele sind vorgesehen:

- Lifecycle
- PDF-Struktur
- Performance

Typische Events sind `document.created`, `page.added`, `object.created`, `object.serialized`, `xref.written`, `trailer.written`, `document.render`, `page.render` und `file.write`.

## Interne Werkzeuge

Fuer Wartung und Diagnose existieren neben Tests mehrere Skripte:

- `bin/benchmark-performance.php` fuer Szenarien-Benchmarks
- `bin/profile-performance.php` fuer Profiling ueber `InMemoryDebugSink`
- `bin/validate-qpdf.sh`, `bin/validate-pdfa.sh`, `bin/validate-pdfua.sh`
- zahlreiche PDF/A-Regressionsgeneratoren und -Runner in `bin/`

## Aktuelle Architekturauffaelligkeiten

Beim Lesen des Repos faellt auf:

- `src/Writer/` ist der aktive Low-level-Pfad, auf den `DocumentRenderer` und `Pdf` zeigen.
- die Regressionen sind zahlreich und profilstark, daher ist ein Make-Sammelziel fuer den gesamten Regressionslauf wichtig, um lokale Validierung und CI konsistent zu halten.
