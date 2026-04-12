#!/usr/bin/env php
<?php

declare(strict_types=1);

use Kalle\Pdf\Color\Color;
use Kalle\Pdf\Document\DefaultDocumentBuilder;
use Kalle\Pdf\Document\Document;
use Kalle\Pdf\Document\DocumentRenderer;
use Kalle\Pdf\Document\Metadata\PdfAOutputIntent;
use Kalle\Pdf\Document\Profile;
use Kalle\Pdf\Font\EmbeddedFontSource;
use Kalle\Pdf\Image\ImageAccessibility;
use Kalle\Pdf\Image\ImageColorSpace;
use Kalle\Pdf\Image\ImagePlacement;
use Kalle\Pdf\Image\ImageSource;
use Kalle\Pdf\Tests\Image\JpegFixture;
use Kalle\Pdf\Tests\Image\PngFixture;
use Kalle\Pdf\Text\TextOptions;
use Kalle\Pdf\Writer\FileOutput;

require dirname(__DIR__) . '/vendor/autoload.php';

if ($argc !== 2) {
    fwrite(STDERR, "Usage: bin/generate-pdfa1b-regression-fixtures.php <output-dir>\n");
    exit(1);
}

$outputDir = $argv[1];

if (!is_dir($outputDir) && !mkdir($outputDir, 0777, true) && !is_dir($outputDir)) {
    fwrite(STDERR, sprintf("Could not create output directory: %s\n", $outputDir));
    exit(1);
}

$fixtures = [
    $outputDir . '/pdf-a-1b-minimal.pdf' => createPdfA1bMinimalFixture(),
    $outputDir . '/pdf-a-1b-gray-jpeg-image.pdf' => createPdfA1bGrayJpegFixture(),
    $outputDir . '/pdf-a-1b-rgb-jpeg-image.pdf' => createPdfA1bRgbJpegFixture(),
    $outputDir . '/pdf-a-1b-cmyk-jpeg-image.pdf' => createPdfA1bCmykJpegFixture(),
    $outputDir . '/pdf-a-1b-rgb-png-image.pdf' => createPdfA1bRgbPngFixture(),
    $outputDir . '/pdf-a-1b-gray-flate-image.pdf' => createPdfA1bGrayFlateFixture(),
    $outputDir . '/pdf-a-1b-rgb-flate-image.pdf' => createPdfA1bRgbFlateFixture(),
];

$renderer = new DocumentRenderer();

foreach ($fixtures as $path => $document) {
    $output = new FileOutput($path);
    $renderer->write($document, $output);
    $output->close();
    fwrite(STDOUT, $path . PHP_EOL);
}

function createPdfA1bMinimalFixture(): Document
{
    $fontPath = regressionFontPath();

    return regressionBuilder('PDF/A-1b Minimal Regression', 'Minimal PDF/A-1b regression fixture')
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
}

function createPdfA1bGrayJpegFixture(): Document
{
    $fontPath = regressionFontPath();

    return regressionBuilder('PDF/A-1b Gray JPEG Regression', 'PDF/A-1b gray JPEG image regression fixture')
        ->text('PDF/A-1b Gray JPEG Привет', new TextOptions(
            x: 72,
            y: 760,
            fontSize: 18,
            embeddedFont: EmbeddedFontSource::fromPath($fontPath),
            color: Color::rgb(0.08, 0.16, 0.35),
        ))
        ->image(
            imageSourceFromBytes(JpegFixture::tinyGrayJpegBytes(), 'jpg'),
            ImagePlacement::at(72, 620, width: 96),
        )
        ->text('Graustufen-JPEG ohne Transparenz oder Sonderfarbraum. Привет.', new TextOptions(
            x: 72,
            y: 590,
            width: 360,
            fontSize: 11,
            lineHeight: 15,
            embeddedFont: EmbeddedFontSource::fromPath($fontPath),
        ))
        ->build();
}

function createPdfA1bRgbPngFixture(): Document
{
    $fontPath = regressionFontPath();

    return regressionBuilder('PDF/A-1b RGB PNG Regression', 'PDF/A-1b RGB PNG image regression fixture')
        ->text('PDF/A-1b RGB PNG Привет', new TextOptions(
            x: 72,
            y: 760,
            fontSize: 18,
            embeddedFont: EmbeddedFontSource::fromPath($fontPath),
            color: Color::rgb(0.08, 0.16, 0.35),
        ))
        ->image(
            imageSourceFromBytes(PngFixture::tinyRgbPngBytes(), 'png'),
            ImagePlacement::at(72, 620, width: 96),
        )
        ->text('RGB-PNG ohne Alpha, damit kein SoftMask-Pfad im PDF/A-1b-Dokument entsteht. Привет.', new TextOptions(
            x: 72,
            y: 590,
            width: 360,
            fontSize: 11,
            lineHeight: 15,
            embeddedFont: EmbeddedFontSource::fromPath($fontPath),
        ))
        ->build();
}

