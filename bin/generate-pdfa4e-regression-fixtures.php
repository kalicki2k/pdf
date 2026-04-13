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
