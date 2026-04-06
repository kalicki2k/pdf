# kalle/pdf

A PHP library for generating PDF files directly from code.

## Overview

`kalle/pdf` builds PDF documents from a small PHP object model and renders them directly to PDF syntax.

Current core coverage includes:

- documents and pages
- text with standard and embedded fonts
- simple flow layout via `TextFrame`
- lists and basic tables
- images, lines, rectangles, and path-based shapes
- links and named destinations
- classic PDF info metadata and XMP metadata
- optional password-based PDF encryption
- outlines, named table-of-contents placement, logical TOC page numbers, attachments, layers, and basic form fields

The current focus is a stable core library. PDF/A, PDF/UA, and full tagged-PDF compliance are not the current target yet.

## Requirements

- PHP `^8.4`
- `ext-mbstring`
- Composer

## Installation

```bash
composer require kalle/pdf
```

## Quick Start

The public entry points live in the root namespace:

- `Kalle\Pdf\Document`
- `Kalle\Pdf\Page`
- `Kalle\Pdf\TextFrame`
- `Kalle\Pdf\Table`

```php
<?php

declare(strict_types=1);

use Kalle\Pdf\Document;
use Kalle\Pdf\Document\Geometry\Position;
use Kalle\Pdf\Layout\PageSize;

require 'vendor/autoload.php';

$document = new Document(
    version: 1.4,
    title: 'Hello PDF',
    author: 'Example Company',
    subject: 'Demo document',
    language: 'en-US',
    creator: 'Example Service',
    creatorTool: 'CLI Export',
);

$document->registerFont('Helvetica');

$page = $document->addPage(PageSize::A4());
$page->addText('Hello PDF', new Position(20, 800), 'Helvetica', 16);

$page->createTextFrame(new Position(20, 760), 300)
    ->addParagraph(
        'This paragraph is rendered through the public TextFrame API.',
        'Helvetica',
        11,
    );

file_put_contents('hello.pdf', $document->render());
```

## Examples

Example scripts live in [examples](examples/):

- [rechnung.php](examples/rechnung.php)
- [table.php](examples/table.php)
- [table-of-contents.php](examples/table-of-contents.php)
- [table-of-contents-after-cover.php](examples/table-of-contents-after-cover.php)
- [table-of-contents-end.php](examples/table-of-contents-end.php)
- [table-of-contents-logical.php](examples/table-of-contents-logical.php)
- [textbox.php](examples/textbox.php)

Run one of them with:

```bash
php examples/rechnung.php
```

Generated example PDFs are written to `var/examples`.

## Metadata

The library supports both metadata layers that are relevant for normal PDF generation:

- classic PDF info entries such as `Title`, `Author`, `Subject`, `Keywords`, `Creator`, `Producer`, `CreationDate`, and `ModDate`
- XMP metadata via a catalog `/Metadata` stream

Current metadata role split:

- `author`: content author or responsible organization
- `creator`: generating application or service
- `creatorTool`: concrete tool or UI that triggered the export
- `producer`: PDF engine, derived from the package name and installed version

## Development

Run tests:

```bash
composer test
```

Run static analysis:

```bash
composer phpstan
```

Check code style:

```bash
composer cs:check
```

## Release

```bash
git tag 0.1.0-alpha1
git push origin 0.1.0-alpha1
```

## License

[MIT](LICENSE)
