#!/usr/bin/env php
<?php

declare(strict_types=1);

use Kalle\Pdf\Color\Color;
use Kalle\Pdf\Document\Attachment\AssociatedFileRelationship;
use Kalle\Pdf\Document\DefaultDocumentBuilder;
use Kalle\Pdf\Document\Document;
use Kalle\Pdf\Document\DocumentRenderer;
use Kalle\Pdf\Document\Profile;
use Kalle\Pdf\Document\TaggedPdf\TaggedStructureTag;
use Kalle\Pdf\Font\EmbeddedFontSource;
use Kalle\Pdf\Text\TextOptions;
use Kalle\Pdf\Writer\FileOutput;

require dirname(__DIR__) . '/vendor/autoload.php';

if ($argc !== 2) {
    fwrite(STDERR, "Usage: bin/generate-pdfa3a-regression-fixtures.php <output-dir>\n");
    exit(1);
}

$outputDir = $argv[1];

if (!is_dir($outputDir) && !mkdir($outputDir, 0777, true) && !is_dir($outputDir)) {
    fwrite(STDERR, sprintf("Could not create output directory: %s\n", $outputDir));
    exit(1);
}

$fixtures = [
    $outputDir . '/pdf-a-3a-package.pdf' => createPdfA3aPackageFixture(),
];

$renderer = new DocumentRenderer();

foreach ($fixtures as $path => $document) {
    $output = new FileOutput($path);
    $renderer->write($document, $output);
    $output->close();
    fwrite(STDOUT, $path . PHP_EOL);
}

function createPdfA3aPackageFixture(): Document
{
    return DefaultDocumentBuilder::make()
        ->profile(Profile::pdfA3a())
        ->title('PDF/A-3a Package Regression')
        ->author('kalle/pdf2')
        ->subject('PDF/A-3a package regression fixture')
        ->language('de-DE')
        ->creator('Regression Fixture')
        ->creatorTool('bin/generate-pdfa3a-regression-fixtures.php')
        ->text('PDF/A-3a Package Привет', TextOptions::make(
            x: 72,
            y: 760,
            fontSize: 18,
            embeddedFont: EmbeddedFontSource::fromPath(regressionFontPath()),
            color: Color::rgb(0.08, 0.16, 0.35),
            tag: TaggedStructureTag::P,
        ))
        ->text('Getaggter Absatz mit zugeordnetem Datenpaket. Привет.', TextOptions::make(
            x: 72,
            y: 724,
            width: 360,
            lineHeight: 15,
            embeddedFont: EmbeddedFontSource::fromPath(regressionFontPath()),
            tag: TaggedStructureTag::P,
        ))
        ->attachment(
            'data.xml',
            '<root/>',
            'Source data',
            'application/xml',
            AssociatedFileRelationship::SOURCE,
        )
        ->build();
}

function regressionFontPath(): string
{
    $path = dirname(__DIR__) . '/assets/fonts/noto-sans/NotoSans-Regular.ttf';

    if (!is_file($path)) {
        throw new RuntimeException(sprintf('Required regression font not found: %s', $path));
    }

    return $path;
}
