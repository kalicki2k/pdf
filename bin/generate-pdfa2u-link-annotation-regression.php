#!/usr/bin/env php
<?php

declare(strict_types=1);

use Kalle\Pdf\Color\Color;
use Kalle\Pdf\Document\DefaultDocumentBuilder;
use Kalle\Pdf\Document\DocumentRenderer;
use Kalle\Pdf\Document\Profile;
use Kalle\Pdf\Font\EmbeddedFontSource;
use Kalle\Pdf\Text\TextOptions;
use Kalle\Pdf\Writer\FileOutput;

require dirname(__DIR__) . '/vendor/autoload.php';

$outputPath = $argv[1] ?? dirname(__DIR__) . '/var/pdfa-regression/pdf-a-2u-link-annotation.pdf';
$outputDirectory = dirname($outputPath);

if (!is_dir($outputDirectory) && !mkdir($outputDirectory, 0777, true) && !is_dir($outputDirectory)) {
    fwrite(STDERR, sprintf("Could not create output directory: %s\n", $outputDirectory));
    exit(1);
}

$fontPath = dirname(__DIR__) . '/assets/fonts/noto-sans/NotoSans-Regular.ttf';

if (!is_file($fontPath)) {
    fwrite(STDERR, sprintf("Required regression font not found: %s\n", $fontPath));
    exit(1);
}

$document = DefaultDocumentBuilder::make()
    ->profile(Profile::pdfA2u())
    ->title('PDF/A-2u Link Annotation Regression')
    ->author('kalle/pdf2')
    ->subject('PDF/A-2u link annotation regression fixture')
    ->language('de-DE')
    ->creator('Regression Fixture')
    ->creatorTool('bin/generate-pdfa2u-link-annotation-regression.php')
    ->text('PDF/A-2u Link Regression Привет', new TextOptions(
        x: 72,
        y: 760,
        fontSize: 18,
        embeddedFont: EmbeddedFontSource::fromPath($fontPath),
        color: Color::rgb(0.08, 0.16, 0.35),
    ))
    ->text('Weitere Infos im Archivprofil. Подробнее.', new TextOptions(
        x: 72,
        y: 724,
        width: 420,
        fontSize: 11,
        lineHeight: 15,
        embeddedFont: EmbeddedFontSource::fromPath($fontPath),
    ))
    ->link('https://example.com/spec', 72, 670, 180, 16, 'Specification Link')
    ->build();

$renderer = new DocumentRenderer();
$output = new FileOutput($outputPath);
$renderer->write($document, $output);
$output->close();

fwrite(STDOUT, $outputPath . PHP_EOL);
