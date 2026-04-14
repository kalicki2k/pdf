# Profile, Standardscope und Grenzen

## Grundprinzip

Der aktuelle Projektstand arbeitet mit expliziten Profilregeln statt mit implizitem "wir versuchen es mal". Das ist im Code vor allem in `Profile` sowie in den zugehoerigen Validierungs- und Policy-Tests sichtbar.

Die wichtigsten Profilfamilien sind:

- Standard-PDF ueber Versionen `1.0` bis `2.0`
- PDF/A-1, PDF/A-2, PDF/A-3 und PDF/A-4 mit einzelnen Konformanzstufen
- PDF/UA-1 und PDF/UA-2

## Standard-PDF

Fuer Standard-PDF sind mehrere Versionen vorgesehen, von `Profile::pdf10()` bis `Profile::pdf20()`. Featuregrenzen ergeben sich hier vor allem aus technischen Implementierungen, nicht aus Archivierungs- oder Accessibility-Regeln.

Beispiele:

- Verschluesselung ist nur in geeigneten Standardprofilen erlaubt.
- Signaturen laufen ueber einen separaten inkrementellen Update-Pfad.

## PDF/A: aktueller Scope

Der im Code dokumentierte und durch Tests abgesicherte Scope ist bewusst konservativ.

### PDF/A-1

- `PDF/A-1b`
  stabiler Positivpfad mit eingebetteten Fonts, XMP/Info-Metadaten, OutputIntent, Appearance-Streams und geprueften Farbpfaden
- `PDF/A-1a`
  enger Tagged-Pfad; im Formularbereich sind aktuell nur `TextField`, `ComboBoxField` und `ListBoxField` freigegeben

Wichtige Eigenschaften aus `ProfileTest`:

- OutputIntent ist erforderlich
- Info-Dictionary wird geschrieben
- Transparenz ist nicht erlaubt
- Tagged PDF ist fuer `1a` erforderlich, fuer `1b` nicht

### PDF/A-2 und PDF/A-3

Die Tests in `ProfileTest` und `PdfAProfileSupportTest` zeigen eine explizit freigegebene Teilmenge:

- Annotation-Subset: aktuell insbesondere `Link`, `Text`, `Highlight`, `FreeText`
- Formular-Subset: `TextField`, `Checkbox`, `RadioButtonGroup`, `ComboBox`, `ListBox`
- Push Buttons und Signaturfelder bleiben in diesen Profilen im aktuellen Scope gesperrt
- `PDF/A-2a` und `PDF/A-3a` verlangen Tagged PDF und getaggte Form-/Annotationspfade
- `PDF/A-2u` und `PDF/A-3u` verlangen extrahierbare eingebettete Unicode-Fonts
- `PDF/A-3*` deckt dokumentweite Embedded Files bzw. Associated Files ab

### PDF/A-4-Familie

Der aktuelle PDF/A-4-Scope ist im Code deutlich enger beschrieben als das gesamte Normspektrum:

- `PDF/A-4`
  enger PDF-2.0-Basispfad mit Metadaten, `pdfaid:rev`, ohne Info-Dictionary, ohne OutputIntent, mit kleinem Annotation- und AcroForm-Subset
- `PDF/A-4e`
  erweitert den Basispfad um einen kleinen Optional-Content-, RichMedia- und 3D-Scope
- `PDF/A-4f`
  erweitert den Basispfad um dokumentweite Associated Files

Explizit aus den Tests ableitbar:

- PDF/A-4 schreibt keine Info-Dictionary-Eintraege
- PDF/A-4 nutzt keinen OutputIntent
- PDF/A-4 schreibt PDF/A-Revision-Metadaten
- PDF/A-4e darf den aktuellen Optional-Content-Pfad nutzen
- PDF/A-4f erlaubt dokumentweite Associated Files

## PDF/UA

