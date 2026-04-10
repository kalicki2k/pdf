# Getting Started

Diese Library erzeugt PDFs direkt aus PHP. Der Einstieg ist bewusst klein gehalten: Dokument anlegen, Fonts registrieren, Seite erzeugen, Inhalt schreiben, Datei speichern.

## Voraussetzungen

- PHP `^8.4`
- `ext-mbstring`
- Composer

## Installation

In einem Projekt installieren:

```bash
composer require kalle/pdf
```

## Erstes Dokument

```php
<?php

declare(strict_types=1);

use Kalle\Pdf\Document;
use Kalle\Pdf\Layout\Geometry\Position;
use Kalle\Pdf\Layout\Page\PageSize;
use Kalle\Pdf\Profile;

require 'vendor/autoload.php';

$document = new Document(
    profile: Profile::pdf14(),
    title: 'Hello PDF',
    author: 'Example Company',
    subject: 'Getting Started',
    language: 'de-DE',
    creator: 'Example Service',
    creatorTool: 'CLI Export',
);

$document
    ->addKeyword('example')
    ->addKeyword('getting-started')
    ->registerFont('Helvetica');

$page = $document->addPage(PageSize::A4());
$page->addText('Hello PDF', new Position(20, 800), 'Helvetica', 16);
$page->addText('Das ist das erste erzeugte Dokument.', new Position(20, 775), 'Helvetica', 11);

file_put_contents('hello.pdf', $document->render());
```

Das erzeugt eine Datei `hello.pdf` im aktuellen Verzeichnis.

## Was hier passiert

1. `Document` sammelt PDF-Version, Metadaten und globale Konfiguration.
   Die PDF-Version wird heute ueber `Profile` gesteuert.
2. `registerFont('Helvetica')` macht die Schrift im Dokument verfuegbar.
3. `addPage(PageSize::A4())` erzeugt eine neue Seite.
4. `addText(...)` schreibt Inhalt auf die Seite.
5. `render()` serialisiert das komplette PDF.

## Profile und Standards

Neben `Profile::standard($version)` gibt es benannte Helfer fuer Standardprofile und Dokumentstandards:

```php
Profile::pdf10();
Profile::pdf14();
Profile::pdf20();
Profile::pdfA2u();
Profile::pdfUa1();
```

Wenn du mit PDF/A oder PDF/UA arbeitest, steht der aktuelle Unterstuetzungsstand in [pdf-standards.md](pdf-standards.md).

Fuer sichtbare Formular-Labels gibt es `FormFieldLabel`. Damit kannst du Label-Text explizit mit einem Widget-Aufruf verbinden, statt ihn getrennt als normalen Absatz zu rendern.

## Metadaten

Die Library rendert klassische PDF-Info-Metadaten und XMP-Metadaten konsistent.

Die wichtigsten Rollen:

- `author`: inhaltlicher Autor oder verantwortliche Organisation
- `creator`: erzeugendes System oder Service
- `creatorTool`: konkretes Tool oder UI, das den Export ausgeloest hat
- `producer`: PDF-Engine, automatisch aus Paketname und Version abgeleitet

## Weiterfuehrende Beispiele

Im Verzeichnis [examples](../examples/) liegen manuelle Beispielskripte fuer mehrere typische Dokumentvarianten:

- [rechnung.php](../examples/rechnung.php)
- [table.php](../examples/table.php)
- [table-of-contents.php](../examples/table-of-contents.php)
- [table-of-contents-after-cover.php](../examples/table-of-contents-after-cover.php)
- [table-of-contents-dashes.php](../examples/table-of-contents-dashes.php)
- [table-of-contents-dots.php](../examples/table-of-contents-dots.php)
- [table-of-contents-end.php](../examples/table-of-contents-end.php)
- [table-of-contents-logical.php](../examples/table-of-contents-logical.php)
- [table-of-contents-minimal.php](../examples/table-of-contents-minimal.php)
- [textbox.php](../examples/textbox.php)

Zum Beispiel:

```bash
php examples/rechnung.php
```

Die erzeugten Dateien landen in `var/examples`.

## Naechste Schritte

Wenn du nach dem ersten Dokument weitergehen willst, sind die typischen naechsten Themen:

- `TextFrame` fuer Absatzlayout und Seitenumbruch
- Tabellen ueber `createTable(...)`
- Bilder, Linien und Rechtecke
- Links, Outlines und benannte Ziele
- Verschluesselung ueber `encrypt(...)`

Die technische Einordnung dazu steht in [architecture.md](architecture.md). Der weitere Ausbau ist in [roadmap.md](roadmap.md) beschrieben.
