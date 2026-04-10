# Page

Dieses Paket enthaelt den Seitenkern.

Die Struktur ist bewusst in kleine Teilpakete geschnitten:

- `Content` fuer seitennahe Inhalts- und Zeichenoperationen, den `Contents`-Stream, bildbezogene Einfuegeoptionen und komponentennahe Styles unter `Content/Style`
- `Link` fuer logische Linkziele zwischen Public API, Textlayout und Annotationen
- `Resources` fuer seitenbezogene Ressourcen wie Fonts und XObjects
- `Serialization` fuer den PDF-Seitenobjekt-Output

`Kalle\Pdf\Page` liegt in diesem Paket als zentrale Seitenklasse.