`Profile::pdfUa1()` und `Profile::pdfUa2()` sind vorhanden. Aus `ProfileTest`, Tagged-PDF-Tests und Annotation/Form-Tests laesst sich ableiten:

- Tagged PDF ist fuer PDF/UA kein optionales Extra, sondern Kernbestandteil
- extrahierbare eingebettete Unicode-Fonts sind erforderlich
- Links, Formulare und mehrere Annotationstypen werden im aktuellen Scope mit logischer Struktur und Alternativtexten in die Struktur aufgenommen

Wichtig: Die Freigabe bleibt auch hier explizit. Nicht jede theoretisch denkbare Annotation oder jeder Widget-Typ ist automatisch PDF/UA-tauglich.

## Tagged PDF als Voraussetzung

Mehrere Profile verlangen im aktuellen Projektstand denselben strukturierten Kern:

- Strukturbaum (`StructTreeRoot`)
- ParentTree
- semantische Container und Blattrollen
- Alternativtexte fuer relevante nicht-textuelle Inhalte
- strukturierte Annotationen und Formfelder in passenden Profilen

Fehlt diese Struktur, bricht der Build mit Validierungsfehlern ab. Das ist nicht nur Konvention, sondern im Builder- und Objektgraph-Validator verankert.

## Farben, Metadaten und OutputIntent

Der aktuelle Projektstand nutzt:

- ICC-Profile in `assets/color/icc/`
- `PdfAOutputIntent`
- XMP-Metadaten ueber `Document/Metadata`

Der Scope ist profilabhaengig:

- fuer PDF/A-1 bis PDF/A-3 ist OutputIntent zentral
- fuer PDF/A-4 schreibt der aktuelle Pfad stattdessen PDF-2.0-konforme Metadaten ohne Info-Dictionary und ohne OutputIntent

## Verschluesselung

Die Engine unterstuetzt im Standard-PDF-Pfad:

- RC4-128
- AES-128
- AES-256

Die Rechte werden ueber `Permissions` modelliert. PDF/A-Profile unterstuetzen Verschluesselung im aktuellen Scope nicht.

## Signaturen

Signaturen sind als eigener Pfad ueber `DocumentSigner` implementiert:

- benoetigt ein vorhandenes `SignatureField`
- rendert zuerst ein unsigniertes PDF
- fuegt die Signatur als inkrementelles Update an
- erzeugt derzeit einen `adbe.pkcs7.detached`-Pfad

Wichtige Grenze aus dem Code:

- verschluesselte Dokumente koennen aktuell nicht kryptographisch signiert werden

## Bekannte bewusste Grenzen

Aus Code und Tests eindeutig ableitbar:

- PDF/A-Unterstuetzung ist absichtlich eine freigegebene Teilmenge, nicht die volle Normabdeckung
- Popups, seitengebundene Dateianhaenge, Push Buttons, Signaturfelder und weitere Features sind in mehreren PDF/A-Profilen bewusst blockiert
- Tagged PDF wird nicht automatisch "geraten"; fehlende Semantik fuehrt in passenden Profilen zu Fehlern
- die aktuelle Text- und Font-Pipeline zielt auf den getesteten Scope, nicht auf generische Vollabdeckung aller Schriftsysteme
- RichMedia-, 3D- und Optional-Content-Unterstuetzung existiert aktuell nur im engen PDF/A-4e-Pfad

## Wo der aktuelle Scope abgesichert ist

Die wichtigsten Quellen im Repository sind:

- `tests/Document/ProfileTest.php`
- `tests/Document/PdfAProfileSupportTest.php`
- `tests/Document/PdfA*PolicyMatrixTest.php`
- `tests/Document/PdfA*ObjectGraphValidatorTest.php`
- `tests/Document/PdfA1a*ValidatorTest.php`
- die PDF/A-Regressionsskripte in `bin/`

Wer Profilregeln oder Freigaben erweitert, sollte diese Stellen immer als primaere Aenderungspunkte betrachten.
