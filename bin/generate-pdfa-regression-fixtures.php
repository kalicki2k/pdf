#!/usr/bin/env php
<?php

declare(strict_types=1);

use Kalle\Pdf\Document;
use Kalle\Pdf\Document\Geometry\Position;
use Kalle\Pdf\Document\Geometry\Rect;
use Kalle\Pdf\Document\ImageOptions;
use Kalle\Pdf\Document\Page as InternalPage;
use Kalle\Pdf\Document\Text\ListOptions;
use Kalle\Pdf\Document\Text\ParagraphOptions;
use Kalle\Pdf\Document\Text\StructureTag;
use Kalle\Pdf\Document\Text\TextOptions;
use Kalle\Pdf\Element\Image;
use Kalle\Pdf\Graphics\Color;
use Kalle\Pdf\Layout\PageSize;
use Kalle\Pdf\Layout\Units;
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
    $outputDir . '/pdf-a-1a-tagged.pdf' => createPdfA1aTaggedFixture(...),
    $outputDir . '/pdf-a-1b-minimal.pdf' => createMinimalPdfA1bFixture(...),
    $outputDir . '/pdf-a-2a-tagged.pdf' => createPdfA2aTaggedFixture(...),
    $outputDir . '/pdf-a-2b-minimal.pdf' => createPdfA2bMinimalFixture(...),
    $outputDir . '/pdf-a-2u-minimal.pdf' => createMinimalPdfA2uFixture(...),
    $outputDir . '/pdf-a-2u-popup.pdf' => createPdfA2uPopupFixture(...),
    $outputDir . '/pdf-a-2u-annotation-batch.pdf' => createPdfA2uAnnotationBatchFixture(...),
    $outputDir . '/pdf-a-3a-tagged-attachment.pdf' => createPdfA3aTaggedAttachmentFixture(...),
    $outputDir . '/pdf-a-3b-attachment.pdf' => createPdfA3bAttachmentFixture(...),
    $outputDir . '/pdf-a-3u-attachment.pdf' => createPdfA3uAttachmentFixture(...),
    $outputDir . '/pdf-a-4-minimal.pdf' => createPdfA4MinimalFixture(...),
    $outputDir . '/pdf-a-4e-minimal.pdf' => createPdfA4eMinimalFixture(...),
    $outputDir . '/pdf-a-4f-attachment.pdf' => createPdfA4fAttachmentFixture(...),
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

function createMinimalPdfA1bFixture(): Document
{
    $document = new Document(
        profile: Profile::pdfA1b(),
        title: 'PDF/A-1b Minimal Regression',
        author: 'kalle/pdf',
        subject: 'Minimal PDF/A-1b regression fixture',
        language: 'de-DE',
        creator: 'Regression Fixture',
        creatorTool: 'bin/generate-pdfa-regression-fixtures.php',
        fontConfig: [[
            'baseFont' => 'NotoSans-Regular',
            'path' => dirname(__DIR__) . '/assets/fonts/NotoSans-Regular.ttf',
            'unicode' => true,
            'subtype' => 'CIDFontType2',
            'encoding' => 'Identity-H',
        ]],
    );
    $document
        ->registerFont('NotoSans-Regular')
        ->addKeyword('PDF/A')
        ->addKeyword('PDF/A-1b')
        ->addKeyword('Regression');
    $page = $document->addPage(PageSize::A4());
    $page->addText(
        'PDF/A-1b Regression',
        new Position(Units::mm(20), Units::mm(270)),
        'NotoSans-Regular',
        18,
        new TextOptions(color: Color::rgb(20, 40, 90)),
    );
    $page->createTextFrame(
        new Position(Units::mm(20), Units::mm(245)),
        Units::mm(170),
        Units::mm(20),
    )
        ->addParagraph(
            'Dieses Fixture bleibt nahe am bestehenden PDF/A-1b-Beispiel und sichert den validen Grundpfad ab.',
            'NotoSans-Regular',
            11,
            new ParagraphOptions(
                lineHeight: Units::mm(6),
                spacingAfter: Units::mm(5),
            ),
        )
        ->addParagraph(
            'Es dient bewusst als stabiler Regression-Fall statt als maximal minimales Dokument.',
            'NotoSans-Regular',
            10,
            new ParagraphOptions(
                lineHeight: Units::mm(5),
            ),
        );

    return $document;
}

