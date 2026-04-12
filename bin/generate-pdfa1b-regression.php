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

$outputPath = $argv[1] ?? dirname(__DIR__) . '/var/pdfa-regression/pdf-a-1b-minimal.pdf';
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
    ->profile(Profile::pdfA1b())
    ->title('PDF/A-1b Minimal Regression')
    ->author('kalle/pdf2')
    ->subject('Minimal PDF/A-1b regression fixture')
    ->language('de-DE')
    ->creator('Regression Fixture')
    ->creatorTool('bin/generate-pdfa1b-regression.php')
    ->text('PDF/A-1b Regression Привет', new TextOptions(
        x: 72,
        y: 760,
        fontSize: 18,
        embeddedFont: EmbeddedFontSource::fromPath($fontPath),
        color: Color::rgb(0.08, 0.16, 0.35),
    ))
    ->text('Dieses Dokument sichert den minimalen PDF/A-1b-Grundpfad mit eingebettetem Repo-Font und OutputIntent ab. Привет.', new TextOptions(
        x: 72,
        y: 724,
        width: 420,
        fontSize: 11,
        lineHeight: 15,
        embeddedFont: EmbeddedFontSource::fromPath($fontPath),
    ))
    ->build();

$renderer = new DocumentRenderer();
$output = new FileOutput($outputPath);
$renderer->write($document, $output);
$output->close();

fwrite(STDOUT, $outputPath . PHP_EOL);
