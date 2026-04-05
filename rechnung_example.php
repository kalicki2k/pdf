<?php

use Kalle\Pdf\Document\Document;
use Kalle\Pdf\Layout\PageSize;
use Kalle\Pdf\Layout\Units;

require 'vendor/autoload.php';


$outputDir = __DIR__ . '/var/examples';

if (!is_dir($outputDir) && !mkdir($outputDir, 0777, true) && !is_dir($outputDir)) {
    throw new RuntimeException(sprintf('Unable to create output directory "%s".', $outputDir));
}

$startedAt = microtime(true);

$document = new Document(
    version: 1.0,
    title: 'Rechnung',
)
    ->addKeyword('Rechnung')
    ->addFont('Helvetica');

$page = $document->addPage(PageSize::A4());

$page->addText(
    text: 'Rechnung',
    x: Units::mm(20),
    y: Units::mm(20),
    baseFont: 'Helvetica',
    size: 20
);

$targetPath = $outputDir . '/' . 'rechnung_' . new DateTime()->format('Y-m-d-H-i-s') . '.pdf';
file_put_contents($targetPath, $document->render());


printf(
    'Erstellt in %.3f Sekunden.%s',
    microtime(true) - $startedAt,
    PHP_EOL,
);
