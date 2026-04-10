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
- PDF-Action-Typen liegen jetzt unter `Internal/Action`
- oeffentliche Annotation- und Formular-Value-Types liegen unter `src/Annotation` und `src/Form`
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
  Annotation/
  Form/
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

  Font/
  Graphics/
  Layout/
  Object/
  Render/
  Structure/
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
- `Annotation` fuer oeffentliche Annotation-Value-Types und Rollen
- `Form` fuer oeffentliche Formularoptionen
- `Internal/Page/Annotation` fuer konkrete Seitenannotationen und ihre Koordination
- `Internal/Page/Form` fuer Widget-Erzeugung und Appearance-Streams
- `Model/Document/Form` fuer dokumentweite AcroForm-Objekte

Regeln:

- PDF-Action-Typen liegen intern, auch wenn einzelne Public-API-Signaturen sie referenzieren
- oeffentliche Annotation- und Formular-Value-Types bleiben ausserhalb von `Internal`
- konkrete Seitenobjekte und Builder liegen nahe am Seitenkern
- dokumentweite Formularzustandsobjekte liegen im Modell statt im Ablaufcode

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
