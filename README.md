# kalle/pdf

A lightweight, native PDF engine for PHP for creating structured PDF documents programmatically.

## Requirements

- PHP `^8.4`
- `ext-mbstring`
- Composer

## Installation

```bash
composer require kalle/pdf
```

## Quick Start

```php
<?php

declare(strict_types=1);

use Kalle\Pdf\Document\Document;
use Kalle\Pdf\Layout\Geometry\Position;
use Kalle\Pdf\Layout\Page\PageSize;
use Kalle\Pdf\Profile\Profile;

require 'vendor/autoload.php';

$document = new Document(
    profile: Profile::pdf14(),
    title: 'Hello PDF',
    author: 'Example Company',
);

$document->registerFont('Helvetica');

$page = $document->addPage(PageSize::A4());
$page->addText('Hello PDF', new Position(20, 800), 'Helvetica', 16);

$document->writeToFile('hello.pdf');
```

## Examples

Example scripts live in [examples](examples).

Run one of them with:

```bash
php examples/rechnung.php
```

Generated example PDFs are written to `var/examples`.

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

## License

[MIT](LICENSE)
