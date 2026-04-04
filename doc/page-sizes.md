# Page Sizes

`PageSize` ist der Helper fuer vordefinierte und benutzerdefinierte Seitengroessen in `Document::addPage()`.

## Verwendung

```php
use Kalle\Pdf\Document\Document;
use Kalle\Pdf\Document\PageSize;

$document = new Document();

$document->addPage();
$document->addPage(PageSize::A4());
$document->addPage(PageSize::A4()->landscape());
$document->addPage(PageSize::custom(148.0, 210.0));
```

Wichtig:

- `addPage()` ohne Argumente bleibt bei `210 x 297`
- `PageSize`-Werte sind immutable
- `landscape()` und `portrait()` liefern neue Instanzen
- alle Masse sind in PDF-User-Units beziehungsweise Punkten notiert

## Verfuegbare Formate

### A-Serie

- `A00`
- `A0`
- `A1`
- `A2`
- `A3`
- `A4`
- `A5`
- `A6`
- `A7`
- `A8`
- `A9`

### B-Serie

- `B0`
- `B1`
- `B2`
- `B3`
- `B4`
- `B5`
- `B6`
- `B7`
- `B8`
- `B9`
- `B10`

### C-Serie

- `C0`
- `C1`
- `C2`
- `C3`
- `C4`
- `C5`
- `C6`
- `C7`
- `C8`
- `C9`
- `C10`

## Beispiele

```php
$poster = $document->addPage(PageSize::A00());
$printSheet = $document->addPage(PageSize::B2());
$envelopeLayout = $document->addPage(PageSize::C4()->landscape());
```

## Eigene Groessen

Fuer Sonderfaelle ohne vordefiniertes Format:

```php
$document->addPage(PageSize::custom(320.0, 180.0));
```
