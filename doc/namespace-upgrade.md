# Namespace Upgrade

This project no longer exposes the former root-level API classes via Composer `classmap`.

Old consumer code must update imports to the package namespaces that match the `src/` structure.

## Common replacements

```php
use Kalle\Pdf\Document;
use Kalle\Pdf\Page;
use Kalle\Pdf\Table;
use Kalle\Pdf\TextFrame;
use Kalle\Pdf\Image;
use Kalle\Pdf\Profile;
use Kalle\Pdf\PdfVersion;
```

becomes

```php
use Kalle\Pdf\Document\Document;
use Kalle\Pdf\Page\Page;
use Kalle\Pdf\Table\Table;
use Kalle\Pdf\Text\TextFrame;
use Kalle\Pdf\Image\Image;
use Kalle\Pdf\Profile\Profile;
use Kalle\Pdf\Profile\PdfVersion;
```

## Other namespace moves

- `Kalle\Pdf\Layout\PageSize` -> `Kalle\Pdf\Layout\Page\PageSize`
- `Kalle\Pdf\Layout\Units` -> `Kalle\Pdf\Layout\Page\Units`
- `Kalle\Pdf\Graphics\Color` -> `Kalle\Pdf\Style\Color`
- `Kalle\Pdf\Element\Image` -> `Kalle\Pdf\Image\Image`
- `Kalle\Pdf\Document\Geometry\Position` -> `Kalle\Pdf\Layout\Geometry\Position`
- `Kalle\Pdf\Document\Geometry\Rect` -> `Kalle\Pdf\Layout\Geometry\Rect`
- `Kalle\Pdf\Document\Text\TextOptions` -> `Kalle\Pdf\Layout\Text\Input\TextOptions`
- `Kalle\Pdf\Document\Text\ParagraphOptions` -> `Kalle\Pdf\Layout\Text\Input\ParagraphOptions`
- `Kalle\Pdf\Document\Text\ListOptions` -> `Kalle\Pdf\Layout\Text\Input\ListOptions`
- `Kalle\Pdf\Document\Text\StructureTag` -> `Kalle\Pdf\TaggedPdf\StructureTag`
- `Kalle\Pdf\Document\ImageOptions` -> `Kalle\Pdf\Page\Content\ImageOptions`

## Why this changed

The old root aliases were a transitional compatibility layer. They were removed so the public API, file layout and PSR-4 autoloading now match directly.
