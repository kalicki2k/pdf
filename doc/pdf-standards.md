# PDF Standards

Diese Seite fasst den aktuellen Stand fuer PDF/A und PDF/UA in der Library zusammen.

Ziel ist kein theoretischer Vollstaendigkeitsanspruch, sondern eine klare Inventur:

- welche Profile aktuell unterstuetzt werden
- welche Pfade automatisiert validiert sind
- welche Regeln fuer die oeffentliche API heute gelten
- wo die aktuellen Grenzen liegen

## PDF/A

Fuer PDF/A gibt es aktuell eine validierte Baseline ueber alle unterstuetzten Profile.

Unterstuetzte Profile:

- `PDF/A-1a`
- `PDF/A-1b`
- `PDF/A-2a`
- `PDF/A-2b`
- `PDF/A-2u`
- `PDF/A-3a`
- `PDF/A-3b`
- `PDF/A-3u`
- `PDF/A-4`
- `PDF/A-4e`
- `PDF/A-4f`

Wichtige Punkte:

- die Profilregeln liegen zentral in `Profile`
- PDF/A-spezifische Verbote und Guards greifen ueber den normalen Dokumentpfad
- PDF/A-Annotationen werden mit den noetigen Print-/Appearance-Regeln gerendert
- die Ausgabe wird automatisiert mit veraPDF geprueft

Validierung:

```bash
composer validate:pdfa -- <pdf-file>
composer test:pdfa-regression
```

Die Regression prueft reprasentative valide Fixtures fuer alle oben genannten PDF/A-Profile.

## PDF/UA-1

Fuer `PDF/UA-1` gibt es aktuell eine validierte Baseline fuer die heute freigegebenen API-Pfade.

Das Profil ist verfuegbar ueber:

```php
use Kalle\Pdf\Profile;

$profile = Profile::pdfUa1();
```

### Dokumentbasis

Aktuell abgesichert:

- Dokumenttitel ist Pflicht
- Dokumentsprache ist Pflicht
- Tagged PDF ist Pflicht
- PDF/UA-XMP (`pdfuaid:part=1`) wird geschrieben
- `ViewerPreferences /DisplayDocTitle true` wird gesetzt

### Tagged Content

Aktuell abgesichert:

- getaggte Ueberschriften und Absaetze
- Listen mit `L`, `LI`, `Lbl` und `LBody`
- Tabellen ueber die bestehende Strukturbaum-Anbindung
- Bilder als `Figure` mit Pflicht-`Alt`
- dekorativer Content in Headern/Footern sowie in High-Level-Komponenten als Artifact

### Links

Aktuell abgesichert:

- textgebundene externe und interne Links
- verschachtelte Links innerhalb bestehender Struktur-Tags
- standalone Rechteck-Links ueber `addLink(...)` und `addInternalLink(...)`
- High-Level-Linkpfade ueber `Badge`, `Panel` und `Callout`

Aktuelle Regel:

- standalone Link-Annotationen brauchen einen expliziten accessible name

### Formulare

Aktuell fuer `PDF/UA-1` angebunden:

- `TextField`
- `Checkbox`
- `PushButton`
- `RadioButton`
- `ComboBox`
- `ListBox`
- `SignatureField`

Aktuelle Regeln:

- Widgets bekommen `TU`, `StructParent` und einen `<Form>`-Eintrag im Strukturbaum
- Seiten mit Widgets schreiben `Tabs /S`
- der zugaengliche Name kommt aus `accessibleName` oder faellt kontrolliert auf vorhandene Feldinformationen zurueck
- sichtbare Feldlabels koennen explizit ueber `FormFieldLabel` an denselben Formular-Block gebunden werden

### Page Annotations

Aktuell fuer `PDF/UA-1` angebunden:

- `Text`
- `Popup`
- `FreeText`
- `Highlight`
- `Underline`
- `StrikeOut`
- `Squiggly`
- `Stamp`
- `Square`
- `Circle`
- `Ink`
- `Line`
- `PolyLine`
- `Polygon`
- `Caret`
- `FileAttachment`
- `Link`

Aktuelle Regeln:

- Nicht-Link-Annotationen werden strukturell unter `Annot` angebunden
- Link-Annotationen werden unter `Link` angebunden
- Annotationen bekommen `StructParent` und `OBJR`
- Alternativbeschreibungen werden fuer die aktuell unterstuetzten Pfade gesetzt

