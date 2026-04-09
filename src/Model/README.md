# Model

Dieses Paket enthaelt den internen Dokumentzustand.

Aktuell hierhin verschoben:

- internes Dokumentaggregat
- Kernobjekte des Dokuments wie `Catalog`, `Pages`, `Info` und `EncryptDictionary`
- streambare Dokumentdaten wie `EmbeddedFileStream`, `IccProfileStream` und `XmpMetadata`
- Seitenmodell-Bausteine wie `Contents`, `Resources`, `ImageObject` und `ImageOptions`

Noch nicht alles ist bereits umgezogen:

- weitere reine Zustandsobjekte des PDFs liegen vorerst noch unter `Document`
- vorhandene Wrapper in `Document` halten die Migration bewusst rueckwaertskompatibel