function createPdfA1bRgbJpegFixture(): Document
{
    $fontPath = regressionFontPath();

    return regressionBuilder('PDF/A-1b RGB JPEG Regression', 'PDF/A-1b RGB JPEG image regression fixture')
        ->text('PDF/A-1b RGB JPEG Привет', new TextOptions(
            x: 72,
            y: 760,
            fontSize: 18,
            embeddedFont: EmbeddedFontSource::fromPath($fontPath),
        ))
        ->image(
            imageSourceFromBytes(JpegFixture::tinyRgbJpegBytes(), 'jpg'),
            ImagePlacement::at(72, 620, width: 96),
            ImageAccessibility::alternativeText('RGB JPEG image'),
        )
        ->text('RGB-JPEG im DeviceRGB-Pfad mit passendem sRGB-OutputIntent. Привет.', new TextOptions(
            x: 72,
            y: 590,
            width: 360,
            fontSize: 11,
            lineHeight: 15,
            embeddedFont: EmbeddedFontSource::fromPath($fontPath),
        ))
        ->build();
}

function createPdfA1bCmykJpegFixture(): Document
{
    $fontPath = regressionFontPath();

    return regressionBuilder('PDF/A-1b CMYK JPEG Regression', 'PDF/A-1b CMYK JPEG image regression fixture')
        ->pdfaOutputIntent(PdfAOutputIntent::defaultCmyk())
        ->text('PDF/A-1b CMYK JPEG Привет', new TextOptions(
            x: 72,
            y: 760,
            fontSize: 18,
            embeddedFont: EmbeddedFontSource::fromPath($fontPath),
        ))
        ->image(
            imageSourceFromBytes(JpegFixture::tinyCmykJpegBytes(), 'jpg'),
            ImagePlacement::at(72, 620, width: 96),
            ImageAccessibility::alternativeText('CMYK JPEG image'),
        )
        ->text('CMYK-JPEG mit passendem CMYK-OutputIntent statt dem Standard-sRGB-Profil. Привет.', new TextOptions(
            x: 72,
            y: 590,
            width: 360,
            fontSize: 11,
            lineHeight: 15,
            embeddedFont: EmbeddedFontSource::fromPath($fontPath),
        ))
        ->build();
}

function createPdfA1bGrayFlateFixture(): Document
{
    $fontPath = regressionFontPath();

    return regressionBuilder('PDF/A-1b Gray Flate Regression', 'PDF/A-1b gray flate image regression fixture')
        ->text('PDF/A-1b Gray Flate Привет', new TextOptions(
            x: 72,
            y: 760,
            fontSize: 18,
            embeddedFont: EmbeddedFontSource::fromPath($fontPath),
        ))
        ->image(
            ImageSource::flate("\x66", 1, 1, ImageColorSpace::GRAY),
            ImagePlacement::at(72, 620, width: 96),
            ImageAccessibility::alternativeText('Gray flate image'),
        )
        ->text('Graues Flate-Bild ohne Transparenz und ohne Custom-Colorspace. Привет.', new TextOptions(
            x: 72,
            y: 590,
            width: 360,
            fontSize: 11,
            lineHeight: 15,
            embeddedFont: EmbeddedFontSource::fromPath($fontPath),
        ))
        ->build();
}

function createPdfA1bRgbFlateFixture(): Document
{
    $fontPath = regressionFontPath();

    return regressionBuilder('PDF/A-1b RGB Flate Regression', 'PDF/A-1b RGB flate image regression fixture')
        ->text('PDF/A-1b RGB Flate Привет', new TextOptions(
            x: 72,
            y: 760,
            fontSize: 18,
            embeddedFont: EmbeddedFontSource::fromPath($fontPath),
        ))
        ->image(
            ImageSource::flate("\xFF\x00\x00", 1, 1, ImageColorSpace::RGB),
            ImagePlacement::at(72, 620, width: 96),
            ImageAccessibility::alternativeText('RGB flate image'),
        )
        ->text('RGB-Flate-Bild im DeviceRGB-Pfad mit passendem sRGB-OutputIntent. Привет.', new TextOptions(
            x: 72,
            y: 590,
            width: 360,
            fontSize: 11,
            lineHeight: 15,
            embeddedFont: EmbeddedFontSource::fromPath($fontPath),
        ))
        ->build();
}

function regressionBuilder(string $title, string $subject): DefaultDocumentBuilder
{
    return DefaultDocumentBuilder::make()
        ->profile(Profile::pdfA1b())
        ->title($title)
        ->author('kalle/pdf2')
        ->subject($subject)
        ->language('de-DE')
        ->creator('Regression Fixture')
        ->creatorTool('bin/generate-pdfa1b-regression-fixtures.php');
}

function regressionFontPath(): string
{
    $path = dirname(__DIR__) . '/assets/fonts/noto-sans/NotoSans-Regular.ttf';

    if (!is_file($path)) {
        throw new RuntimeException(sprintf('Required regression font not found: %s', $path));
    }

    return $path;
}

function imageSourceFromBytes(string $bytes, string $extension): ImageSource
{
    $path = tempnam(sys_get_temp_dir(), 'pdf2-pdfa1b-image-');

    if ($path === false) {
        throw new RuntimeException('Unable to allocate a temporary image path.');
    }

    $typedPath = $path . '.' . $extension;

    if (!rename($path, $typedPath)) {
        @unlink($path);
        throw new RuntimeException('Unable to prepare a typed temporary image path.');
    }

    file_put_contents($typedPath, $bytes);

    try {
        return ImageSource::fromPath($typedPath);
    } finally {
        @unlink($typedPath);
    }
}
