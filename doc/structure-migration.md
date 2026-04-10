# Structure Migration

Diese Notiz beschreibt die Zielstruktur fuer die naechste Refactoring-Phase.

Sie dokumentiert bewusst nicht den aktuellen Ist-Zustand, sondern das Zielbild,
an dem sich die schrittweise Migration orientiert.

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

- die dokumentweite Orchestrierung liegt jetzt unter `Internal/Document`
- Vorbereitung und Serialisierung sind dort in `Preparation` und `Serialization` geschnitten
- zentrale Kernobjekte des Dokument- und Seitenzustands liegen jetzt direkt unter `Internal/Document` und `Internal/Page`
- wiederverwendbare Bytequellen liegen jetzt komplett unter `Internal/Binary`
- der fruehere Root-Block `Layout` ist aufgeloest; Geometrie, Seitenmasse, Alignment und Overflow liegen jetzt unter `Internal/Layout`, TOC-Typen unter `Internal/Document/TableOfContents`
- oeffentliche Stil-Primitiven `Color` und `Opacity` liegen jetzt unter `Style`
- PDF-Action-Typen liegen jetzt unter `Internal/Action`
- PDF-Annotation-Stiltypen und das Marker-Interface liegen jetzt unter `Internal/Page/Annotation`
- `LinkTarget` liegt jetzt unter `Internal/Page/Link`, weil es die seitennahe Linkziel-Semantik fuer Text und Annotationen beschreibt
- der technische Font-Kern liegt jetzt unter `Internal/Font`
- gemeinsame Formularoptionen fuer Seitenwidgets liegen jetzt unter `Internal/Page/Form`
- konkrete Seitenannotationen und Formular-Widgets liegen unter `Internal/Page/Annotation` und `Internal/Page/Form`
- `AcroForm` und `RadioButtonField` liegen unter `Internal/Document/Form`
- oeffentliche Text- und Tabellen-Value-Types liegen unter `src/Text` und `src/Table`
- interne Text- und Tabellen-Layoutimplementierungen liegen unter `Internal/Layout/Text` und `Internal/Layout/Table`
- `OptionalContent` und `Outline` liegen jetzt unter `Internal/Document`, weil sie dokumentweiten Zustand und Navigation modellieren
- `StructureTag` liegt unter `Structure`, weil es Tagged-PDF-Semantik beschreibt
- die alte `src/Document`-Struktur ist als internes Codepaket entfernt

## Zielbild

Die Public API bleibt im Wurzel-Namespace:

- `Kalle\\Pdf\\Document`
- `Kalle\\Pdf\\Page`
- `Kalle\\Pdf\\Table`
- `Kalle\\Pdf\\TextFrame`

Darunter wird die interne Struktur schrittweise in diese Ebenen getrennt:

```text
src/
  Table/
  Text/

  Internal/
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
      Value/
    Object/
    Security/
      EncryptionAlgorithm.php
      EncryptionOptions.php
      EncryptionPermissions.php
    Page/
      Annotation/
      Form/
      Link/

  Render/
  Structure/
  Style/
  Types/
  Utilities/
```

## Paketregeln

### Public API

Die Root-Klassen bleiben kleine Fassaden.

Regeln:

- keine tiefe PDF-Logik
- keine Objektlisten aufbauen
- keine PDF-Syntax rendern
- nur Uebersetzung auf interne Use-Cases und Modelle

### Internal
Hier liegt der interne Dokumentkern samt Ablaufsteuerung fuer Aufbau und Ausgabe.

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

### Text und Table

Die oeffentlichen Eingabetypen fuer Text- und Tabellenaufbau liegen jetzt in eigenen Root-Paketen.

Beispiele:

- `TextOptions`, `ParagraphOptions`, `TextSegment`
- `TableCell`, `TableCaption`, `TableStyle`

Regeln:

- Public-API-Typen bleiben schlanke Value-Types ohne Seiten- oder Dokumentzustand
- Styles und Eingabemodelle sollen von den internen Layout-Renderern getrennt bleiben

### Style

Das Root-Paket `Style` enthaelt oeffentliche Stil-Primitiven und kleine Style-Value-Types.

Beispiele:

- `Color`, `Opacity`
- `BadgeStyle`, `CalloutStyle`, `PanelStyle`

Regeln:

- bleibt oeffentliche API, weil Farben, Opacity und Style-Objekte direkt in Signaturen und Optionsobjekten verwendet werden
- enthaelt nur deklarative Stilwerte, keine Render- oder Seitenlogik

