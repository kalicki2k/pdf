# PDF 1.4 Roadmap

Diese Datei sammelt die naechsten technischen Schritte fuer eine saubere und belastbare PDF-1.4-Implementierung.

## Aktueller Stand

- Die PDF ist syntaktisch lesbar.
- `qpdf --check` meldet keine Syntax- oder Stream-Fehler mehr.
- Die Datei ist nicht verschluesselt.
- Die Datei ist nicht linearized. Das ist kein Fehler, sondern nur keine Web-Optimierung.

## Wichtig

Eine syntaktisch gueltige PDF ist noch nicht automatisch:

- semantisch korrekt
- barrierefrei
- Tagged-PDF-konform
- PDF/A-konform
- PDF/UA-konform

## Prioritaeten

### 1. Text sicher escapen

PDF-Strings muessen korrekt escaped werden.

Mindestens diese Zeichen muessen im Text behandelt werden:

- `(`
- `)`
- `\\`

Sonst kann normaler Text den PDF-String oder sogar die Dokumentstruktur kaputt machen.

### 2. Objektverwaltung zentralisieren

Aktuell wird die PDF im Wesentlichen als String zusammengesetzt.

Robuster waere ein zentrales Objektmodell:

- alle indirekten Objekte registrieren
- Objekt-ID zentral vergeben
- Offset pro Objekt beim Rendern erfassen
- `xref` aus der Objektverwaltung erzeugen
- Trailer aus der Objektverwaltung erzeugen

Vorteil:

- weniger fehleranfaellig
- keine Offset-Ermittlung ueber Regex auf dem Gesamtdokument
- spaeter leichter erweiterbar

### 3. Strukturbaum nur korrekt oder gar nicht

Wenn Tagged PDF unterstuetzt werden soll, dann muss die Struktur fachlich korrekt aufgebaut werden.

Relevant sind insbesondere:

- `StructTreeRoot`
- `StructElem`
- `MCID`
- `StructParents`
- `ParentTree`
- korrekte Verknuepfung zwischen Inhalt und Struktur

Wichtig:

Eine halb implementierte Struktur ist riskanter als gar keine Struktur.

Wenn Accessibility oder Tagged PDF nicht sofort gebraucht werden, sollte die Struktur zunaechst weggelassen oder klar als unvollstaendig markiert werden.

### 4. Content-Streams robuster machen

Die Inhaltserzeugung sollte systematischer werden:

- Textoperationen sauber kapseln
- Koordinatenmodell dokumentieren
- String-Encoding klar behandeln
- spaeter optional Stream-Kompression unterstuetzen

### 5. Ressourcenmodell verbessern

Zu pruefen:

- Fonts global oder pro Seite
- Ressourcenvererbung ueber `Pages`
- spaeter Bilder, XObjects und weitere Ressourcen sauber einbinden

### 6. Trailer und Metadaten ausbauen

Fuer eine belastbare PDF-1.4-Basis sollten sauber modelliert werden:

- `Root`
- `Info`
- `Size`
- optional spaeter XMP-Metadaten
- optional spaeter Outlines, Links, Destinations, Annotations

### 7. Gegen mehrere Werkzeuge pruefen

`qpdf` ist nur ein Syntax-Check.

Spaeter sinnvoll:

- `qpdf`
- Acrobat Reader / Acrobat Preflight
- Poppler-Tools
- `veraPDF` fuer PDF/A
- PAC fuer PDF/UA und Accessibility

## Empfohlene Umsetzungsreihenfolge

### Variante A: minimale saubere PDF 1.4

1. Text-Escaping korrekt machen
2. Zentrale Objektverwaltung einfuehren
3. Ressourcenmodell stabilisieren
4. Content-Streams sauber strukturieren
5. Grundlegende Tests und Validatoren ergaenzen

Diese Variante ist die beste Basis, wenn zuerst eine stabile PDF-Erzeugung wichtig ist.

### Variante B: strukturierte PDF 1.4 / Tagged PDF

1. Alles aus Variante A abschliessen
2. Strukturmodell fachlich korrekt definieren
3. `MCID`, `ParentTree` und `StructParents` sauber implementieren
4. Struktur gegen echte Reader und Validatoren testen

Diese Variante ist deutlich komplexer und sollte erst begonnen werden, wenn die Grundarchitektur stabil ist.

## Konkrete Empfehlung fuer dieses Projekt

Naechster sinnvoller Schritt:

1. Text-Escaping fixen
2. Objektverwaltung zentralisieren
3. Strukturfeatures vorerst vereinfachen oder deaktivieren, falls sie noch nicht vollstaendig implementiert sind

Begruendung:

- Das reduziert das Risiko neuer kaputter PDFs.
- Es schafft eine stabile Grundlage fuer alle spaeteren Features.
- Tagged PDF ist deutlich anspruchsvoller als eine normale gueltige PDF 1.4.

## Offene Fragen

- Soll das Projekt zuerst nur gueltige Standard-PDFs erzeugen?
- Oder ist echtes Tagged PDF von Anfang an Pflicht?
- Ist spaeter PDF/A oder PDF/UA ein Ziel?

Diese Fragen bestimmen, wie die Architektur als Naechstes aufgebaut werden sollte.
