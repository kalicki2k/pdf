<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Kalle\Pdf\Color\Color;
use Kalle\Pdf\Drawing\Units;
use Kalle\Pdf\Page\PageOptions;
use Kalle\Pdf\Page\PageSize;
use Kalle\Pdf\Pdf;
use Kalle\Pdf\Text\TextOptions;

$outputDirectory = __DIR__ . '/../var/examples';

if (!is_dir($outputDirectory) && !mkdir($outputDirectory, 0777, true) && !is_dir($outputDirectory)) {
    throw new RuntimeException('Unable to create example output directory.');
}

Pdf::document()
    ->title('Background Example')
    ->author('Kalle PDF')
    ->subject('Page background colors')
    ->creator('examples/background.php')
    ->creatorTool('pdf2')
    ->pageSize(PageSize::A4())
    ->text('Page 1: default white background', TextOptions::make(
        left: Units::mm(20),
        bottom: Units::mm(270),
        fontSize: 18,
        fontName: 'Helvetica',
    ))
    ->newPage(new PageOptions(
        pageSize: PageSize::A4(),
        backgroundColor: Color::hex('#f5f5f5'),
    ))
    ->text('Page 2: light gray background', TextOptions::make(
        left: Units::mm(20),
        bottom: Units::mm(270),
        fontSize: 18,
        fontName: 'Helvetica',
    ))
    ->text('Backgrounds are rendered as a full-page rectangle before text.', TextOptions::make(
        left: Units::mm(20),
        bottom: Units::mm(255),
        fontSize: 12,
        fontName: 'Courier',
    ))
    ->writeToFile($outputDirectory . '/background.pdf');
