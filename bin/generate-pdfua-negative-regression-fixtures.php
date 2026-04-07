#!/usr/bin/env php
<?php

declare(strict_types=1);

use Kalle\Pdf\Document;
use Kalle\Pdf\Document\Form\FormFieldLabel;
use Kalle\Pdf\Document\Geometry\Position;
use Kalle\Pdf\Document\Geometry\Rect;
use Kalle\Pdf\Document\ImageOptions;
use Kalle\Pdf\Document\Text\ListOptions;
use Kalle\Pdf\Document\Text\StructureTag;
use Kalle\Pdf\Document\Text\TextOptions;
use Kalle\Pdf\Element\Image;
use Kalle\Pdf\Layout\PageSize;
use Kalle\Pdf\Profile;

require dirname(__DIR__) . '/vendor/autoload.php';

if ($argc !== 2) {
    fwrite(STDERR, "Usage: bin/generate-pdfua-negative-regression-fixtures.php <output-dir>\n");
    exit(1);
}

$outputDir = $argv[1];

if (!is_dir($outputDir) && !mkdir($outputDir, 0777, true) && !is_dir($outputDir)) {
    fwrite(STDERR, "Could not create output directory: $outputDir\n");
    exit(1);
}

$fixtures = [
    $outputDir . '/pdf-ua-1-negative-missing-language.pdf' => fn (): string => mutatePdf(
        createPdfUaBaselineFixture()->render(),
        [
            ...blankPdfLiteral('/Lang ', 'en-US'),
        ],
    ),
    $outputDir . '/pdf-ua-1-negative-missing-display-title.pdf' => fn (): string => mutatePdf(
        createPdfUaBaselineFixture()->render(),
        [
            '/DisplayDocTitle true' => '/DisplayDocTitle null',
        ],
    ),
    $outputDir . '/pdf-ua-1-negative-figure-without-alt.pdf' => fn (): string => mutatePdf(
        createPdfUaBaselineFixture()->render(),
        [
            '/Alt (Negative regression marker image)' => '/AIt (Negative regression marker image)',
        ],
    ),
    $outputDir . '/pdf-ua-1-negative-standalone-link-without-structure.pdf' => fn (): string => mutatePdf(
        createPdfUaLinksFixture()->render(),
        [
            '/StructParent 1' => '/StrvctParent 1',
        ],
    ),
    $outputDir . '/pdf-ua-1-negative-form-field-without-structure.pdf' => fn (): string => mutatePdf(
        createPdfUaFormsFixture()->render(),
        [
            '/StructParent 1' => '/StrvctParent 1',
        ],
    ),
    $outputDir . '/pdf-ua-1-negative-form-page-without-tab-order.pdf' => fn (): string => mutatePdf(
        createPdfUaFormsFixture()->render(),
        [
            '/Tabs /S' => '/Tabx /S',
        ],
    ),
    $outputDir . '/pdf-ua-1-negative-annotation-without-structure.pdf' => fn (): string => mutatePdf(
        createPdfUaAnnotationsFixture()->render(),
        [
            '/StructParent 1' => '/StrvctParent 1',
        ],
    ),
    $outputDir . '/pdf-ua-1-negative-table-header-without-scope.pdf' => fn (): string => mutatePdf(
        createPdfUaLayoutFixture()->render(),
        [
            '/Scope /Column' => '/Sc0pe /Column',
        ],
    ),
    $outputDir . '/pdf-ua-1-negative-list-without-label-tag.pdf' => fn (): string => mutatePdf(
        createPdfUaListFixture()->render(),
        [
            '/S /Lbl' => '/S /Lzl',
        ],
    ),
];

foreach ($fixtures as $path => $createFixture) {
    file_put_contents($path, $createFixture());
    fwrite(STDOUT, $path . PHP_EOL);
}

function createPdfUaBaselineFixture(): Document
{
    $document = createPdfUaDocument('PDF/UA-1 Negative Baseline', 'Baseline source for PDF/UA negative regression cases');
    $page = $document->addPage(PageSize::custom(180, 180));

    $page->addText(
        'Negative Baseline',
        new Position(12, 160),
        'NotoSans-Bold',
        14,
        new TextOptions(structureTag: StructureTag::Heading1),
    );
    $page->addText(
        'This valid source fixture is mutated after rendering so veraPDF sees a focused PDF/UA failure.',
        new Position(12, 142),
        'NotoSans-Regular',
        10,
        new TextOptions(structureTag: StructureTag::Paragraph),
    );
    $page->addImage(
        new Image(1, 1, 'DeviceGray', 'FlateDecode', "\x00"),
        new Position(144, 126),
        12,
        12,
        new ImageOptions(
            structureTag: StructureTag::Figure,
            altText: 'Negative regression marker image',
        ),
    );

    return $document;
}

