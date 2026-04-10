# Structure Migration

Diese Notiz beschreibt den aktuellen Strukturstand nach der Aufloesung des `Internal`-Quellbaums.

Sie dokumentiert die erreichte Zielstruktur und haelt die wichtigsten Paketregeln fuer weitere Refactorings fest.

## Ziele

Die Migration soll diese Probleme beheben:

- `src/Document` ist historisch zum Sammelbecken fuer fast alle Verantwortungen geworden
- Public API und interne Engine verwenden kollidierende Namen wie `Document`, `Page` und `Table`
- Orchestrierung, Dokumentmodell und PDF-spezifische Infrastruktur liegen heute zu eng beieinander
- einige runtime-relevante Ressourcen liegen ausserhalb von `src`

Die Migration folgt weiterhin diesen Grundsaetzen:

- Verhalten erhalten
- kleine, sichere Schritte
- Verantwortlichkeiten sauberer schneiden
- keine neue God-Class erzeugen
- bestehende Low-Level-Pakete nur dann verschieben, wenn ein echter Strukturgewinn entsteht

## Status April 2026

Diese Migrationsphase ist nach den letzten Strukturschritten in diesem Zustand:

- die dokumentweite Orchestrierung liegt jetzt unter `Document`
- Vorbereitung und Serialisierung sind dort in `Preparation` und `Serialization` geschnitten
- zentrale Kernobjekte des Dokument- und Seitenzustands liegen jetzt direkt unter `Document` und `Page`
- wiederverwendbare Bytequellen liegen jetzt komplett unter `Binary`
- der fruehere Root-Block `Layout` ist aufgeloest; Geometrie, Seitenmasse, Alignment und Overflow liegen jetzt unter `Layout`, TOC-Typen unter `Document/TableOfContents`
- generische Stil-Primitiven `Color` und `Opacity` liegen jetzt unter `Style`
- komponentennahe Styles fuer Badges, Panels und Callouts liegen jetzt unter `Page/Content/Style`
- PDF-Action-Typen liegen jetzt unter `Action`
- PDF-Annotation-Stiltypen und das Marker-Interface liegen jetzt unter `Page/Annotation`
- `LinkTarget` liegt jetzt unter `Page/Link`, weil es die seitennahe Linkziel-Semantik fuer Text und Annotationen beschreibt
- der technische Font-Kern liegt jetzt unter `Font`
- gemeinsame Formularoptionen fuer Seitenwidgets liegen jetzt unter `Page/Form`
- konkrete Seitenannotationen und Formular-Widgets liegen unter `Page/Annotation` und `Page/Form`
- `AcroForm` und `RadioButtonField` liegen unter `Document/Form`
- Text-Eingabetypen liegen jetzt unter `Layout/Text/Input`; tabellenspezifische Eingabe- und Stiltypen liegen unter `Layout/Table`
- interne Text- und Tabellen-Layoutimplementierungen liegen unter `Layout/Text` und `Layout/Table`
- `OptionalContent` und `Outline` liegen jetzt unter `Document`, weil sie dokumentweiten Zustand und Navigation modellieren
- Tagged-PDF-Strukturtypen liegen jetzt unter `TaggedPdf`, weil sie technischer Dokumentkern und keine Root-Public-API sind
- die alte `src/Document`-Struktur ist als internes Codepaket entfernt

## Ergebnis

Die Public API bleibt im Wurzel-Namespace:

- `Kalle\\Pdf\\Document`
- `Kalle\\Pdf\\Page`
- `Kalle\\Pdf\\Table`
- `Kalle\\Pdf\\TextFrame`

Darunter wird die interne Struktur schrittweise in diese Ebenen getrennt:

```text
src/
    Action/
    Binary/
    Document/
      Form/
      Preparation/
      Serialization/
      TableOfContents/
    Encryption/
      Crypto/
      Object/
      Profile/
      Standard/
      Stream/
    Font/
    Layout/
      Geometry/
      Page/
      Table/
      Text/
        Input/
      Value/
    Object/
    Security/
      EncryptionAlgorithm.php
      EncryptionOptions.php
      EncryptionPermissions.php
    Page/
      Annotation/
      Content/
        Style/
      Form/
      Link/
    Render/
    PdfType/
    TaggedPdf/
    Style/
```

