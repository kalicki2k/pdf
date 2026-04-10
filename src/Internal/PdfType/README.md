Dieses Paket enthaelt kleine PDF-Grundwerte fuer den internen Objekt- und Renderkern.

- `DictionaryType`, `ArrayType`, `NameType`, `StringType`, `BooleanType`, `RawType` und `ReferenceType` kapseln die niedrige PDF-Syntax.
- `Type` definiert den gemeinsamen Render-Vertrag fuer diese Werte.

Die Typen sind bewusst nah am technischen PDF-Kern gebuendelt und keine eigene Public-API-Schicht.