function createPdfUaLinksFixture(): Document
{
    $document = createPdfUaDocument('PDF/UA-1 Negative Links', 'Source for PDF/UA negative standalone link validation');
    $page = $document->addPage(PageSize::custom(180, 180));

    $page->addText(
        'Negative Links',
        new Position(12, 160),
        'NotoSans-Bold',
        14,
        new TextOptions(structureTag: StructureTag::Heading1),
    );
    $page->addText(
        'The standalone link below is valid before the negative mutation removes its accessible description.',
        new Position(12, 142),
        'NotoSans-Regular',
        10,
        new TextOptions(structureTag: StructureTag::Paragraph),
    );
    $page->addLink(
        new Rect(12, 116, 82, 14),
        'https://example.com/guide',
        'Open standalone guide',
    );

    return $document;
}

function createPdfUaFormsFixture(): Document
{
    $document = createPdfUaDocument('PDF/UA-1 Negative Forms', 'Source for PDF/UA negative form widget validation');
    $page = $document->addPage(PageSize::custom(200, 180));

    $page->addText(
        'Negative Forms',
        new Position(12, 160),
        'NotoSans-Bold',
        14,
        new TextOptions(structureTag: StructureTag::Heading1),
    );
    $page->addTextField(
        'customer_name',
        new Rect(12, 112, 90, 16),
        'Ada Lovelace',
        'NotoSans-Regular',
        11,
        accessibleName: 'Customer name',
        fieldLabel: new FormFieldLabel('Customer name', new Position(12, 132), 'NotoSans-Regular', 10),
    );

    return $document;
}

function createPdfUaAnnotationsFixture(): Document
{
    $document = createPdfUaDocument('PDF/UA-1 Negative Annotations', 'Source for PDF/UA negative annotation validation');
    $page = $document->addPage(PageSize::custom(200, 180));

    $page->addText(
        'Negative Annotations',
        new Position(12, 160),
        'NotoSans-Bold',
        14,
        new TextOptions(structureTag: StructureTag::Heading1),
    );
    $page->addTextAnnotation(new Rect(12, 128, 12, 12), 'Review note', 'QA');

    return $document;
}

function createPdfUaLayoutFixture(): Document
{
    $document = createPdfUaDocument('PDF/UA-1 Negative Tables', 'Source for PDF/UA negative table validation');
    $page = $document->addPage(PageSize::custom(220, 180));

    $page->addText(
        'Negative Tables',
        new Position(12, 160),
        'NotoSans-Bold',
        14,
        new TextOptions(structureTag: StructureTag::Heading1),
    );

    $table = $page->createTable(new Position(12, 120), 120, [120]);
    $table
        ->font('NotoSans-Regular', 10)
        ->addRow(['Area'], header: true)
        ->addRow(['Valid before mutation']);

    return $document;
}

function createPdfUaListFixture(): Document
{
    $document = createPdfUaDocument('PDF/UA-1 Negative Lists', 'Source for PDF/UA negative list validation');
    $page = $document->addPage(PageSize::custom(200, 180));

    $page->addText(
        'Negative Lists',
        new Position(12, 160),
        'NotoSans-Bold',
        14,
        new TextOptions(structureTag: StructureTag::Heading1),
    );

    $page->createTextFrame(new Position(12, 140), 150, 80)
        ->addBulletList(
            [
                'First list entry',
            ],
            'NotoSans-Regular',
            10,
            options: new ListOptions(
                structureTag: StructureTag::List,
            ),
        );

    return $document;
}

/**
 * @param array<string, string> $replacements
 */
function mutatePdf(string $pdf, array $replacements): string
{
    foreach ($replacements as $search => $replace) {
        if (strlen($search) !== strlen($replace)) {
            throw new RuntimeException(sprintf(
                'Replacement length mismatch for "%s": %d !== %d.',
                $search,
                strlen($search),
                strlen($replace),
            ));
        }

        $count = 0;
        $pdf = str_replace($search, $replace, $pdf, $count);

        if ($count !== 1) {
            throw new RuntimeException(sprintf(
                'Expected exactly one replacement for "%s", got %d.',
                $search,
                $count,
            ));
        }
    }

    return $pdf;
}

/**
 * @return array<string, string>
 */
function blankPdfLiteral(string $prefix, string $value): array
{
    return [
        $prefix . '(' . $value . ')' => $prefix . '(' . str_repeat(' ', strlen($value)) . ')',
    ];
}

function createPdfUaDocument(string $title, string $subject): Document
{
    $document = new Document(
        profile: Profile::pdfUa1(),
        title: $title,
        author: 'kalle/pdf',
        subject: $subject,
        language: 'en-US',
        creator: 'Regression Fixture',
        creatorTool: 'bin/generate-pdfua-negative-regression-fixtures.php',
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
    $document->registerFont('NotoSans-Regular');
    $document->registerFont('NotoSans-Bold');

    return $document;
}