function createPdfA1aTaggedFixture(): Document
{
    $document = new Document(
        profile: Profile::pdfA1a(),
        title: 'PDF/A-1a Tagged Regression',
        author: 'kalle/pdf',
        subject: 'Representative PDF/A-1a regression fixture',
        language: 'de-DE',
        creator: 'Regression Fixture',
        creatorTool: 'bin/generate-pdfa-regression-fixtures.php',
        fontConfig: [
            [
                'baseFont' => 'NotoSans-Regular',
                'path' => dirname(__DIR__) . '/assets/fonts/NotoSans-Regular.ttf',
                'unicode' => true,
                'subtype' => 'CIDFontType2',
                'encoding' => 'Identity-H',
            ],
            [
                'baseFont' => 'NotoSans-Bold',
                'path' => dirname(__DIR__) . '/assets/fonts/NotoSans-Bold.ttf',
                'unicode' => true,
                'subtype' => 'CIDFontType2',
                'encoding' => 'Identity-H',
            ],
        ],
    );
    $document
        ->addKeyword('PDF/A')
        ->addKeyword('PDF/A-1a')
        ->addKeyword('Tagged PDF')
        ->registerFont('NotoSans-Regular')
        ->registerFont('NotoSans-Bold')
        ->addHeader(static function (Page $page, int $pageNumber): void {
            $page->addText("Archivkopf $pageNumber", new Position(Units::mm(20), Units::mm(287)), 'NotoSans-Regular', 9);
        })
        ->addFooter(static function (Page $page, int $pageNumber): void {
            $page->addText("Seite $pageNumber", new Position(Units::mm(20), Units::mm(12)), 'NotoSans-Regular', 9);
        });
    $page = $document->addPage(PageSize::A4());
    $page->addText(
        'PDF/A-1a Tagged Regression',
        new Position(Units::mm(20), Units::mm(278)),
        'NotoSans-Bold',
        18,
        new TextOptions(
            structureTag: StructureTag::Heading1,
            color: Color::rgb(20, 40, 90),
        ),
    );
    $page->createTextFrame(
        new Position(Units::mm(20), Units::mm(260)),
        Units::mm(170),
        Units::mm(190),
    )
        ->addParagraph(
            'Dieses Fixture bleibt nahe am validierten PDF/A-1a-Beispiel und sichert den getaggten Kernpfad ohne spaetere PDF-Spezialfeatures ab.',
            'NotoSans-Regular',
            11,
            new ParagraphOptions(
                structureTag: StructureTag::Paragraph,
                lineHeight: Units::mm(6),
                spacingAfter: Units::mm(6),
            ),
        )
        ->addBulletList(
            [
                'Ueberschrift und Absatz sind getaggt.',
                'Liste nutzt L, LI, Lbl und LBody.',
                'Figure ist mit Alt-Text versehen.',
            ],
            'NotoSans-Regular',
            10,
            options: new ListOptions(
                structureTag: StructureTag::List,
                lineHeight: Units::mm(5),
                spacingAfter: Units::mm(5),
                itemSpacing: Units::mm(3),
            ),
        );
    $page->addImage(
        new Image(1, 1, 'DeviceGray', 'FlateDecode', "\x00"),
        new Position(Units::mm(160), Units::mm(255)),
        Units::mm(10),
        Units::mm(10),
        new ImageOptions(
            structureTag: StructureTag::Figure,
            altText: 'Kleines Beispielbild',
        ),
    );

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

function createPdfA2aTaggedFixture(): Document
{
    $document = createTaggedPdfADocument(Profile::pdfA2a(), 'PDF/A-2a Tagged Regression');

    return $document;
}

function createPdfA2bMinimalFixture(): Document
{
    $document = createPdfADocument(Profile::pdfA2b(), 'PDF/A-2b Minimal Regression');
    $page = $document->addPage(PageSize::A4());
    $page->addText(
        'PDF/A-2b Regression',
        new Position(Units::mm(20), Units::mm(270)),
        'NotoSans-Regular',
        18,
        new TextOptions(color: Color::rgb(20, 40, 90)),
    );
    $page->createTextFrame(
        new Position(Units::mm(20), Units::mm(245)),
        Units::mm(170),
        Units::mm(20),
    )
        ->addParagraph(
            'Dieses Fixture prueft den schlanken PDF/A-2b-Kernpfad mit XMP-Kennung, OutputIntent und eingebettetem Font.',
            'NotoSans-Regular',
            11,
            new ParagraphOptions(
                lineHeight: Units::mm(6),
                spacingAfter: Units::mm(5),
            ),
        )
        ->addParagraph(
            'Es bleibt bewusst nahe am bestehenden Beispiel und bildet den stabilen B-Conformance-Pfad ab.',
            'NotoSans-Regular',
            10,
            new ParagraphOptions(
                lineHeight: Units::mm(5),
            ),
        );

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

function createPdfA3aTaggedAttachmentFixture(): Document
{
    $document = createTaggedPdfADocument(Profile::pdfA3a(), 'PDF/A-3a Tagged Attachment Regression');
    $document->addAttachment(
        'source-data.xml',
        "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<document><id>3A-REGRESSION</id><status>tagged</status></document>\n",
        'Machine-readable source',
        'application/xml',
    );

    return $document;
}

function createPdfA3bAttachmentFixture(): Document
{
    $document = createPdfADocument(Profile::pdfA3b(), 'PDF/A-3b Attachment Regression');
    $page = $document->addPage(PageSize::custom(120, 120));
    $page->addText('Archive bundle', new Position(10, 60), 'NotoSans-Regular', 12);
    $document->addAttachment('payload.txt', "payload\n", 'Regression payload', 'text/plain');

    return $document;
}

function createPdfA3uAttachmentFixture(): Document
{
    $document = createPdfADocument(Profile::pdfA3u(), 'PDF/A-3u Attachment Regression');
    $page = $document->addPage(PageSize::A4());
    $page->addText(
        'PDF/A-3u Regression',
        new Position(Units::mm(20), Units::mm(270)),
        'NotoSans-Regular',
        18,
        new TextOptions(color: Color::rgb(20, 40, 90)),
    );
    $page->createTextFrame(
        new Position(Units::mm(20), Units::mm(245)),
        Units::mm(170),
        Units::mm(20),
    )
        ->addParagraph(
            'Dieses Fixture prueft den Unicode-Pfad von PDF/A-3u mit Umlauten wie ÄÖÜ äöü ß und einem eingebetteten XML-Anhang.',
            'NotoSans-Regular',
            11,
            new ParagraphOptions(
                lineHeight: Units::mm(6),
                spacingAfter: Units::mm(5),
            ),
        )
        ->addParagraph(
            'Damit bleibt der U-Conformance-Pfad fuer Text und Attachment gemeinsam in der Regression sichtbar.',
            'NotoSans-Regular',
            10,
            new ParagraphOptions(
                lineHeight: Units::mm(5),
            ),
        );
    $document->addAttachment(
        'invoice-data.xml',
        "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<invoice><number>2026-0002</number><customer>Mueller GmbH</customer></invoice>\n",
        'Machine-readable invoice source',
        'application/xml',
    );

    return $document;
}

function createPdfA4MinimalFixture(): Document
{
    $document = createPdfADocument(Profile::pdfA4(), 'PDF/A-4 Minimal Regression');
    $page = $document->addPage(PageSize::A4());
    $page->addText(
        'PDF/A-4 Regression',
        new Position(Units::mm(20), Units::mm(270)),
        'NotoSans-Regular',
        18,
        new TextOptions(color: Color::rgb(20, 40, 90)),
    );
    $page->createTextFrame(
        new Position(Units::mm(20), Units::mm(245)),
        Units::mm(170),
        Units::mm(20),
    )
        ->addParagraph(
            'Dieses Fixture prueft den schlanken PDF/A-4-Kernpfad auf Basis von PDF 2.0 mit XMP, OutputIntent und eingebettetem Unicode-Font.',
            'NotoSans-Regular',
            11,
            new ParagraphOptions(
                lineHeight: Units::mm(6),
                spacingAfter: Units::mm(5),
            ),
        )
        ->addParagraph(
            'Der klassische Info-Dictionary-Pfad bleibt fuer PDF/A-4 bewusst aus, damit die Regression genau dieses Profilverhalten absichert.',
            'NotoSans-Regular',
            10,
            new ParagraphOptions(
                lineHeight: Units::mm(5),
            ),
        );

    return $document;
}

function createPdfA4eMinimalFixture(): Document
{
    $document = createPdfADocument(Profile::pdfA4e(), 'PDF/A-4e Minimal Regression');
    $page = $document->addPage(PageSize::A4());
    $page->addText(
        'PDF/A-4e Regression',
        new Position(Units::mm(20), Units::mm(270)),
        'NotoSans-Regular',
        18,
        new TextOptions(color: Color::rgb(20, 40, 90)),
    );
    $page->createTextFrame(
        new Position(Units::mm(20), Units::mm(245)),
        Units::mm(170),
        Units::mm(20),
    )
        ->addParagraph(
            'Dieses Fixture prueft den schlanken PDF/A-4e-Pfad mit PDF-2.0-Unterbau und eingebettetem Unicode-Font.',
            'NotoSans-Regular',
            11,
            new ParagraphOptions(
                lineHeight: Units::mm(6),
                spacingAfter: Units::mm(5),
            ),
        )
        ->addParagraph(
            'Es bleibt absichtlich nah am bestehenden Beispiel, aber klein genug fuer die Regression.',
            'NotoSans-Regular',
            10,
            new ParagraphOptions(
                lineHeight: Units::mm(5),
            ),
        );

    return $document;
}

function createPdfA4fAttachmentFixture(): Document
{
    $document = createPdfADocument(Profile::pdfA4f(), 'PDF/A-4f Attachment Regression');
    $page = $document->addPage(PageSize::custom(120, 120));
    $page->addText('Factur-X bundle', new Position(10, 60), 'NotoSans-Regular', 12);
    $document->addAttachment('invoice.xml', "<invoice id=\"demo\"/>\n", 'Invoice payload', 'application/xml');

    return $document;
}

function createPdfA2uDocument(string $title): Document
{
    return createPdfADocument(Profile::pdfA2u(), $title);
}

function createTaggedPdfADocument(Profile $profile, string $title): Document
{
    $document = createPdfADocument($profile, $title);
    $page = $document->addPage(PageSize::A4());

    $page->createTextFrame(
        new Position(Units::mm(20), Units::mm(262)),
        Units::mm(170),
        Units::mm(190),
    )
        ->addHeading(
            $title,
            'NotoSans-Regular',
            18,
            new ParagraphOptions(
                structureTag: StructureTag::Heading1,
                spacingAfter: Units::mm(6),
            ),
        )
        ->addParagraph(
            'Dieses Fixture prueft den getaggten PDF/A-A-Pfad mit Ueberschrift, Absatz und Liste.',
            'NotoSans-Regular',
            11,
            new ParagraphOptions(
                structureTag: StructureTag::Paragraph,
                lineHeight: Units::mm(6),
                spacingAfter: Units::mm(6),
            ),
        )
        ->addBulletList(
            [
                'Ueberschrift mit H1-Struktur.',
                'Absatz mit P-Struktur.',
                'Liste mit L-, LI-, Lbl- und LBody-Struktur.',
            ],
            'NotoSans-Regular',
            10,
            options: new ListOptions(
                structureTag: StructureTag::List,
                lineHeight: Units::mm(5),
                spacingAfter: Units::mm(5),
                itemSpacing: Units::mm(3),
            ),
        );

    return $document;
}

function createPdfADocument(Profile $profile, string $title): Document
{
    $document = new Document(
        profile: $profile,
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
