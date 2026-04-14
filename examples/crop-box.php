<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Kalle\Pdf\Drawing\Units;
use Kalle\Pdf\Page\PageBox;
use Kalle\Pdf\Page\PageOptions;
use Kalle\Pdf\Page\PageSize;
use Kalle\Pdf\Pdf;
use Kalle\Pdf\Text\TextOptions;

$outputDirectory = __DIR__ . '/../var/examples';

if (!is_dir($outputDirectory) && !mkdir($outputDirectory, 0777, true) && !is_dir($outputDirectory)) {
    throw new RuntimeException('Unable to create example output directory.');
}

Pdf::document()
    ->title('Page Box Example')
    ->author('Kalle PDF')
    ->subject('MediaBox, CropBox, BleedBox, TrimBox and ArtBox')
    ->creator('examples/crop-box.php')
    ->creatorTool('pdf2')
    ->pageSize(PageSize::A4())
    ->text('Page 1 uses the full MediaBox.', TextOptions::make(
        x: Units::mm(20),
        y: Units::mm(270),
        fontSize: 18,
        fontName: 'Helvetica',
    ))
    ->newPage(new PageOptions(
        pageSize: PageSize::A4(),
        cropBox: PageBox::fromPoints(
            Units::mm(10),
            Units::mm(10),
            PageSize::A4()->width() - Units::mm(10),
            PageSize::A4()->height() - Units::mm(10),
        ),
        bleedBox: PageBox::fromPoints(
            Units::mm(7),
            Units::mm(7),
            PageSize::A4()->width() - Units::mm(7),
            PageSize::A4()->height() - Units::mm(7),
        ),
        trimBox: PageBox::fromPoints(
            Units::mm(12),
            Units::mm(12),
            PageSize::A4()->width() - Units::mm(12),
            PageSize::A4()->height() - Units::mm(12),
        ),
        artBox: PageBox::fromPoints(
            Units::mm(20),
            Units::mm(20),
            PageSize::A4()->width() - Units::mm(20),
            PageSize::A4()->height() - Units::mm(20),
        ),
    ))
    ->text('Page 2 defines CropBox, BleedBox, TrimBox and ArtBox.', TextOptions::make(
        x: Units::mm(20),
        y: Units::mm(270),
        fontSize: 18,
        fontName: 'Helvetica',
    ))
    ->text('CropBox is inset by 10 mm, BleedBox by 7 mm, TrimBox by 12 mm and ArtBox by 20 mm.', TextOptions::make(
        x: Units::mm(20),
        y: Units::mm(255),
        fontSize: 12,
        fontName: 'Courier',
    ))
    ->writeToFile($outputDirectory . '/crop-box.pdf');
