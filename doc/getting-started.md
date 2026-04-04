# Getting Started

Diese Library erzeugt PDFs direkt aus PHP. Der aktuelle Fokus liegt auf einer kleinen, klaren API fuer Dokumente, Seiten, Text und Fonts.

## Voraussetzungen

- PHP `^8.4`
- Erweiterungen `ext-iconv` und `ext-mbstring`
- Composer

## Installation

Abhaengigkeiten installieren:

```bash
composer install
```

## Schnellster Einstieg

Ein lauffaehiges Beispiel liegt in `example.php`.

Ausfuehren:

```bash
php example.php
```

Dabei wird eine PDF-Datei im Projektverzeichnis erzeugt.

## Erstes Dokument

```php
<?php

declare(strict_types=1);

use Kalle\Pdf\Document\Document;

require 'vendor/autoload.php';

$document = new Document(
    version: 1.4,
    title: 'Hello PDF',
    author: 'Example',
    subject: 'Getting Started',
    language: 'de-DE',
);

$document
    ->addKeyword('example')
    ->addFont('sans');

$page = $document->addPage();

$page->addText(
    'Hallo PDF',
    20,
    265,
    'NotoSans-Regular',
    24,
    'H1',
);

$pdfContent = $document->render();

file_put_contents('hello.pdf', $pdfContent);
```

## Was im Beispiel passiert

1. `Document` initialisiert das PDF mit Version und Metadaten.
2. `addFont('sans')` registriert eine Schrift, die spaeter auf Seiten verwendet werden kann.
3. `addPage()` erstellt eine neue Seite im Format `210 x 297`.
4. `addText()` positioniert Text mit `x`, `y`, Fontname, Schriftgroesse und Struktur-Tag.
5. `render()` gibt den kompletten PDF-Inhalt als String zurueck.

## Verfuegbare Font-Gruppen

Aktuell sind diese Gruppen ueber `Document::addFont()` vorbelegt:

- `sans` -> `NotoSans-Regular`
- `serif` -> `NotoSerif-Regular`
- `mono` -> `NotoSansMono-Regular`
- `global` -> `NotoSansCJKsc-Regular`

Wichtig: In `Page::addText()` wird nicht die Gruppe, sondern der konkrete Fontname verwendet, also zum Beispiel `NotoSans-Regular`.

## Unicode-Beispiel

Fuer breitere Zeichensaetze kann der globale Font registriert werden:

```php
$document->addFont('global');

$page->addText('漢字とカタカナ', 20, 225, 'NotoSansCJKsc-Regular', 14, 'P');
```

## Aktueller Funktionsumfang

Der derzeit belastbare Einstieg ist:

- Dokument anlegen
- Metadaten setzen
- Keywords hinzufuegen
- eine oder mehrere Seiten anlegen
- Text mit registrierten Fonts rendern
- Unicode-Text mit dem globalen Font rendern

## Aktuelle Grenzen

Der Codebestand enthaelt bereits weitere Bausteine, aber fuer den Einstieg solltest du aktuell von diesem Stand ausgehen:

- Bilder sind noch nicht fertig nutzbar
- die API ist noch klein und nah an der PDF-Struktur
- die Doku wird schrittweise parallel zum Code aufgebaut

## Naechste Datei

Als sinnvolle Fortsetzung bietet sich [architecture.md](architecture.md) an. Dort sollte beschrieben werden, wie `Document`, `Page`, `Resources`, `Contents` und `PdfRenderer` zusammenspielen.
