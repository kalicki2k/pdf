# AGENTS.md

## Zweck des Projekts
Dieses Projekt erzeugt PDFs aus strukturierten Eingabedaten.

Die obersten Ziele sind:
- korrekte und stabile PDF-Erzeugung
- gut lesbarer, wartbarer Code
- einfache Architektur
- kleine, sichere Änderungen
- nachvollziehbares Verhalten bei Layout, Rendering und Datenmapping

## Entwicklungsprinzipien
Handle immer nach diesen Regeln, in dieser Reihenfolge:

1. **KISS**
    - Bevorzuge die einfachste Lösung, die das Problem sauber löst.
    - Keine unnötigen Abstraktionen, Layer oder Patterns.
    - Keine "cleveren" Kurzlösungen auf Kosten der Lesbarkeit.

2. **SOLID**
    - Jede Klasse soll eine klar erkennbare Verantwortung haben.
    - Bevorzuge Komposition statt tiefer Vererbung.
    - Abhängigkeiten sollen über Interfaces oder klar trennbare Services laufen.
    - Änderungen an einem Bereich sollen möglichst keine Seiteneffekte in anderen Bereichen erzeugen.

3. **DRY**
    - Doppelte Logik vermeiden.
    - Wiederholungen nur abstrahieren, wenn die gemeinsame Struktur wirklich stabil ist.
    - Keine zu frühe Abstraktion.

## Projektmentalität
Arbeite wie ein erfahrener PHP-Entwickler, der:
- Wert auf saubere Architektur legt
- robusten, testbaren Code bevorzugt
- auf Lesbarkeit mehr Wert legt als auf "Smartness"
- bestehende Konventionen respektiert
- keine unnötigen Refactorings durchführt
- Änderungen so klein wie möglich hält

## Allgemeine Arbeitsregeln
- Bevorzuge kleine, lokale Änderungen statt großer Umbauten.
- Ändere nur das, was für die Aufgabe notwendig ist.
- Erhalte bestehendes Verhalten, sofern nicht ausdrücklich etwas anderes verlangt wird.
- Führe keine stillen Breaking Changes ein.
- Wenn ein Refactoring sinnvoll ist, halte es klein und begründe es kurz.
- Gib am Ende kurz an:
    - welche Dateien geändert wurden
    - warum sie geändert wurden
    - welche Risiken oder offenen Punkte es gibt

## Architekturregeln
Nutze diese Struktur als Leitlinie:

- **Domain / Model**
    - enthält die fachlichen Datenstrukturen
    - keine PDF-spezifischen Seiteneffekte

- **Application / Use Case**
    - orchestriert den Ablauf der PDF-Erstellung
    - validiert Eingaben
    - ruft Mapper, Builder und Renderer in klarer Reihenfolge auf

- **Mapping / Transformation**
    - wandelt Eingabedaten in renderbare View- oder Dokumentmodelle um
    - keine versteckte Business-Logik im Renderer

- **PDF Rendering**
    - zuständig für Layout, Styles, Seiteneinstellungen, Header/Footer, Tabellen, Pagination
    - sollte nicht direkt Fachlogik enthalten

- **Infrastructure**
    - Dateisystem, externe PDF-Library, Storage, Streams, Framework-Anbindung

## PDF-spezifische Regeln
- Trenne **Datenaufbereitung** klar von **PDF-Rendering**.
- Halte PDF-Library-spezifischen Code isoliert.
- Direkte Zugriffe auf die konkrete PDF-Library möglichst in Adapter oder Renderer kapseln.
- Keine fachliche Logik in Template-Dateien oder Render-Closures verstecken.
- Layout-Regeln sollen nachvollziehbar und zentral auffindbar sein.
- Magic Numbers für Abstände, Breiten, Schriftgrößen oder Seitenränder vermeiden.
- Wiederverwendbare Layout-Konstanten oder Value Objects bevorzugen.
- Rendering soll möglichst deterministisch sein.
- Fehler bei fehlenden Daten sollen sauber behandelt werden, nicht stillschweigend ignoriert.
- PDF-Erzeugung soll keine unerwarteten Seiteneffekte haben.

## Klassen- und Methodendesign
- Klassen klein halten.
- Methoden kurz und eindeutig benennen.
- Eine Methode soll möglichst genau einen klaren Zweck haben.
- Lange Methoden in sinnvolle private Methoden zerlegen.
- Primitive Obsession vermeiden, wenn kleine Value Objects das Problem klarer machen.
- Konstruktor-Injection bevorzugen.
- Statische Hilfsklassen nur verwenden, wenn sie wirklich zustandslos und banal sind.
- Keine God Classes bauen.

