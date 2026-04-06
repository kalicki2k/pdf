# Roadmap

Diese Roadmap beschreibt die naechsten sinnvollen Schritte fuer die Library.

Sie ist bewusst priorisiert:

- zuerst Stabilitaet und Vorhersehbarkeit
- dann Ausbau der haeufigen Dokumentfaelle
- erst spaeter tiefere PDF-Sonderfaelle

## Zielbild

Die Library soll ein robuster allgemeiner PDF-Generator werden mit:

- stabiler PDF-Erzeugung
- klarer, kleiner API
- gut nachvollziehbarem Layout-Verhalten
- solider Font- und Text-Unterstuetzung
- brauchbaren High-Level-Bausteinen fuer echte Dokumente

## Aktueller Stand

Die aktuelle Basis ist bereits deutlich ueber einem Prototyp:

- Seiten, Ressourcen, Content-Streams und indirekte Objekte sind vorhanden
- Text, Paragraphen, `TextBox` und `TextFrame` sind vorhanden
- Tabellen, Bilder, Shapes, Links und Annotationen sind vorhanden
- Form-Felder sind vorhanden
- Standardfonts und eingebettete Fonts sind vorhanden
- Geometrie ueber `Position`, `Rect` und `Insets` ist vorhanden
- Text-Optionen, Paragraph-Optionen und `TextSegment` sind vorhanden
- Verschluesselung, Outlines, benannte Ziele und Inhaltsverzeichnisse sind vorhanden

Die Kernfrage ist deshalb nicht mehr: "Koennen wir PDFs erzeugen?"

Die Kernfrage ist jetzt: "Welche Bereiche muessen wir haerten und sauber ausbauen, damit die Library im Alltag verlaesslich ist?"

## Must Have

Diese Punkte bringen den groessten realen Nutzen und sollten zuerst kommen.

### 1. Tabellen haerten

Tabellen sind fuer echte Dokumente einer der kritischsten Bereiche.

Wichtige Punkte:

- mehr Regressionstests fuer `rowspan`, `colspan` und Seitenumbrueche
- wiederholte Header in mehr Randfaellen pruefen
- Zell-Overflow und vertikale Ausrichtung weiter absichern
- Split-Verhalten bei langen Tabellenzeilen weiter stabilisieren

Warum:

- Berichte, Rechnungen und Listen stehen und fallen mit Tabellen
- kleine Layoutfehler wirken dort sofort unprofessionell

### 2. Header, Footer und Seitentemplates

Wiederkehrende Seitenbereiche sollten als klare API vorhanden sein.

Wichtige Punkte:

- wiederverwendbare Header/Footer-Definitionen
- feste Content-Areas statt verteilter Magic Numbers
- klare Regeln fuer Seitenzahlen und Dokumentbereiche

Warum:

- fast jedes reale Dokument braucht diese Bausteine
- aktuell ist das moeglich, aber noch nicht stark genug als Layout-Modell

### 3. Mehr Rendering-Regressionstests

Der Renderer ist der kritische Kern.

Wichtige Punkte:

- mehr Tests fuer Text-Layout
- mehr Tests fuer Tabellen
- mehr Tests fuer Form-Felder
- mehr Tests fuer Seitenumbrueche
- gezielte Golden-Master- oder PDF-Assertions fuer stabile Ausgabe

Warum:

- Rendering-Regressionen sind teuer
- die Library wird mit wachsender API nur durch gute Regressionstests wirklich stabil

### 4. Doku und echte Beispiel-Dokumente

Die API ist inzwischen deutlich staerker als die Beispielsammlung.

Wichtige Punkte:

- Rechnung als sauberes Referenzbeispiel pflegen
- Brief / Anschreiben als Beispiel
- Formular als Beispiel
- kleiner Bericht oder tabellenlastiges Dokument als Beispiel
- Best-Practice-Doku fuer `Position`, `Rect`, `Insets`, `TextBox` und `TextFrame`

Warum:

- gute Beispiele sind fuer Nutzer fast so wichtig wie die API selbst
- sie zeigen sofort, ob das API-Design wirklich tragfaehig ist

## Should Have

Diese Punkte sind wichtig, aber nach den Kernbereichen.

### 5. Rich Text vorsichtig ausbauen

Der Rich-Text-Pfad ist schon brauchbar, aber noch bewusst klein.

Moegliche naechste Schritte:

- segment-spezifischer `fontName`
- optionale Hintergrundfarbe pro Segment
- weitere kleine `TextSegment`-Factories nur bei echtem Bedarf

Nicht vorschnell:

- `fontSize` pro Segment
- zu fruehe HTML- oder Markdown-Importpfade

Warum:

- hier steigt die Layout-Komplexitaet schnell
- der Ausbau sollte nur kontrolliert erfolgen

### 6. Bilder und Layout-Bloecke verbessern

Moegliche Erweiterungen:

- `contain`, `cover` oder aehnliche Fit-Strategien fuer Bilder
- klarere Box-/Panel-Bausteine fuer wiederkehrende Layouts
- einfache Key-Value- oder Info-Bloecke fuer typische Dokumente

Warum:

- das bringt fuer echte PDFs sofort Nutzwert
- ohne die Core-API unnoetig aufzublaehen

### 7. Formular-API weiter absichern

Formulare sind schon vorhanden, aber noch nicht voll ausgereizt.

Moegliche naechste Schritte:

- bessere Appearance-Strategien fuer Text- und Choice-Felder
- mehr Regressionstests fuer Widget-Rendering
- klarere Doku fuer Formularnutzung

## Nice To Have

Diese Punkte sind sinnvoll, aber nicht akut.

### 8. Dokumentstandards und Validatoren staerker einbinden

Moegliche Themen:

- PDF/A
- PDF/UA
- Accessibility-Checks
- XMP-Metadaten
- Viewer Preferences

Warum spaeter:

- das ist wertvoll, aber erst sinnvoll auf stabiler Kernbasis

### 9. Tiefere Typografie und Font-Themen

Moegliche Themen:

- weiter praezisierte Font-Metriken
- Fallback-Font-Strategien
- erweiterte Unicode-/Skript-Themen

Warum spaeter:

- hoher Aufwand
- nur sinnvoll, wenn reale Dokumente das wirklich brauchen

## Empfohlene Reihenfolge

1. Tabellen-Haertung
2. Header/Footer/Templates
3. Mehr Rendering-Regressionstests
4. Doku und echte Beispiel-Dokumente
5. Rich Text gezielt erweitern
6. Bilder und Layout-Bloecke verbessern
7. Formular-Feinschliff
8. Standards und Validatoren

## Entscheidungsregel

Wenn mehrere moegliche naechste Schritte offen sind, gilt:

- Stabilitaet vor Feature-Breite
- haeufige Dokumentfaelle vor Spezialfaellen
- kleine nachvollziehbare Schritte vor grossem Umbau