### Validierung

```bash
composer validate:pdfua -- <pdf-file>
composer test:pdfua-regression
composer test:pdfua-negative-regression
```

Die Regression prueft aktuell fuenfzehn reprasentative PDF/UA-1-Fixtures:

- minimaler Tagged-PDF-Basispfad
- Layout-/Decorative-Graphics-Pfad
- Link-Pfad
- Form-Pfad
- Widget-Appearance-Pfad fuer Text-, Choice-, Button- und Signature-Felder
- Widget-State-Pfad fuer Checkbox-, Radio- und Choice-Auswahlen
- Annotation-Batch
- mehrseitiger Tabellenpfad mit Caption, wiederholter Header-Zeile und Row-Headern
- mehrseitiger Tabellenpfad mit Caption, Row-Headern sowie `rowspan` und `colspan`
- mehrseitiger Tabellenpfad mit `rowspan`-/`colspan`-Gruppen, langen Monatsinhalten und wiederholten Headern
- mehrseitiger Tabellenpfad mit zwei Header-Zeilen und gruppierten Spalten-Headern
- mehrseitiger Tabellenpfad mit Header-Matrix, langen Zellinhalten und aggressiveren Seitenumbruechen
- kompakter Tabellenpfad mit schmalen Spalten, leeren Zellen und untrennbaren Tokens
- Mixed-Integrationspfad ueber mehrere Seiten
- tieferer Mixed-Integrationspfad mit internen Zielen, Listen, Formularen, Annotationen und Attachment ueber mehrere Seiten

Zusaetzlich gibt es aktuell neunzehn gezielte Negativ-Fixtures, die bei veraPDF fehlschlagen muessen:

- fehlende Dokumentsprache
- fehlender `ParentTree`
- fehlendes `MarkInfo /Marked true`
- fehlendes `DisplayDocTitle`
- ungueltiger oberster `Document`-Tag
- `Figure` ohne `Alt`
- standalone Link ohne Struktur-Anbindung
- standalone Link mit ungueltigem `Link`-Tag
- Formular-Widget ohne Struktur-Anbindung
- Widget-Seite ohne `Tabs /S`
- Seite ohne `StructParents`
- Form-Strukturelement ohne gueltigen `Form`-Tag
- Formular-Label-Container ohne gueltigen `Div`-Tag
- Nicht-Link-Annotation ohne Struktur-Anbindung
- Nicht-Link-Annotation mit ungueltigem `Annot`-Tag
- Tabelle mit ungueltigem `Table`-Tag
- Tabellenzeile mit ungueltigem `TR`-Tag
- Tabellenkopf ohne `Scope`
- Listen-Label ohne `Lbl`-Semantik

## Oeffentliche API

Fuer Profile gibt es benannte Helfer:

```php
Profile::pdf10();
Profile::pdf14();
Profile::pdf20();
Profile::pdfA2u();
Profile::pdfUa1();
```

Wenn die Zielversion dynamisch ist, bleibt `Profile::standard($version)` verfuegbar.

## Aktuelle Grenzen

Wichtig fuer die Einordnung:

- die Regressionssuiten sind bewusst reprasentativ und keine Vollmatrix aller Feature-Kombinationen
- `PDF/UA` ist aktuell auf `PDF/UA-1` ausgerichtet
- die Library validiert reale Ausgabedateien ueber veraPDF, nicht nur interne Objektstrukturen
- die Negativ-Suite arbeitet mit gezielten Byte-Mutationen auf sonst validen PDF/UA-Ausgaben, um konkrete Validator-Fehler stabil zu reproduzieren
- weitere PDF/UA-Regeln koennen noch folgen, wenn neue Features freigeschaltet oder strengere Fixtures aufgebaut werden

## Zugehoerige Dateien

Fuer den aktuellen Standard-Pfad sind vor allem diese Stellen relevant:

- `src/Profile.php`
- `src/Document/Document.php`
- `src/Document/Page.php`
- `src/Document/Annotation/PageAnnotationFactory.php`
- `src/Internal/TaggedPdf/StructElem.php`
- `bin/test-pdfa-regression.sh`
- `bin/test-pdfua-regression.sh`
