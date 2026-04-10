# Internal Page

Dieses Paket enthaelt den internen Seitenkern.

Die Struktur ist bewusst in kleine Teilpakete geschnitten:

- `Content` fuer seitennahe Inhalts- und Zeichenoperationen, den `Contents`-Stream und bildbezogene Einfuegeoptionen
- `Resources` fuer seitenbezogene Ressourcen wie Fonts und XObjects
- `Serialization` fuer den PDF-Seitenobjekt-Output

`Page` selbst bleibt im Root-Paket als zentrale interne Seitenklasse.
