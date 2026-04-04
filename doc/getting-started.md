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
use Kalle\Pdf\Document\PageSize;

require 'vendor/autoload.php';

$document = new Document(
    version: 1.4,
    title: 'Hello PDF',
    author: 'Example',
    subject: 'Getting Started',
    language: 'de-DE',
    fontConfig: require __DIR__ . '/../config/fonts.php',
);

$document
    ->addKeyword('example')
    ->addFont('NotoSans-Regular');

$page = $document->addPage(PageSize::A4());

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
2. `addFont('NotoSans-Regular')` registriert eine eingebettete Schrift aus der Font-Konfiguration.
3. `addPage()` erstellt eine neue Seite, standardmaessig im Format `210 x 297` oder explizit ueber `PageSize::A4()`.
4. `addText()` positioniert Text mit `x`, `y`, Fontname, Schriftgroesse und optionalem Struktur-Tag.
5. `render()` gibt den kompletten PDF-Inhalt als String zurueck.

## Font-Konfiguration

Die eingebetteten Fonts werden standardmaessig ueber `config/fonts.php` definiert.

Aktuell sind dort unter anderem registriert:

- `NotoSans-Regular`
- `NotoSerif-Regular`
- `NotoSansMono-Regular`
- `NotoSansCJKsc-Regular`

Standard-PDF-Fonts wie `Helvetica` benoetigen keinen Eintrag in der Config.

Du kannst die globale Config verwenden oder pro Dokument eine eigene Liste ueber `fontConfig` setzen.

## Unicode-Beispiel

Fuer breitere Zeichensaetze kann ein Unicode-Font direkt ueber seinen Fontnamen registriert werden:

```php
$document->addFont('NotoSansCJKsc-Regular');

$page->addText('漢字とカタカナ', 20, 225, 'NotoSansCJKsc-Regular', 14, 'P');
```

## Dokumenteigene Font-Konfiguration

Zusatzlich zur globalen `config/fonts.php` kann ein Dokument eine eigene Font-Konfiguration erhalten:

```php
$document = new Document(
    version: 1.4,
    fontConfig: [
        [
            'baseFont' => 'CustomSans-Regular',
            'path' => 'assets/fonts/NotoSans-Regular.ttf',
            'unicode' => true,
            'subtype' => 'CIDFontType2',
            'encoding' => 'Identity-H',
        ],
    ],
);

$document->addFont('CustomSans-Regular');
```

## Aktueller Funktionsumfang

Der derzeit belastbare Einstieg ist:

- Dokument anlegen
- Metadaten setzen
- Keywords hinzufuegen
- globale oder dokumenteigene Fonts konfigurieren
- eine oder mehrere Seiten anlegen
- Text mit registrierten Fonts rendern
- Unicode-Text mit eingebetteten Fonts rendern

## Aktuelle Grenzen

Der Codebestand enthaelt bereits weitere Bausteine, aber fuer den Einstieg solltest du aktuell von diesem Stand ausgehen:

- Bilder sind noch nicht fertig nutzbar
- die API ist noch klein und nah an der PDF-Struktur
- die Doku wird schrittweise parallel zum Code aufgebaut

## Naechste Datei

Als sinnvolle Fortsetzung bietet sich [architecture.md](architecture.md) an. Dort sollte beschrieben werden, wie `Document`, `Page`, `Resources`, `Contents` und `PdfRenderer` zusammenspielen.
