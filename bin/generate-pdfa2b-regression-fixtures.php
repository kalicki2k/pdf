#!/usr/bin/env php
<?php

declare(strict_types=1);

use Kalle\Pdf\Color\Color;
use Kalle\Pdf\Document\DefaultDocumentBuilder;
use Kalle\Pdf\Document\Document;
use Kalle\Pdf\Document\DocumentRenderer;
use Kalle\Pdf\Document\Profile;
use Kalle\Pdf\Font\EmbeddedFontSource;
use Kalle\Pdf\Text\TextOptions;
use Kalle\Pdf\Writer\FileOutput;

require dirname(__DIR__) . '/vendor/autoload.php';

if ($argc !== 2) {
    fwrite(STDERR, "Usage: bin/generate-pdfa2b-regression-fixtures.php <output-dir>\n");
    exit(1);
}

$outputDir = $argv[1];

if (!is_dir($outputDir) && !mkdir($outputDir, 0777, true) && !is_dir($outputDir)) {
    fwrite(STDERR, sprintf("Could not create output directory: %s\n", $outputDir));
    exit(1);
}

$fixtures = [
    $outputDir . '/pdf-a-2b-minimal.pdf' => createPdfA2bMinimalFixture(),
    $outputDir . '/pdf-a-2b-link-annotation.pdf' => createPdfA2bLinkAnnotationFixture(),
    $outputDir . '/pdf-a-2b-text-annotation.pdf' => createPdfA2bTextAnnotationFixture(),
];

$renderer = new DocumentRenderer();

foreach ($fixtures as $path => $document) {
    $output = new FileOutput($path);
    $renderer->write($document, $output);
    $output->close();
    fwrite(STDOUT, $path . PHP_EOL);
}

function createPdfA2bMinimalFixture(): Document
{
    return regressionBuilder('PDF/A-2b Minimal Regression', 'Minimal PDF/A-2b regression fixture')
        ->text('PDF/A-2b Regression', TextOptions::make(
            x: 72,
            y: 760,
            fontSize: 18,
            embeddedFont: EmbeddedFontSource::fromPath(regressionFontPath()),
            color: Color::rgb(0.08, 0.16, 0.35),
        ))
        ->build();
}

function createPdfA2bLinkAnnotationFixture(): Document
{
    return regressionBuilder('PDF/A-2b Link Annotation Regression', 'PDF/A-2b link annotation regression fixture')
        ->text('PDF/A-2b Link Regression', TextOptions::make(
            x: 72,
            y: 760,
            fontSize: 18,
            embeddedFont: EmbeddedFontSource::fromPath(regressionFontPath()),
            color: Color::rgb(0.08, 0.16, 0.35),
        ))
        ->link('https://example.com/spec', 72, 670, 180, 16, 'Specification Link')
        ->build();
}

function createPdfA2bTextAnnotationFixture(): Document
{
    return regressionBuilder('PDF/A-2b Text Annotation Regression', 'PDF/A-2b text annotation regression fixture')
        ->text('PDF/A-2b Comment Regression', TextOptions::make(
            x: 72,
            y: 760,
            fontSize: 18,
            embeddedFont: EmbeddedFontSource::fromPath(regressionFontPath()),
            color: Color::rgb(0.08, 0.16, 0.35),
        ))
        ->textAnnotation(72, 680, 18, 18, 'Kommentar', 'QA', 'Comment', true)
        ->build();
}

function regressionBuilder(string $title, string $subject): DefaultDocumentBuilder
{
    return DefaultDocumentBuilder::make()
        ->profile(Profile::pdfA2b())
        ->title($title)
        ->author('kalle/pdf2')
        ->subject($subject)
        ->language('de-DE')
        ->creator('Regression Fixture')
        ->creatorTool('bin/generate-pdfa2b-regression-fixtures.php');
}

function regressionFontPath(): string
{
    $path = dirname(__DIR__) . '/assets/fonts/noto-sans/NotoSans-Regular.ttf';

    if (!is_file($path)) {
        throw new RuntimeException(sprintf('Required regression font not found: %s', $path));
    }

    return $path;
}
