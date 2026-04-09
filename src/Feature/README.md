# Feature

Dieses Paket enthaelt fachlich zusammenhaengende Dokumentfeatures.

Aktuell vorhanden:

- Actions
- Annotationen
- Formulare
- Optional Content
- Outlines
- Tabellen
- Text

Der aktuelle Stand dieser Phase:

- `Action`, `Annotation`, `Form`, `Outline`, `OptionalContent`, `Table` und `Text` sind als Ziel-Namespaces vorhanden
- die eigentlichen Implementierungen liegen jetzt in `Feature`
- die alten `Document`-Namespaces sind fuer diese Familien nur noch eine schmale Rueckwaertskompatibilitaetsschicht
