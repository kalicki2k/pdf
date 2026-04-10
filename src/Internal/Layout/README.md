# Internal Layout

Dieses Paket enthaelt die internen Layout-Implementierungen fuer Text- und Tabellenfluss.

- `Text` enthaelt Absatz- und Textframe-Layout sowie die zugehoerigen Renderer.
- `Table` enthaelt Tabellenzustand, Vorbereitung, Pagination und Rendering.

Oeffentliche Value-Types und API-Eingaben liegen bewusst ausserhalb davon:

- `src/Text` fuer Textoptionen und Segmente
- `src/Table` fuer Tabellen-Value-Types und Styles
