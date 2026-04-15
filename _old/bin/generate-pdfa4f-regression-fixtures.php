#!/usr/bin/env php
<?php

declare(strict_types=1);

use Kalle\Pdf\Document\Attachment\EmbeddedFile;
use Kalle\Pdf\Document\Attachment\FileAttachment;
use Kalle\Pdf\Document\Attachment\AssociatedFileRelationship;
use Kalle\Pdf\Document\Document;
use Kalle\Pdf\Document\DocumentRenderer;
use Kalle\Pdf\Document\Profile;
use Kalle\Pdf\Writer\FileOutput;

require dirname(__DIR__) . '/vendor/autoload.php';

if ($argc !== 2) {
    fwrite(STDERR, "Usage: bin/generate-pdfa4f-regression-fixtures.php <output-dir>\n");
    exit(1);
}

$outputDir = $argv[1];

if (!is_dir($outputDir) && !mkdir($outputDir, 0777, true) && !is_dir($outputDir)) {
    fwrite(STDERR, sprintf("Could not create output directory: %s\n", $outputDir));
    exit(1);
}

$fixtures = [
    $outputDir . '/pdf-a-4f-package.pdf' => createPdfA4fPackageFixture(),
];

$renderer = new DocumentRenderer();

foreach ($fixtures as $path => $document) {
    $output = new FileOutput($path);
    $renderer->write($document, $output);
    $output->close();
    fwrite(STDOUT, $path . PHP_EOL);
}

function createPdfA4fPackageFixture(): Document
{
    return new Document(
        profile: Profile::pdfA4f(),
        title: 'PDF/A-4f Package Regression',
        author: 'kalle/pdf2',
        subject: 'PDF/A-4f package regression fixture',
        language: 'de-DE',
        creator: 'Regression Fixture',
        creatorTool: 'bin/generate-pdfa4f-regression-fixtures.php',
        attachments: [
            new FileAttachment(
                'engineering-data.xml',
                new EmbeddedFile('<assembly id="base"/>', 'application/xml'),
                'Engineering source data',
                AssociatedFileRelationship::SOURCE,
            ),
        ],
    );
}