## Paketregeln

### Public API

Die Root-Klassen bleiben kleine Fassaden.

Regeln:

- keine tiefe PDF-Logik
- keine Objektlisten aufbauen
- keine PDF-Syntax rendern
- nur Uebersetzung auf interne Use-Cases und Modelle

### Kernpakete
Hier liegt der Dokumentkern samt Ablaufsteuerung fuer Aufbau und Ausgabe.

Beispiele:

- Vorbereitung vor dem finalen Schreiben
- Serialisierungsplanung
- Writer-Orchestrierung
- Dokumentweite Guards und Manager
- interner Encryption-Kern
- interner Font-Kern

Regeln:

- kennt Modell und Infrastruktur
- kapselt internen mutierbaren Dokumentzustand
- soll keine PDF-Typen oder Streamsyntax im Detail modellieren
- soll lesbar den Ablauf erklaeren

### Style

Die generischen Stil-Primitiven liegen jetzt gesammelt unter `Style`.

Beispiele:

- `Color`
- `Opacity`

Regeln:

- enthaelt nur kleine technische Stil-Primitiven
- keine Seiten- oder Komponentenlogik
- kann von Text-, Tabellen-, Formular- und Zeichenpfaden gemeinsam verwendet werden

### Layout

Hier liegen jetzt sowohl die kleinen Layout-Primitiven als auch die internen Layout-Implementierungen.

Beispiele:

- `Layout/Geometry` fuer `Position`, `Rect` und `Insets`
- `Layout/Page` fuer `PageSize` und `Units`
- `Layout/Value` fuer `HorizontalAlign`, `VerticalAlign`, `TextOverflow` und `BulletType`
- `Layout/Text/Input` fuer Text-Eingabetypen wie `TextOptions`, `ParagraphOptions`, `TextBoxOptions`, `FlowTextOptions`, `ListOptions` und `TextSegment`
- `Layout/Table/Definition` fuer deklarative Tabellen-Eingabetypen wie `TableCell` und `TableCaption`
- `Layout/Table/Style` fuer deklarative Tabellen-Stilobjekte wie `TableStyle`, `RowStyle` und `CellStyle`
- `Layout/Text` fuer Absatzlayout, Textboxen und Textframes
- `Layout/Table` fuer Tabellenzustand, Zeilengruppen, Pagination und Rendering

Regeln:

- kennt Seiten- und Dokumentkern und liefert auch die kleinen Layout-Primitiven fuer oeffentliche Signaturen
- kapselt Layout- und Rendering-Details fuer mehrseitige Text- und Tabellenfluesse
- verwendet die Text-Eingabetypen aus `Layout/Text/Input` und die internen Tabellen-Definitionen als Eingaben

### Document/TableOfContents

Die TOC-Konfiguration liegt jetzt nahe an der Dokumentvorbereitung statt in einem generischen Layout-Paket.

Beispiele:

- `TableOfContentsOptions`
- `TableOfContentsPlacement`
- `TableOfContentsStyle`

Regeln:

- beschreibt dokumentweite TOC-Konfiguration und Platzierung
- liegt beim Dokumentkern, weil diese Typen direkt in die Vorbereitungsphase greifen

### Action, Annotation und Form

Diese Bereiche sind jetzt zwischen internem PDF-Mechanismus, Public API, Seitenimplementierung und Dokumentmodell geschnitten.

Beispiele:

- `Action` fuer PDF-Action-Dictionaries von Buttons und Links
- `Page/Annotation/Style` fuer Annotation-Border- und Linienend-Stile
- `Page/Annotation/PageAnnotation` als gemeinsames Marker- und Related-Objects-Interface
- `Page/Annotation` fuer konkrete Seitenannotationen und ihre Koordination
- `Page/Form` fuer Widget-Erzeugung, Appearance-Streams und gemeinsame Formularoptionen
- `Page/Link` fuer logische Linkziele, die von Public API, Textlayout und Link-Annotationen geteilt werden
- `Document/Form` fuer dokumentweite AcroForm-Objekte

Regeln:

