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
- die Pakete verweisen derzeit noch ueber Namespace-Bridges auf die bestehenden `Document`-Implementierungen
- die eigentlichen Implementierungen werden erst in weiteren, kleineren Refactorings schrittweise in diese Struktur verschoben
