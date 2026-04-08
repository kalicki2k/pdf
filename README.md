# kalle/pdf

![PHP 8.4](https://img.shields.io/badge/PHP-8.4-777bb4?logo=php&logoColor=white)
![Tests Passing](https://img.shields.io/badge/tests-passing-2ea44f?logo=githubactions&logoColor=white)
![PHPStan No Errors](https://img.shields.io/badge/PHPStan-no%20errors-2ea44f?logo=php&logoColor=white)
![Code Coverage 100%](https://img.shields.io/badge/coverage-100%25-2ea44f?logo=codecov&logoColor=white)
![License MIT](https://img.shields.io/badge/license-MIT-1f6feb?logo=open-source-initiative&logoColor=white)

A lightweight, native PDF engine for PHP for creating structured PDF documents programmatically.

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

The current focus is a stable core library. PDF/A now has a validated baseline across all supported conformance profiles, and PDF/UA-1 currently has a validated baseline for the supported tagged content, links, annotations, and form widget paths.

The current support matrix and the active API rules for PDF/A and PDF/UA-1 are documented in [doc/pdf-standards.md](doc/pdf-standards.md).

## Requirements

- PHP `^8.4`
- `ext-mbstring`
- Composer

## Installation

```bash
composer require kalle/pdf
```

Named standard profile helpers are available from `Profile::pdf10()` to `Profile::pdf20()`.
If the target version is dynamic, `Profile::standard($version)` remains available.

For document standards there are dedicated helpers such as `Profile::pdfA2u()` and `Profile::pdfUa1()`.

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
use Kalle\Pdf\Profile;

require 'vendor/autoload.php';

$document = new Document(
    profile: Profile::pdf14(),
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
- [pdf-a-2b.php](examples/pdf-a-2b.php)
- [pdf-a-2u.php](examples/pdf-a-2u.php)
- [pdf-a-3b.php](examples/pdf-a-3b.php)
- [pdf-a-3u.php](examples/pdf-a-3u.php)
- [pdf-a-4.php](examples/pdf-a-4.php)
- [pdf-a-4e.php](examples/pdf-a-4e.php)
- [pdf-a-4f.php](examples/pdf-a-4f.php)
- [pdf-ua-1.php](examples/pdf-ua-1.php)
- [table-caption.php](examples/table-caption.php)
- [table-caption-pagination.php](examples/table-caption-pagination.php)
- [table.php](examples/table.php)
- [table-of-contents.php](examples/table-of-contents.php)
- [table-of-contents-after-cover.php](examples/table-of-contents-after-cover.php)
- [table-of-contents-dashes.php](examples/table-of-contents-dashes.php)
- [table-of-contents-dots.php](examples/table-of-contents-dots.php)
- [table-of-contents-end.php](examples/table-of-contents-end.php)
- [table-of-contents-logical.php](examples/table-of-contents-logical.php)
- [table-of-contents-minimal.php](examples/table-of-contents-minimal.php)
- [textbox.php](examples/textbox.php)

Run one of them with:

```bash
php examples/rechnung.php
```

Generated example PDFs are written to `var/examples`.

For the PDF/A examples:

```bash
composer example:pdfa1a
composer validate:pdfa -- var/examples/pdf-a-1a_YYYY-mm-dd-HH-ii-ss.pdf

composer example:pdfa1b
composer validate:pdfa -- var/examples/pdf-a-1b_YYYY-mm-dd-HH-ii-ss.pdf

composer example:pdfa2a
composer validate:pdfa -- var/examples/pdf-a-2a_YYYY-mm-dd-HH-ii-ss.pdf

composer example:pdfa2b
composer validate:pdfa -- var/examples/pdf-a-2b_YYYY-mm-dd-HH-ii-ss.pdf

composer example:pdfa2u
composer validate:pdfa -- var/examples/pdf-a-2u_YYYY-mm-dd-HH-ii-ss.pdf

composer example:pdfa3a
composer validate:pdfa -- var/examples/pdf-a-3a_YYYY-mm-dd-HH-ii-ss.pdf

composer example:pdfa3b
composer validate:pdfa -- var/examples/pdf-a-3b_YYYY-mm-dd-HH-ii-ss.pdf

composer example:pdfa3u
composer validate:pdfa -- var/examples/pdf-a-3u_YYYY-mm-dd-HH-ii-ss.pdf

composer example:pdfa4
composer validate:pdfa -- var/examples/pdf-a-4_YYYY-mm-dd-HH-ii-ss.pdf

composer example:pdfa4e
composer validate:pdfa -- var/examples/pdf-a-4e_YYYY-mm-dd-HH-ii-ss.pdf

composer example:pdfa4f
composer validate:pdfa -- var/examples/pdf-a-4f_YYYY-mm-dd-HH-ii-ss.pdf

composer example:pdfua1
composer validate:pdfua -- var/examples/pdf-ua-1_YYYY-mm-dd-HH-ii-ss.pdf
```

The PDF/A validation command uses the official Docker image `verapdf/cli:v1.28.2`.

For an automated PDF/A regression run with representative fixtures for `PDF/A-1a`, `PDF/A-1b`, `PDF/A-2a`, `PDF/A-2b`, `PDF/A-2u`, `PDF/A-3a`, `PDF/A-3b`, `PDF/A-3u`, `PDF/A-4`, `PDF/A-4e`, and `PDF/A-4f`:

```bash
composer test:pdfa-regression
```

For PDF/UA-1 there is a matching automated veraPDF regression run with representative fixtures for the supported baseline, layout/decorative graphics, link, form widget, widget appearance, widget state, annotation, multipage table-caption pagination, mixed, and deep mixed integration paths:

```bash
composer test:pdfua-regression
```

There is also a negative regression run with targeted invalid fixtures that must fail veraPDF:

```bash
composer test:pdfua-negative-regression
```

The negative PDF/UA run currently covers document metadata, marked-content and structure root integrity, parent tree and page structure references, figure alt text, list and table semantics, link and annotation structure, widget tab order/structure, and form label semantics.

Further detail on supported profiles, active guards and current limits lives in [doc/pdf-standards.md](doc/pdf-standards.md).

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
