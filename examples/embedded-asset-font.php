<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Kalle\Pdf\Color\Color;
use Kalle\Pdf\Font\EmbeddedFontSource;
use Kalle\Pdf\Page\PageSize;
use Kalle\Pdf\Pdf;
use Kalle\Pdf\Text\TextOptions;

$outputDirectory = __DIR__ . '/../var/examples';

if (!is_dir($outputDirectory) && !mkdir($outputDirectory, 0777, true) && !is_dir($outputDirectory)) {
    throw new RuntimeException('Unable to create example output directory.');
}

$fontPath = __DIR__ . '/../assets/fonts/inter/static/Inter-Regular.ttf';

if (!is_file($fontPath)) {
    throw new RuntimeException(sprintf('Embedded example font not found: %s', $fontPath));
}

$fontSource = EmbeddedFontSource::fromPath($fontPath);

Pdf::document()
    ->title('Embedded Asset Font Example')
    ->author('Kalle PDF')
    ->subject('Embedded font example using a local asset font')
    ->creator('examples/embedded-asset-font.php')
    ->creatorTool('pdf2')
    ->pageSize(PageSize::A4()->portrait())
    ->text('Embedded asset font', new TextOptions(
        fontSize: 24,
        embeddedFont: $fontSource,
        color: Color::hex('#111827'),
    ))
    ->text('This example embeds Inter-Regular.ttf from assets/fonts and writes it into the PDF as an embedded TrueType font.', new TextOptions(
        fontSize: 12,
        lineHeight: 16,
        embeddedFont: $fontSource,
        color: Color::hex('#334155'),
    ))
    ->text('The goal is to keep the example small and explicit so the embedded-font API remains easy to inspect.', new TextOptions(
        fontSize: 12,
        lineHeight: 16,
        embeddedFont: $fontSource,
        color: Color::hex('#475569'),
    ))
    ->writeToFile($outputDirectory . '/embedded-asset-font.pdf');
