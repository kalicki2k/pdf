#!/usr/bin/env php
<?php

declare(strict_types=1);

use Kalle\Pdf\Color\Color;
use Kalle\Pdf\Document\DefaultDocumentBuilder;
use Kalle\Pdf\Document\Document;
use Kalle\Pdf\Document\DocumentRenderer;
use Kalle\Pdf\Document\Profile;
use Kalle\Pdf\Font\EmbeddedFontSource;
use Kalle\Pdf\Image\ImageColorSpace;
use Kalle\Pdf\Image\ImagePlacement;
use Kalle\Pdf\Image\ImageSource;
use Kalle\Pdf\Text\TextOptions;
use Kalle\Pdf\Writer\FileOutput;

require dirname(__DIR__) . '/vendor/autoload.php';

if ($argc !== 2) {
    fwrite(STDERR, "Usage: bin/generate-pdfa2u-regression-fixtures.php <output-dir>\n");
    exit(1);
}

$outputDir = $argv[1];

if (!is_dir($outputDir) && !mkdir($outputDir, 0777, true) && !is_dir($outputDir)) {
    fwrite(STDERR, sprintf("Could not create output directory: %s\n", $outputDir));
    exit(1);
}

$fixtures = [
    $outputDir . '/pdf-a-2u-minimal.pdf' => createPdfA2uMinimalFixture(),
    $outputDir . '/pdf-a-2u-image.pdf' => createPdfA2uImageFixture(),
    $outputDir . '/pdf-a-2u-internal-links.pdf' => createPdfA2uInternalLinksFixture(),
    $outputDir . '/pdf-a-2u-link-annotation.pdf' => createPdfA2uLinkAnnotationFixture(),
    $outputDir . '/pdf-a-2u-text-annotation.pdf' => createPdfA2uTextAnnotationFixture(),
    $outputDir . '/pdf-a-2u-highlight-annotation.pdf' => createPdfA2uHighlightAnnotationFixture(),
    $outputDir . '/pdf-a-2u-freetext-annotation.pdf' => createPdfA2uFreeTextAnnotationFixture(),
];

$renderer = new DocumentRenderer();

foreach ($fixtures as $path => $document) {
    $output = new FileOutput($path);
    $renderer->write($document, $output);
    $output->close();
    fwrite(STDOUT, $path . PHP_EOL);
}

function createPdfA2uMinimalFixture(): Document
{
    $fontPath = regressionFontPath();

    return regressionBuilder('PDF/A-2u Minimal Regression', 'Minimal PDF/A-2u regression fixture')
        ->text('PDF/A-2u Regression Привет', TextOptions::make(
            x: 72,
            y: 760,
            fontSize: 18,
            embeddedFont: EmbeddedFontSource::fromPath($fontPath),
            color: Color::rgb(0.08, 0.16, 0.35),
        ))
        ->text('Dieses Dokument sichert den minimalen PDF/A-2u-Grundpfad mit Unicode-Font, XMP und OutputIntent ab. Подробнее.', TextOptions::make(
            x: 72,
            y: 724,
            width: 420,
            fontSize: 11,
            lineHeight: 15,
            embeddedFont: EmbeddedFontSource::fromPath($fontPath),
        ))
        ->build();
}

function createPdfA2uImageFixture(): Document
{
    $fontPath = regressionFontPath();

    return regressionBuilder('PDF/A-2u Image Regression', 'PDF/A-2u image regression fixture')
        ->text('PDF/A-2u Bild Regression Привет', TextOptions::make(
            x: 72,
            y: 760,
            fontSize: 18,
            embeddedFont: EmbeddedFontSource::fromPath($fontPath),
            color: Color::rgb(0.08, 0.16, 0.35),
        ))
        ->image(
            ImageSource::jpeg('jpeg-bytes', 200, 100, ImageColorSpace::RGB),
            ImagePlacement::at(72, 610, width: 160),
        )
        ->text('RGB-JPEG ohne Transparenz. Подробнее.', TextOptions::make(
            x: 72,
            y: 580,
            width: 360,
            fontSize: 11,
            lineHeight: 15,
            embeddedFont: EmbeddedFontSource::fromPath($fontPath),
        ))
        ->build();
}