### Internal/Layout

Hier liegen jetzt sowohl die kleinen Layout-Primitiven als auch die internen Layout-Implementierungen.

Beispiele:

- `Internal/Layout/Geometry` fuer `Position`, `Rect` und `Insets`
- `Internal/Layout/Page` fuer `PageSize` und `Units`
- `Internal/Layout/Value` fuer `HorizontalAlign`, `VerticalAlign`, `TextOverflow` und `BulletType`
- `Internal/Layout/Text` fuer Absatzlayout, Textboxen und Textframes
- `Internal/Layout/Table` fuer Tabellenzustand, Zeilengruppen, Pagination und Rendering

Regeln:

- kennt Seiten- und Dokumentkern und liefert auch die kleinen Layout-Primitiven fuer oeffentliche Signaturen
- kapselt Layout- und Rendering-Details fuer mehrseitige Text- und Tabellenfluesse
- verwendet die oeffentlichen Text- und Tabellen-Value-Types als Eingaben

### Internal/Document/TableOfContents

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

- `Internal/Action` fuer PDF-Action-Dictionaries von Buttons und Links
- `Internal/Page/Annotation/Style` fuer Annotation-Border- und Linienend-Stile
- `Internal/Page/Annotation/PageAnnotation` als gemeinsames Marker- und Related-Objects-Interface
- `Internal/Page/Annotation` fuer konkrete Seitenannotationen und ihre Koordination
- `Internal/Page/Form` fuer Widget-Erzeugung, Appearance-Streams und gemeinsame Formularoptionen
- `Internal/Page/Link` fuer logische Linkziele, die von Public API, Textlayout und Link-Annotationen geteilt werden
- `Internal/Document/Form` fuer dokumentweite AcroForm-Objekte

Regeln:

- PDF-Action-Typen liegen intern, auch wenn einzelne Public-API-Signaturen sie referenzieren
- annotation-spezifische PDF-Typen liegen intern nahe an der Seitenannotation-Schicht
- formularspezifische Optionen fuer Seitenwidgets liegen intern nahe an der Widget-Erzeugung
- konkrete Seitenobjekte und Builder liegen nahe am Seitenkern
- dokumentweite Formularzustandsobjekte liegen im Dokumentkern statt im Ablaufcode

### Internal/Font

Hier liegt der technische Font-Kern der PDF-Erzeugung.

Beispiele:

- `FontRegistry` und `FontPreset` fuer eingebaute Font-Presets
- `StandardFont`, `UnicodeFont`, `CidFont` und ihre PDF-Hilfsobjekte
- `OpenTypeFontParser`, `UnicodeGlyphMap` und `UnicodeFontWidthUpdater`

Regeln:

- keine eigene Public-API-Schicht, solange `Document::registerFont(...)` mit primitiven Werten auskommt
- Font-Presets, Parser, Width-Updates und konkrete PDF-Fontobjekte bleiben nah beieinander
- seiten- und formularspezifische Nutzung erfolgt ueber `Internal/Page` statt ueber Root-Fassaden

### Structure

Hier liegen PDF-Struktur- und Tagged-PDF-Typen.

Beispiele:

- `StructElem`
- `StructureTag`

Regeln:

- beschreibt Struktursemantik des PDFs
- ist kein Text- oder Layout-Unterpaket

### Low-Level-Pakete

Diese technischen Bausteine bleiben erhalten, sind aber jetzt konsequent intern geschnitten:

- `Internal/Object`
- `Render`
- `Types`
- `Structure`

Grund:

- sie sind relativ kohärent und bilden den PDF-Kern
- `Object` ist keine Public API, sondern technische Basis fuer indirekte PDF-Objekte
- `Security` enthaelt jetzt die Public-Konfiguration, `Internal/Encryption` den technischen Kryptokern

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
9. Orchestrierung nach `Internal/Document` verschieben
10. Dokumentmodell nach `Model` verschieben
11. Text- und Tabellenlayout in Public-Value-Types und `Internal/Layout` schneiden
12. historische Test- und Reststrukturen schrittweise nachziehen

## Nicht-Ziele

Diese Migration ist bewusst keine komplette fachliche Neuerfindung.

Nicht geplant:

- neues API-Design fuer Nutzer in einem grossen Schritt
- One-Pass-Layout-Engine waehrend des Strukturumbaus
- breite Feature-Erweiterung parallel zum Strukturumbau
- Verschieben aller vorhandenen Low-Level-Pakete nur aus Symmetriegruenden
