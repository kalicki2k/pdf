#!/usr/bin/env php
<?php

declare(strict_types=1);

use Kalle\Pdf\Document;
use Kalle\Pdf\Document\Geometry\Position;
use Kalle\Pdf\Document\Geometry\Rect;
use Kalle\Pdf\Document\Page as InternalPage;
use Kalle\Pdf\Graphics\Color;
use Kalle\Pdf\Layout\PageSize;
use Kalle\Pdf\Page;
use Kalle\Pdf\Profile;

require dirname(__DIR__) . '/vendor/autoload.php';

if ($argc !== 2) {
    fwrite(STDERR, "Usage: bin/generate-pdfa-regression-fixtures.php <output-dir>\n");
    exit(1);
}

$outputDir = $argv[1];

if (!is_dir($outputDir) && !mkdir($outputDir, 0777, true) && !is_dir($outputDir)) {
    fwrite(STDERR, "Could not create output directory: $outputDir\n");
    exit(1);
}

$fixtures = [
    $outputDir . '/pdf-a-2u-minimal.pdf' => createMinimalPdfA2uFixture(...),
    $outputDir . '/pdf-a-2u-popup.pdf' => createPdfA2uPopupFixture(...),
    $outputDir . '/pdf-a-2u-annotation-batch.pdf' => createPdfA2uAnnotationBatchFixture(...),
];

foreach ($fixtures as $path => $createFixture) {
    file_put_contents($path, $createFixture()->render());
    fwrite(STDOUT, $path . PHP_EOL);
}

function createMinimalPdfA2uFixture(): Document
{
    $document = createPdfA2uDocument('PDF/A-2u Minimal Regression');
    $page = $document->addPage(PageSize::custom(120, 120));
    $page->addText('ÄÖÜ äöü ß EUR', new Position(10, 60), 'NotoSans-Regular', 12);

    return $document;
}

function createPdfA2uPopupFixture(): Document
{
    $document = createPdfA2uDocument('PDF/A-2u Popup Regression');
    $page = $document->addPage(PageSize::custom(100, 100));
    $page->addText('Hallo Popup', new Position(10, 70), 'NotoSans-Regular', 12);
    $page->addTextAnnotation(new Rect(10, 20, 10, 10), 'Kommentar', 'QA');

    $popupParent = internalPage($page)->getAnnotations()[0];
    $page->addPopupAnnotation($popupParent, new Rect(25, 20, 30, 20), true);

    return $document;
}

function createPdfA2uAnnotationBatchFixture(): Document
{
    $document = createPdfA2uDocument('PDF/A-2u Annotation Batch Regression');
    $page = $document->addPage(PageSize::custom(200, 120));
    $page->addText('Hallo Rest', new Position(10, 100), 'NotoSans-Regular', 12);
    $page->addStampAnnotation(new Rect(10, 80, 20, 10), 'Approved', Color::rgb(0, 128, 0), 'Freigegeben', 'QA');
    $page->addSquareAnnotation(new Rect(35, 75, 20, 20), Color::rgb(255, 0, 0), Color::gray(0.9), 'Kasten', 'QA');
    $page->addCircleAnnotation(new Rect(60, 75, 20, 20), Color::rgb(0, 0, 255), Color::gray(0.9), 'Kreis', 'QA');
    $page->addInkAnnotation(new Rect(85, 75, 20, 20), [[[85.0, 75.0], [95.0, 85.0]]], Color::rgb(0, 0, 0), 'Ink', 'QA');
    $page->addLineAnnotation(new Position(110, 75), new Position(140, 95), Color::rgb(0, 0, 0), 'Linie', 'QA');
    $page->addPolyLineAnnotation([[145, 75], [155, 95], [165, 80]], Color::rgb(0, 0, 255), 'PolyLine', 'QA');
    $page->addPolygonAnnotation([[170, 75], [180, 95], [190, 80]], Color::rgb(255, 0, 0), Color::gray(0.9), 'Polygon', 'QA');
    $page->addCaretAnnotation(new Rect(10, 55, 10, 10), 'Einfuegen', 'QA', 'P');

    return $document;
}

function createPdfA2uDocument(string $title): Document
{
    $document = new Document(
        profile: Profile::pdfA2u(),
        title: $title,
        language: 'de-DE',
        fontConfig: [[
            'baseFont' => 'NotoSans-Regular',
            'path' => dirname(__DIR__) . '/assets/fonts/NotoSans-Regular.ttf',
            'unicode' => true,
            'subtype' => 'CIDFontType2',
            'encoding' => 'Identity-H',
        ]],
    );
    $document->registerFont('NotoSans-Regular');

    return $document;
}

function internalPage(Page $page): InternalPage
{
    $property = new ReflectionProperty($page, 'page');

    /** @var InternalPage $internalPage */
    $internalPage = $property->getValue($page);

    return $internalPage;
}