## Interfaces und Abhängigkeiten
- Interfaces nur dort einführen, wo Austauschbarkeit oder Testbarkeit davon wirklich profitieren.
- Keine Interfaces "auf Vorrat".
- Abhängigkeiten explizit machen.
- Keine versteckten globalen Zustände.
- Keine schwer nachvollziehbaren Singleton-Konstruktionen.

## Fehlerbehandlung
- Fehler nicht verschlucken.
- Exceptions nur dort einsetzen, wo sie fachlich oder technisch sinnvoll sind.
- Fehlermeldungen klar und konkret formulieren.
- Ungültige Eingaben früh validieren.
- "Best effort"-Rendering nur dann, wenn das Projekt das explizit vorsieht.

## Konfiguration
- Konfiguration von Layout, Seitenformat, Margins, Fonts und Output-Verhalten nicht hart im Code verteilen.
- Konfigurationswerte an einer klaren Stelle bündeln.
- Keine Secrets oder Umgebungswerte hart codieren.

## Lesbarkeit und Stil
- Schreibe klaren, nüchternen PHP-Code.
- Bevorzuge sprechende Namen gegenüber Kommentaren.
- Kommentare nur dort, wo die Absicht sonst nicht klar wäre.
- Keine unnötig verschachtelten Bedingungen.
- Frühzeitige Returns bevorzugen, wenn sie den Code vereinfachen.
- Keine übermäßig generischen Utility-Klassen anlegen.

## Tests
Bei Änderungen an Logik oder Rendering möglichst absichern durch:

- Unit-Tests für Mapper, Formatter, Value Objects und Use Cases
- Integrationstests für die PDF-Erzeugung
- falls vorhanden: Snapshot-/Golden-Master-Tests für stabile PDF-Ausgabe
- Regressionen besonders bei:
    - Seitenumbrüchen
    - Tabellenlayout
    - Header/Footer
    - Zeichensatz / Sonderzeichen / Umlaute
    - mehrseitigen Dokumenten
    - optionalen Feldern
    - leeren Datenmengen

Wenn Tests fehlen:
- füge gezielt kleine Tests für neues Verhalten hinzu
- vermeide riesige Testgerüste nur für minimale Änderungen

## Qualitätsregeln
Nach PHP-Änderungen nach Möglichkeit immer prüfen:

1. `composer test`
   2.`composer phpstan`
   3.`composer cs:check`

Wenn im Projekt andere Befehle etabliert sind, nutze die bestehenden Projektstandards.

## PHPStan-Regeln
- Neue Änderungen dürfen keine zusätzlichen PHPStan-Fehler erzeugen.
- Bevorzuge präzise Typen statt `mixed`.
- Native Typen vor PHPDoc, wenn möglich.
- PHPDoc nur ergänzen, wenn es echten Mehrwert bringt.
- Warnungen nicht ohne gute Begründung unterdrücken.
- Nullability explizit und bewusst behandeln.

## Refactoring-Regeln
Refaktoriere nur, wenn mindestens einer dieser Punkte erfüllt ist:
- die aktuelle Struktur verhindert die saubere Lösung der Aufgabe
- du entfernst echte Duplikation
- du verbesserst Testbarkeit deutlich
- du reduzierst Komplexität spürbar

Nicht refaktorieren nur aus Stilgründen, wenn das Risiko höher ist als der Nutzen.

## Was vermieden werden soll
- überflüssige Abstraktion
- unnötige Design Patterns
- implizite Seiteneffekte
- Logik in Views/Templates
- Copy-Paste-Code
- riesige Services oder Builder
- enge Kopplung an eine konkrete PDF-Library
- nicht getestete Änderungen an zentralem Rendering-Verhalten
- große Umbauten ohne ausdrücklichen Auftrag

## Bevorzugte Lösungsrichtung
Wenn mehrere Lösungen möglich sind, bevorzuge diese Reihenfolge:
1. kleine und direkte Verbesserung im bestehenden Design
2. Extraktion einer klar benannten Hilfsmethode oder kleinen Klasse
3. Einführung eines dedizierten Services oder Value Objects
4. größere strukturelle Änderung nur wenn wirklich notwendig

## Erwartete Antwort bei Codeänderungen
Bei jeder relevanten Änderung kurz angeben:
- was geändert wurde
- warum die Änderung notwendig war
- wie sie SOLID, DRY oder KISS unterstützt
- welche Tests oder Checks ausgeführt werden sollten

## Kurzfassung für Entscheidungen
Bei Unsicherheit gilt:
- **einfacher vor generischer**
- **klarer vor cleverer**
- **kleiner vor größer**
- **testbarer vor impliziter**
- **stabiler vor theoretisch flexibler**