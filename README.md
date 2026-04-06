# kalle/pdf

Small PHP library for generating PDF files directly from code.

## Status

The project already covers a usable core for:

- documents and pages
- text and embedded fonts
- simple flow layout via `TextFrame`
- lists and basic tables
- images, lines, rectangles, and path-based shapes
- links and document metadata including classic PDF info entries and XMP metadata

The current focus is a stable core library, not PDF/A, PDF/UA, or full tagged-PDF compliance yet.

## Requirements

- PHP `^8.4`
- `ext-iconv`
- `ext-mbstring`
- Composer

## Install

```bash
composer install
```

## Quick Start

Run the example:

```bash
php example.php
```

This generates a PDF file in the project directory.

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

## Documentation

- [Getting Started](doc/getting-started.md)
- [Architecture](doc/architecture.md)
- [Roadmap](doc/roadmap.md)
