# Roadmap

Diese Roadmap beschreibt die naechsten sinnvollen technischen Schritte fuer die Library. Sie trennt bewusst zwischen bereits stabilisierten Grundlagen und offenen Baustellen.

## Aktueller Stand

Die aktuelle Basis ist weiter als eine reine Skizze:

- PDFs werden als indirekte Objekte aufgebaut und gerendert
- `xref`, `trailer` und `startxref` werden erzeugt
- Dokument-Metadaten sind vorhanden
- Seiten, Ressourcen und Content-Streams sind getrennt modelliert
- Text wird ueber registrierte Fonts gerendert
- Unicode-Fonts und `ToUnicode`-CMaps sind bereits angelegt
- fuer PDF `>= 1.4` wird ein Strukturmodell mit `StructTreeRoot`, `StructElem` und `ParentTree` erzeugt

Das bedeutet aber nicht, dass bereits alle hoeheren PDF-Ziele erreicht sind.

## Bereits erledigte Grundlagen

Diese Punkte aus der frueheren technischen Vorbereitung sind im aktuellen Code im Kern vorhanden:

- Text-String-Escaping
- zentrale Vergabe von Objekt-IDs
- Rendern ueber explizite indirekte Objekte
- `xref`- und Trailer-Erzeugung
- grundlegende Dokument-Metadaten
- erste Unicode-Unterstuetzung

## Prioritaeten

### 1. Rendering robuster machen

Der Renderpfad ist funktional, aber noch relativ direkt.

Naechste sinnvolle Verbesserungen:

- Content-Stream-Erzeugung weiter kapseln
- Byte-Laengen und Encodings konsequent pruefen
- Objektabhaengigkeiten noch klarer modellieren
- spaeter optionale Stream-Kompression vorbereiten

Warum das wichtig ist:

- Das Rendering ist der kritische Pfad des Projekts.
- Kleine Fehler in Laengen, Offsets oder Encodings machen PDFs schnell unbrauchbar.

### 2. Strukturmodell fachlich absichern

Das Projekt erzeugt bereits Tagged-PDF-nahe Strukturinformationen. Dieser Bereich ist technisch heikel und sollte nur kontrolliert erweitert werden.

Offene Punkte:

- erlaubte Struktur-Tags weiter definieren
- Verschachtelung und Eltern-Kind-Beziehungen genauer absichern
- Verhalten fuer komplexere Inhalte festlegen
- Struktur gegen echte Reader und Validatoren pruefen

Wichtig:

- Eine teilweise richtige Struktur ist riskanter als eine bewusst reduzierte Struktur.
- Neue Features in diesem Bereich sollten immer gegen reale PDF-Werkzeuge validiert werden.

### 3. API fuer weitere Inhalte erweitern

Aktuell ist Text der belastbare End-to-End-Fall. Weitere Inhaltstypen sollten erst dann hinzukommen, wenn sie in API, Ressourcenmodell und Rendering sauber verankert sind.

Naechste Kandidaten:

- Bilder
- Linien und einfache grafische Primitive
- Links oder Annotationen
- Tabellen oder strukturierte Layout-Helfer

Ziel:

- neue Features nicht nur als Klassen anlegen, sondern komplett bis zum finalen PDF-Pfad durchziehen

### 4. Ressourcenmodell ausbauen

Die Font-Nutzung pro Seite ist bereits sauber getrennt, aber das Ressourcenmodell wird mit mehr Features anspruchsvoller.

Offene Erweiterungen:

- XObjects fuer Bilder
- gemeinsam genutzte Ressourcen sauber modellieren
- Vererbung oder zentrale Verwaltung nur dann einfuehren, wenn sie echten Nutzen bringt

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
2. den naechsten echten Inhaltstyp vollstaendig einfuehren
3. Struktur- und Standardthemen danach gezielt validieren
