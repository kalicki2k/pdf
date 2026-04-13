#!/usr/bin/env php
<?php

declare(strict_types=1);

use Kalle\Pdf\Color\Color;
use Kalle\Pdf\Document\DefaultDocumentBuilder;
use Kalle\Pdf\Document\DocumentRenderer;
use Kalle\Pdf\Document\Profile;
use Kalle\Pdf\Font\EmbeddedFontSource;
use Kalle\Pdf\Image\ImageAccessibility;
use Kalle\Pdf\Image\ImageColorSpace;
use Kalle\Pdf\Image\ImagePlacement;
use Kalle\Pdf\Image\ImageSource;
use Kalle\Pdf\Text\TextOptions;
use Kalle\Pdf\Writer\FileOutput;

require dirname(__DIR__) . '/vendor/autoload.php';

if ($argc !== 2) {
    fwrite(STDERR, "Usage: bin/generate-pdfa1a-multipage-regression.php <output-pdf>\n");
    exit(1);
}

$fontPath = dirname(__DIR__) . '/assets/fonts/noto-sans/NotoSans-Regular.ttf';

if (!is_file($fontPath)) {
    fwrite(STDERR, sprintf("Required regression font not found: %s\n", $fontPath));
    exit(1);
}

$document = DefaultDocumentBuilder::make()
    ->profile(Profile::pdfA1a())
    ->title('PDF/A-1a Multipage Structure Regression')
    ->author('kalle/pdf2')
    ->subject('PDF/A-1a multipage structure and reading order regression fixture')
    ->language('de-DE')
    ->creator('Regression Fixture')
    ->creatorTool('bin/generate-pdfa1a-multipage-regression.php')
    ->heading('Kapitel Eins Привет', 1, new TextOptions(
        x: 72,
        y: 760,
        fontSize: 18,
        embeddedFont: EmbeddedFontSource::fromPath($fontPath),
        color: Color::rgb(0.08, 0.16, 0.35),
    ))
    ->text('Die erste Seite prueft die strukturierte Lesereihenfolge mit Ueberschrift, Absatz und Liste. Привет.', new TextOptions(
        x: 72,
        y: 724,
        width: 360,
        fontSize: 12,
        lineHeight: 16,
        embeddedFont: EmbeddedFontSource::fromPath($fontPath),
    ))
    ->list(
        ['Erster Punkt Привет', 'Zweiter Punkt Привет', 'Dritter Punkt Привет'],
        text: new TextOptions(
            x: 72,
            y: 668,
            width: 280,
            fontSize: 12,
            lineHeight: 16,
            embeddedFont: EmbeddedFontSource::fromPath($fontPath),
        ),
    )
    ->newPage()
    ->heading('Kapitel Zwei Привет', 1, new TextOptions(
        x: 72,
        y: 760,
        fontSize: 18,
        embeddedFont: EmbeddedFontSource::fromPath($fontPath),
        color: Color::rgb(0.08, 0.16, 0.35),
    ))
    ->text('Die zweite Seite fuehrt den Strukturbaum mit Absatz, Bild und Link fort. Привет.', new TextOptions(
        x: 72,
        y: 724,
        width: 360,
        fontSize: 12,
        lineHeight: 16,
        embeddedFont: EmbeddedFontSource::fromPath($fontPath),
    ))
    ->image(
        ImageSource::jpeg('jpeg-bytes', 200, 100, ImageColorSpace::RGB),
        ImagePlacement::at(72, 600, width: 140),
        ImageAccessibility::alternativeText('Projektgrafik Seite zwei'),
    )
    ->linkToPage(1, 72, 540, 180, 16, 'Spezifikation oeffnen')
    ->build();

$output = new FileOutput($argv[1]);
(new DocumentRenderer())->write($document, $output);
$output->close();
