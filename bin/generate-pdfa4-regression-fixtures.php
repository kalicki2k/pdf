#!/usr/bin/env php
<?php

declare(strict_types=1);

use Kalle\Pdf\Document\Document;
use Kalle\Pdf\Document\DocumentRenderer;
use Kalle\Pdf\Document\Profile;
use Kalle\Pdf\Writer\FileOutput;

require dirname(__DIR__) . '/vendor/autoload.php';

if ($argc !== 2) {
    fwrite(STDERR, "Usage: bin/generate-pdfa4-regression-fixtures.php <output-dir>\n");
    exit(1);
}

$outputDir = $argv[1];

if (!is_dir($outputDir) && !mkdir($outputDir, 0777, true) && !is_dir($outputDir)) {
    fwrite(STDERR, sprintf("Could not create output directory: %s\n", $outputDir));
    exit(1);
}

$fixtures = [
    $outputDir . '/pdf-a-4-minimal.pdf' => createPdfA4MinimalFixture(),
];

$renderer = new DocumentRenderer();

foreach ($fixtures as $path => $document) {
    $output = new FileOutput($path);
    $renderer->write($document, $output);
    $output->close();
    fwrite(STDOUT, $path . PHP_EOL);
}

function createPdfA4MinimalFixture(): Document
{
    return new Document(
        profile: Profile::pdfA4(),
        title: 'PDF/A-4 Minimal Regression',
        author: 'kalle/pdf2',
        subject: 'Minimal PDF/A-4 regression fixture',
        language: 'de-DE',
        creator: 'Regression Fixture',
        creatorTool: 'bin/generate-pdfa4-regression-fixtures.php',
    );
}