function createPdfA2uInternalLinksFixture(): Document
{
    $fontPath = regressionFontPath();

    return regressionBuilder('PDF/A-2u Internal Link Regression', 'PDF/A-2u internal link regression fixture')
        ->namedDestination('intro')
        ->text('Einleitung Привет', TextOptions::make(
            x: 72,
            y: 760,
            fontSize: 18,
            embeddedFont: EmbeddedFontSource::fromPath($fontPath),
            color: Color::rgb(0.08, 0.16, 0.35),
        ))
        ->text('Zielseite fuer interne Links. Подробнее.', TextOptions::make(
            x: 72,
            y: 724,
            width: 360,
            fontSize: 11,
            lineHeight: 15,
            embeddedFont: EmbeddedFontSource::fromPath($fontPath),
        ))
        ->newPage()
        ->text('Linkseite Привет', TextOptions::make(
            x: 72,
            y: 760,
            fontSize: 18,
            embeddedFont: EmbeddedFontSource::fromPath($fontPath),
            color: Color::rgb(0.08, 0.16, 0.35),
        ))
        ->linkToPage(1, 72, 680, 180, 16, 'Back To Page One')
        ->linkToPagePosition(1, 72, 760, 72, 650, 180, 16, 'Back To Heading')
        ->text('Zur Einleitung Привет', TextOptions::make(
            x: 72,
            y: 620,
            embeddedFont: EmbeddedFontSource::fromPath($fontPath),
            link: \Kalle\Pdf\Page\LinkTarget::namedDestination('intro'),
        ))
        ->build();
}

function createPdfA2uLinkAnnotationFixture(): Document
{
    $fontPath = regressionFontPath();

    return regressionBuilder('PDF/A-2u Link Annotation Regression', 'PDF/A-2u link annotation regression fixture')
        ->text('PDF/A-2u Link Regression Привет', TextOptions::make(
            x: 72,
            y: 760,
            fontSize: 18,
            embeddedFont: EmbeddedFontSource::fromPath($fontPath),
            color: Color::rgb(0.08, 0.16, 0.35),
        ))
        ->text('Weitere Infos im Archivprofil. Подробнее.', TextOptions::make(
            x: 72,
            y: 724,
            width: 420,
            fontSize: 11,
            lineHeight: 15,
            embeddedFont: EmbeddedFontSource::fromPath($fontPath),
        ))
        ->link('https://example.com/spec', 72, 670, 180, 16, 'Specification Link')
        ->build();
}

function createPdfA2uTextAnnotationFixture(): Document
{
    $fontPath = regressionFontPath();

    return regressionBuilder('PDF/A-2u Text Annotation Regression', 'PDF/A-2u text annotation regression fixture')
        ->text('PDF/A-2u Kommentar Regression Привет', TextOptions::make(
            x: 72,
            y: 760,
            fontSize: 18,
            embeddedFont: EmbeddedFontSource::fromPath($fontPath),
            color: Color::rgb(0.08, 0.16, 0.35),
        ))
        ->textAnnotation(72, 680, 18, 18, 'Kommentar', 'QA', 'Comment', true)
        ->build();
}

function createPdfA2uHighlightAnnotationFixture(): Document
{
    $fontPath = regressionFontPath();

    return regressionBuilder('PDF/A-2u Highlight Annotation Regression', 'PDF/A-2u highlight annotation regression fixture')
        ->text('PDF/A-2u Highlight Regression Привет', TextOptions::make(
            x: 72,
            y: 760,
            fontSize: 18,
            embeddedFont: EmbeddedFontSource::fromPath($fontPath),
            color: Color::rgb(0.08, 0.16, 0.35),
        ))
        ->highlightAnnotation(72, 680, 140, 12, Color::rgb(1, 1, 0), 'Markiert', 'QA')
        ->build();
}

function createPdfA2uFreeTextAnnotationFixture(): Document
{
    $fontPath = regressionFontPath();

    return regressionBuilder('PDF/A-2u FreeText Annotation Regression', 'PDF/A-2u free text annotation regression fixture')
        ->freeTextAnnotation(
            'Kommentar Привет',
            72,
            680,
            180,
            40,
            TextOptions::make(
                fontSize: 12,
                embeddedFont: EmbeddedFontSource::fromPath($fontPath),
                color: Color::rgb(0, 0, 0.4),
            ),
            Color::rgb(0.2, 0.2, 0.2),
            Color::rgb(1, 1, 0.8),
            'QA',
        )
        ->build();
}

function regressionBuilder(string $title, string $subject): DefaultDocumentBuilder
{
    return DefaultDocumentBuilder::make()
        ->profile(Profile::pdfA2u())
        ->title($title)
        ->author('kalle/pdf2')
        ->subject($subject)
        ->language('de-DE')
        ->creator('Regression Fixture')
        ->creatorTool('bin/generate-pdfa2u-regression-fixtures.php');
}

function regressionFontPath(): string
{
    $path = dirname(__DIR__) . '/assets/fonts/noto-sans/NotoSans-Regular.ttf';

    if (!is_file($path)) {
        throw new RuntimeException(sprintf('Required regression font not found: %s', $path));
    }

    return $path;
}
