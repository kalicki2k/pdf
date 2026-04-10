<?php

declare(strict_types=1);

use Kalle\Pdf\Document;
use Kalle\Pdf\Layout\Geometry\Position;
use Kalle\Pdf\Layout\Page\PageSize;
use Kalle\Pdf\Profile\Profile;

require __DIR__ . '/../vendor/autoload.php';

$outputDir = __DIR__ . '/../var/examples';

if (!is_dir($outputDir) && !mkdir($outputDir, 0777, true) && !is_dir($outputDir)) {
    throw new RuntimeException(sprintf('Unable to create output directory "%s".', $outputDir));
}

$targetPath = $outputDir . '/stream-to-file.pdf';

$document = new Document(
    profile: Profile::pdf14(),
    title: 'Stream to file example',
    author: 'kalle/pdf',
    subject: 'Streaming PDF output into a file handle',
    language: 'en-US',
    creator: 'Example script',
    creatorTool: 'examples/stream-to-file.php',
);

$document->registerFont('Helvetica');

$page = $document->addPage(PageSize::A4());
$page->addText('PDF stream example', new Position(20, 800), 'Helvetica', 18);
$page->addText('This PDF was written through Document::writeToStream().', new Position(20, 780), 'Helvetica', 11);
$page->addText(sprintf('Target file: %s', basename($targetPath)), new Position(20, 760), 'Helvetica', 11);

$stream = fopen($targetPath, 'wb');

if ($stream === false) {
    throw new RuntimeException(sprintf('Unable to open "%s" for writing.', $targetPath));
}

try {
    // Writes directly into the file resource instead of returning the full PDF as a string.
    $document->writeToStream($stream);
} finally {
    fclose($stream);
}

printf("PDF written to %s\n", $targetPath);
