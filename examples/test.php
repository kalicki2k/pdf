<?php

declare(strict_types=1);

use Kalle\Pdf\Pdf;
use Kalle\Pdf\PdfRenderer;

require dirname(__DIR__) . '/vendor/autoload.php';

$document = Pdf::document()
    ->withTitle('Minimal PDF Example')
    ->withAuthor('Kalle PDF')
    ->withCreator('examples/test.php')
    ->withPageName('cover')
    ->withPageLabel('Cover')
    ->writeText('Hello PDF', 72, 720)
    ->writeText('This file was rendered through PdfRenderer.', 72, 700)
    ->build();

$pdf = PdfRenderer::make()->render($document);

$target = dirname(__DIR__) . '/var/examples/test.pdf';
$targetDirectory = dirname($target);

if (!is_dir($targetDirectory) && !mkdir($targetDirectory, 0777, true) && !is_dir($targetDirectory)) {
    throw new RuntimeException(sprintf('Unable to create output directory "%s".', $targetDirectory));
}

file_put_contents($target, $pdf);

echo "Wrote PDF to {$target}\n";