- PDF-Action-Typen liegen intern, auch wenn einzelne Public-API-Signaturen sie referenzieren
- annotation-spezifische PDF-Typen liegen intern nahe an der Seitenannotation-Schicht
- formularspezifische Optionen fuer Seitenwidgets liegen intern nahe an der Widget-Erzeugung
- konkrete Seitenobjekte und Builder liegen nahe am Seitenkern
- dokumentweite Formularzustandsobjekte liegen im Dokumentkern statt im Ablaufcode

### Page/Content/Style

Die komponentenspezifischen Styles fuer seitennahe Bausteine liegen direkt bei der Content-Schicht.

Beispiele:

- `BadgeStyle`
- `PanelStyle`
- `CalloutStyle`

Regeln:

- enthaelt deklarative Stil-Value-Types fuer Badges, Panels und Callouts
- liegt nahe an `PageComponents`, weil diese Typen nur dort fachlich Sinn ergeben
- verwendet `Style` fuer gemeinsame Farb- und Opacity-Primitiven

### Font

Hier liegt der technische Font-Kern der PDF-Erzeugung.

Beispiele:

- `FontRegistry` und `FontPreset` fuer eingebaute Font-Presets
- `StandardFont`, `UnicodeFont`, `CidFont` und ihre PDF-Hilfsobjekte
- `OpenTypeFontParser`, `UnicodeGlyphMap` und `UnicodeFontWidthUpdater`

Regeln:

- keine eigene Public-API-Schicht, solange `Document::registerFont(...)` mit primitiven Werten auskommt
- Font-Presets, Parser, Width-Updates und konkrete PDF-Fontobjekte bleiben nah beieinander
- seiten- und formularspezifische Nutzung erfolgt ueber `Page` statt ueber Root-Fassaden

### TaggedPdf

Hier liegen Tagged-PDF-Strukturtypen und die Bausteine des Strukturbaums.

Beispiele:

- `StructElem`
- `StructTreeRoot`
- `ParentTree`
- `MarkedContentReference`
- `StructureTag`

Regeln:

- beschreibt Tagged-PDF-Semantik und Strukturbaum-Verknuepfungen
- ist kein Public-API-Paket mehr
- bleibt getrennt von `Document/Structure`, das Root-Objekte wie `Catalog` und `Pages` enthaelt

### Low-Level-Pakete

Diese technischen Bausteine bleiben erhalten, sind aber jetzt konsequent intern geschnitten:

- `Object`
- `PdfType`
- `Render`
- `TaggedPdf`

Grund:

- sie sind relativ kohärent und bilden den PDF-Kern
- `Object` ist keine Public API, sondern technische Basis fuer indirekte PDF-Objekte
- `PdfType` ist keine Public API, sondern buendelt PDF-Grundwerte wie Dictionary-, Name-, String- und Reference-Werte
- `Render` ist keine Public API, sondern der technische Ausgabe- und Serialisierungskern
- `TaggedPdf` ist keine Public API, sondern der technische Strukturbaum fuer PDF/UA- und Tagged-PDF-Pfade
- `Security` enthaelt jetzt die Public-Konfiguration, `Encryption` den technischen Kryptokern

## Geplante Migrationsreihenfolge

Die Strukturmigration wird in diese groben Schritte geschnitten:

1. Zielbild dokumentieren
2. Repo-Hygiene bereinigen
3. Runtime-Ressourcen klarer schneiden
4. Zielpakete vorbereiten
5. interne Namenskollisionen fuer `Document` aufloesen
6. interne Namenskollisionen fuer `Page` aufloesen
7. interne Namenskollisionen fuer `Table` aufloesen
8. Public API an die internen Namen anpassen
9. Orchestrierung nach `Document` verschieben
10. Dokumentmodell nach `Model` verschieben
11. Text- und Tabellenlayout in Public-Value-Types und `Layout` schneiden
12. historische Test- und Reststrukturen schrittweise nachziehen

## Nicht-Ziele

Diese Migration ist bewusst keine komplette fachliche Neuerfindung.

Nicht geplant:

- neues API-Design fuer Nutzer in einem grossen Schritt
- One-Pass-Layout-Engine waehrend des Strukturumbaus
- breite Feature-Erweiterung parallel zum Strukturumbau
- Verschieben aller vorhandenen Low-Level-Pakete nur aus Symmetriegruenden
