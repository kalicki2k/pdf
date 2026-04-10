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
- zentrale Kernobjekte des Dokument- und Seitenzustands liegen unter `Model/Document` und `Model/Page`
- oeffentliche Geometrie-Primitiven `Position`, `Rect` und `Insets` liegen jetzt unter `Layout`
- oeffentliche Stil-Primitiven `Color` und `Opacity` liegen jetzt unter `Style`
- PDF-Action-Typen liegen jetzt unter `Internal/Action`
- PDF-Annotation-Stiltypen und das Marker-Interface liegen jetzt unter `Internal/Page/Annotation`
- der technische Font-Kern liegt jetzt unter `Internal/Font`
- gemeinsame Formularoptionen fuer Seitenwidgets liegen jetzt unter `Internal/Page/Form`
- konkrete Seitenannotationen und Formular-Widgets liegen unter `Internal/Page/Annotation` und `Internal/Page/Form`
- `AcroForm` und `RadioButtonField` liegen unter `Model/Document/Form`
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
    Document/
      Form/
      Preparation/
      Serialization/
    Encryption/
      Crypto/
      Object/
      Profile/
      Standard/
      Stream/
    Font/
    Layout/
      Table/
      Text/
    Security/
      EncryptionAlgorithm.php
      EncryptionOptions.php
      EncryptionPermissions.php
    Page/
      Annotation/
      Form/

  Model/
    Document/
      Form/
    Page/

  Layout/
  Object/
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

### Model

Hier liegt der interne Dokumentzustand.

Beispiele:

- internes Dokumentaggregat
- Seitenmodell
- Catalog, Pages, Info, Resources, Contents
- dokumentweite Formularobjekte wie `AcroForm`

Regeln:

- repraesentiert den Zustand des PDFs
- trifft keine uebergeordneten Ablaufentscheidungen
- soll moeglichst keine Public-API-Verantwortung tragen

### Text und Table

Die oeffentlichen Eingabetypen fuer Text- und Tabellenaufbau liegen jetzt in eigenen Root-Paketen.

Beispiele:

- `TextOptions`, `ParagraphOptions`, `TextSegment`
- `TableCell`, `TableCaption`, `TableStyle`

Regeln:

- Public-API-Typen bleiben schlanke Value-Types ohne Seiten- oder Dokumentzustand
- Styles und Eingabemodelle sollen von den internen Layout-Renderern getrennt bleiben

### Layout

Das Root-Paket `Layout` enthaelt die oeffentlichen Layout- und Geometrie-Primitiven.

Beispiele:

- `PageSize`, `Units`, `HorizontalAlign`, `VerticalAlign`
- `Position`, `Rect`, `Insets`

Regeln:

- bleibt oeffentliche API, weil diese Typen direkt in Signaturen von `Document`, `Page`, `TextFrame` und `Table` verwendet werden
- enthaelt nur kleine Value-Types und Layout-Helfer, keine Seiten- oder Dokumentzustandslogik

### Style

Das Root-Paket `Style` enthaelt oeffentliche Stil-Primitiven und kleine Style-Value-Types.

Beispiele:

- `Color`, `Opacity`
- `BadgeStyle`, `CalloutStyle`, `PanelStyle`

Regeln:

- bleibt oeffentliche API, weil Farben, Opacity und Style-Objekte direkt in Signaturen und Optionsobjekten verwendet werden
- enthaelt nur deklarative Stilwerte, keine Render- oder Seitenlogik

### Internal/Layout

Hier liegen die internen Layout-Implementierungen fuer laengeren Textfluss und Tabellen.

Beispiele:

- `Internal/Layout/Text` fuer Absatzlayout, Textboxen und Textframes
- `Internal/Layout/Table` fuer Tabellenzustand, Zeilengruppen, Pagination und Rendering

Regeln:

- kennt Seiten- und Dokumentkern, aber keine oeffentlichen Fassaden
- kapselt Layout- und Rendering-Details fuer mehrseitige Text- und Tabellenfluesse
- verwendet die oeffentlichen Text- und Tabellen-Value-Types als Eingaben

### Action, Annotation und Form

Diese Bereiche sind jetzt zwischen internem PDF-Mechanismus, Public API, Seitenimplementierung und Dokumentmodell geschnitten.

Beispiele:

- `Internal/Action` fuer PDF-Action-Dictionaries von Buttons und Links
- `Internal/Page/Annotation/Style` fuer Annotation-Border- und Linienend-Stile
- `Internal/Page/Annotation/PageAnnotation` als gemeinsames Marker- und Related-Objects-Interface
- `Internal/Page/Annotation` fuer konkrete Seitenannotationen und ihre Koordination
- `Internal/Page/Form` fuer Widget-Erzeugung, Appearance-Streams und gemeinsame Formularoptionen
- `Model/Document/Form` fuer dokumentweite AcroForm-Objekte

Regeln:

- PDF-Action-Typen liegen intern, auch wenn einzelne Public-API-Signaturen sie referenzieren
- annotation-spezifische PDF-Typen liegen intern nahe an der Seitenannotation-Schicht
- formularspezifische Optionen fuer Seitenwidgets liegen intern nahe an der Widget-Erzeugung
- konkrete Seitenobjekte und Builder liegen nahe am Seitenkern
- dokumentweite Formularzustandsobjekte liegen im Modell statt im Ablaufcode

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

Diese bestehenden Pakete bleiben vorerst erhalten:

- `Render`
- `Object`
- `Types`
- `Structure`

Grund:

- sie sind bereits relativ kohärent
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
