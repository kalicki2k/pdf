#!/usr/bin/env php
<?php

declare(strict_types=1);

use Kalle\Pdf\Document\Document;
use Kalle\Pdf\Document\DocumentRenderer;
use Kalle\Pdf\Document\OptionalContentConfiguration;
use Kalle\Pdf\Document\Profile;
use Kalle\Pdf\Page\OptionalContentGroup;
use Kalle\Pdf\Page\Page;
use Kalle\Pdf\Page\PageSize;
use Kalle\Pdf\Page\RichMediaAnnotation;
use Kalle\Pdf\Page\RichMediaAssetType;
use Kalle\Pdf\Page\RichMediaPresentationStyle;
use Kalle\Pdf\Page\ThreeDAnnotation;
use Kalle\Pdf\Page\ThreeDAssetType;
use Kalle\Pdf\Page\ThreeDViewPreset;
use Kalle\Pdf\Document\Attachment\EmbeddedFile;
use Kalle\Pdf\Writer\FileOutput;

require dirname(__DIR__) . '/vendor/autoload.php';

if ($argc !== 2) {
    fwrite(STDERR, "Usage: bin/generate-pdfa4e-regression-fixtures.php <output-dir>\n");
    exit(1);
}

$outputDir = $argv[1];

if (!is_dir($outputDir) && !mkdir($outputDir, 0777, true) && !is_dir($outputDir)) {
    fwrite(STDERR, sprintf("Could not create output directory: %s\n", $outputDir));
    exit(1);
}

$fixtures = [
    $outputDir . '/pdf-a-4e-optional-content.pdf' => createPdfA4eOptionalContentFixture(),
    $outputDir . '/pdf-a-4e-richmedia-windowed.pdf' => createPdfA4eRichMediaWindowedFixture(),
    $outputDir . '/pdf-a-4e-3d-exploded.pdf' => createPdfA4eThreeDExplodedFixture(),
];

$renderer = new DocumentRenderer();

foreach ($fixtures as $path => $document) {
    $output = new FileOutput($path);
    $renderer->write($document, $output);
    $output->close();
    fwrite(STDOUT, $path . PHP_EOL);
}

function createPdfA4eOptionalContentFixture(): Document
{
    return new Document(
        profile: Profile::pdfA4e(),
        title: 'PDF/A-4e Optional Content Regression',
        author: 'kalle/pdf2',
        subject: 'PDF/A-4e optional content regression fixture',
        language: 'de-DE',
        creator: 'Regression Fixture',
        creatorTool: 'bin/generate-pdfa4e-regression-fixtures.php',
        pages: [
            new Page(
                PageSize::A4(),
                contents: "/OC /LayerA BDC\nEMC",
                optionalContentGroups: [
                    'LayerA' => new OptionalContentGroup('Base Geometry'),
                    'LayerB' => new OptionalContentGroup('Dimensions', visible: false),
                ],
            ),
        ],
        optionalContentConfigurations: [
            new OptionalContentConfiguration(
                'Exploded View',
                ['LayerA', 'LayerB'],
                initialOn: ['LayerA'],
                initialOff: ['LayerB'],
            ),
        ],
    );
}

function createPdfA4eRichMediaWindowedFixture(): Document
{
    return new Document(
        profile: Profile::pdfA4e(),
        title: 'PDF/A-4e RichMedia Regression',
        author: 'kalle/pdf2',
        subject: 'PDF/A-4e windowed RichMedia regression fixture',
        language: 'de-DE',
        creator: 'Regression Fixture',
        creatorTool: 'bin/generate-pdfa4e-regression-fixtures.php',
        pages: [
            new Page(
                PageSize::A4(),
                annotations: [
                    new RichMediaAnnotation(
                        40,
                        500,
                        160,
                        90,
                        'demo.mp4',
                        new EmbeddedFile('demo-video', 'video/mp4'),
                        RichMediaAssetType::VIDEO,
                        'Demo video',
                        null,
                        RichMediaPresentationStyle::WINDOWED,
                    ),
                ],
            ),
        ],
    );
}

function createPdfA4eThreeDExplodedFixture(): Document
{
    return new Document(
        profile: Profile::pdfA4e(),
        title: 'PDF/A-4e 3D Regression',
        author: 'kalle/pdf2',
        subject: 'PDF/A-4e exploded 3D regression fixture',
        language: 'de-DE',
        creator: 'Regression Fixture',
        creatorTool: 'bin/generate-pdfa4e-regression-fixtures.php',
        pages: [
            new Page(
                PageSize::A4(),
                annotations: [
                    new ThreeDAnnotation(
                        40,
                        500,
                        160,
                        90,
                        'u3d-data',
                        ThreeDAssetType::U3D,
                        '3D model',
                        null,
                        ThreeDViewPreset::EXPLODED,
                    ),
                ],
            ),
        ],
    );
}
