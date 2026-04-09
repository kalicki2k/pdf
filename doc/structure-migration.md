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

Diese Migrationsphase ist nach den ersten zwanzig Schritten in diesem Zustand:

- die dokumentweite Orchestrierung liegt jetzt unter `Application/Document`
- zentrale Kernobjekte des Dokument- und Seitenzustands liegen unter `Model/Document` und `Model/Page`
- `Feature/Action`, `Feature/Annotation`, `Feature/Form`, `Feature/OptionalContent`, `Feature/Outline`, `Feature/Table` und `Feature/Text` existieren bereits als Ziel-Namespaces
- die eigentlichen Implementierungen fuer diese Feature-Familien liegen jetzt in `Feature`
- die alten `Document`-Namespaces sind fuer diese Familien nur noch eine Rueckwaertskompatibilitaetsschicht

Das ist ein deutlich saubererer Zwischenstand, aber noch nicht das Ende der Migration.

Als naechster Schritt kann `Document` weiter von verbliebenen Zustands- und Glue-Klassen bereinigt werden,
ohne dass die Feature-Pakete erneut umgeschnitten werden muessen.

## Zielbild

Die Public API bleibt im Wurzel-Namespace:

- `Kalle\\Pdf\\Document`
- `Kalle\\Pdf\\Page`
- `Kalle\\Pdf\\Table`
- `Kalle\\Pdf\\TextFrame`

Darunter wird die interne Struktur schrittweise in drei Ebenen getrennt:

```text
src/
  Application/
    Document/

  Model/
    Document/
    Page/

  Feature/
    Action/
    Annotation/
    Form/
    OptionalContent/
    Outline/
    Table/
    Text/

  Element/
  Encryption/
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

### Application

Hier liegt Ablaufsteuerung fuer Dokumentaufbau und Ausgabe.

Beispiele:

- Vorbereitung vor dem finalen Schreiben
- Serialisierungsplanung
- Writer-Orchestrierung
- Dokumentweite Guards und Manager

Regeln:

- kennt Modell und Infrastruktur
- soll keine PDF-Typen oder Streamsyntax im Detail modellieren
- soll lesbar den Ablauf erklaeren

### Model

Hier liegt der interne Dokumentzustand.

Beispiele:

- internes Dokumentaggregat
- Seitenmodell
- Catalog, Pages, Info, Resources, Contents

Regeln:

- repraesentiert den Zustand des PDFs
- trifft keine uebergeordneten Ablaufentscheidungen
- soll moeglichst keine Public-API-Verantwortung tragen

### Feature

Hier liegen fachlich zusammenhaengende Dokumentfeatures.

Beispiele:

- Actions
- Annotationen
- Formulare
- Tabellen
- Textlayout
- Outlines
- Optional Content

Regeln:

- ein Feature enthaelt seine Modelle, Builder und Renderer moeglichst zusammen
- kein verstecktes Rueckgreifen auf unklare globale Dokumentzustandsobjekte
- Schnittstellen zum Dokumentmodell sollen expliziter werden

### Low-Level-Pakete

Diese bestehenden Pakete bleiben vorerst erhalten:

- `Render`
- `Object`
- `Types`
- `Structure`
- `Encryption`
- `Element`

Grund:

- sie sind bereits relativ kohärent
- das Hauptproblem liegt derzeit nicht dort, sondern im ueberladenen `Document`-Paket

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
9. Orchestrierung nach `Application` verschieben
10. Dokumentmodell nach `Model` verschieben
11. Text- und Tabellenfeature sauber trennen
12. weitere Feature-Pakete schrittweise aus `Document` herausloesen

## Nicht-Ziele

Diese Migration ist bewusst keine komplette fachliche Neuerfindung.

Nicht geplant:

- neues API-Design fuer Nutzer in einem grossen Schritt
- One-Pass-Layout-Engine waehrend des Strukturumbaus
- breite Feature-Erweiterung parallel zum Strukturumbau
- Verschieben aller vorhandenen Low-Level-Pakete nur aus Symmetriegruenden
