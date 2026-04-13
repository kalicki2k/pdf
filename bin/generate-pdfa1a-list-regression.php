#!/usr/bin/env php
<?php

declare(strict_types=1);

use Kalle\Pdf\Document\DefaultDocumentBuilder;
use Kalle\Pdf\Document\DocumentRenderer;
use Kalle\Pdf\Document\Profile;
use Kalle\Pdf\Font\EmbeddedFontSource;
use Kalle\Pdf\Text\TextOptions;
use Kalle\Pdf\Writer\FileOutput;

require dirname(__DIR__) . '/vendor/autoload.php';

if ($argc !== 2) {
    fwrite(STDERR, "Usage: bin/generate-pdfa1a-list-regression.php <output-pdf>\n");
    exit(1);
}

$fontPath = dirname(__DIR__) . '/assets/fonts/noto-sans/NotoSans-Regular.ttf';

if (!is_file($fontPath)) {
    fwrite(STDERR, sprintf("Required regression font not found: %s\n", $fontPath));
    exit(1);
}

$document = DefaultDocumentBuilder::make()
    ->profile(Profile::pdfA1a())
    ->title('PDF/A-1a List Structure Regression')
    ->author('kalle/pdf2')
    ->subject('PDF/A-1a structured list regression fixture')
    ->language('de-DE')
    ->creator('Regression Fixture')
    ->creatorTool('bin/generate-pdfa1a-list-regression.php')
    ->text('Liste Привет', new TextOptions(
        x: 72,
        y: 760,
        fontSize: 18,
        embeddedFont: EmbeddedFontSource::fromPath($fontPath),
    ))
    ->list(
        ['Erster Punkt Привет', 'Zweiter Punkt Привет'],
        text: new TextOptions(
            x: 72,
            y: 720,
            width: 360,
            fontSize: 12,
            lineHeight: 16,
            embeddedFont: EmbeddedFontSource::fromPath($fontPath),
        ),
    )
    ->build();

$output = new FileOutput($argv[1]);
(new DocumentRenderer())->write($document, $output);
$output->close();
