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
    fwrite(STDERR, "Usage: bin/generate-pdfa1a-mixed-regression.php <output-pdf>\n");
    exit(1);
}

$fontPath = dirname(__DIR__) . '/assets/fonts/noto-sans/NotoSans-Regular.ttf';

if (!is_file($fontPath)) {
    fwrite(STDERR, sprintf("Required regression font not found: %s\n", $fontPath));
    exit(1);
}

$document = DefaultDocumentBuilder::make()
    ->profile(Profile::pdfA1a())
    ->title('PDF/A-1a Mixed Structure Regression')
    ->author('kalle/pdf2')
    ->subject('PDF/A-1a mixed heading paragraph list image link regression fixture')
    ->language('de-DE')
    ->creator('Regression Fixture')
    ->creatorTool('bin/generate-pdfa1a-mixed-regression.php')
    ->text('Projektübersicht Привет', TextOptions::make(
        left: 72,
        bottom: 760,
        fontSize: 18,
        embeddedFont: EmbeddedFontSource::fromPath($fontPath),
        color: Color::rgb(0.08, 0.16, 0.35),
    ))
    ->text('Dieses Dokument kombiniert Überschrift, Absatz, Liste, Bild und Link in einem PDF/A-1a-Fall. Привет.', TextOptions::make(
        left: 72,
        bottom: 724,
        width: 360,
        fontSize: 12,
        lineHeight: 16,
        embeddedFont: EmbeddedFontSource::fromPath($fontPath),
    ))
    ->list(
        ['Erster Prüfpunkt Привет', 'Zweiter Prüfpunkt Привет'],
        text: TextOptions::make(
            left: 72,
            bottom: 670,
            width: 260,
            fontSize: 12,
            lineHeight: 16,
            embeddedFont: EmbeddedFontSource::fromPath($fontPath),
        ),
    )
    ->image(
        ImageSource::jpeg('jpeg-bytes', 200, 100, ImageColorSpace::RGB),
        ImagePlacement::absolute(left: 340, bottom: 610, width: 140),
        ImageAccessibility::alternativeText('Projektgrafik'),
    )
    ->linkToPage(1, 72, 560, 180, 16, 'Spezifikation öffnen')
    ->build();

$output = new FileOutput($argv[1]);
(new DocumentRenderer())->write($document, $output);
$output->close();
