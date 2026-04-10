# Internal Font

Dieses Paket enthaelt den technischen Font-Kern der PDF-Erzeugung.

- Font-Presets und ihre Default-Definitionen
- konkrete PDF-Fontobjekte wie `StandardFont`, `UnicodeFont` und `CidFont`
- Parser- und Mapping-Helfer fuer eingebettete Fonts
- Width- und Encoding-Unterstuetzung fuer Text- und Formular-Rendering

Die oeffentliche API bleibt bewusst bei `Document::registerFont(...)` mit primitiven Werten.
Der eigentliche Font-Aufbau und die PDF-Objekte liegen intern nahe an Dokument-, Seiten- und Formularlogik.
