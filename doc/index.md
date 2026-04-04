# Documentation

Diese Dokumentation beschreibt den aktuellen Stand der Library und die naechsten technischen Schritte.

## Inhalte

- [Getting Started](getting-started.md)
- [Page Sizes](page-sizes.md)
- [Architecture](architecture.md)
- [Roadmap](roadmap.md)

## Zweck

Die Doku ist bewusst kompakt gehalten und wird parallel zum Code erweitert. Sie soll drei Dinge abdecken:

- schneller Einstieg in die Nutzung
- klares Bild der aktuellen Architektur
- sichtbare technische Prioritaeten fuer die Weiterentwicklung

## Generierung

Die statische Doku-Seite wird per Composer nach `var/docs` gebaut:

```bash
composer docs:build
```
