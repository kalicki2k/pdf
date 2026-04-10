# Feature

Dieses Paket enthaelt fachlich zusammenhaengende Dokumentfeatures.

Aktuell vorhanden:

- Tabellen
- Text

Der aktuelle Stand dieser Phase:

- `Table` und `Text` liegen weiterhin in `Feature`
- `Action` ist jetzt ein eigenes Public-API-Paket unter `src/Action`
- oeffentliche Annotation- und Formular-Value-Types liegen unter `src/Annotation` und `src/Form`
- konkrete Seitenannotationen und Formular-Widgets liegen unter `Internal/Page`
- `AcroForm` und `RadioButtonField` liegen unter `Model/Document/Form`
- `OptionalContent` und `Outline` gehoeren jetzt zum internen Dokumentkern unter `Internal/Document`
- `StructureTag` liegt unter `Structure`, weil es Tagged-PDF-Semantik und kein Textdetail modelliert
