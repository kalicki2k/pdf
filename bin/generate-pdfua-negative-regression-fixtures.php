#!/usr/bin/env php
<?php

declare(strict_types=1);

use Kalle\Pdf\Document\Document;
use Kalle\Pdf\Image\Image;
use Kalle\Pdf\Layout\Geometry\Position;
use Kalle\Pdf\Layout\Geometry\Rect;
use Kalle\Pdf\Layout\Page\PageSize;
use Kalle\Pdf\Layout\Text\Input\ListOptions;
use Kalle\Pdf\Layout\Text\Input\TextOptions;
use Kalle\Pdf\Page\Content\ImageOptions;
use Kalle\Pdf\Page\Form\FormFieldLabel;
use Kalle\Pdf\Profile\Profile;
use Kalle\Pdf\TaggedPdf\StructureTag;
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
        writeDocumentToString(createPdfUaBaselineFixture()),
        [
            ...blankPdfLiteral('/Lang ', 'en-US'),
        ],
    ),
    $outputDir . '/pdf-ua-1-negative-missing-parent-tree.pdf' => fn (): string => mutatePdf(
        writeDocumentToString(createPdfUaBaselineFixture()),
        [
            '/ParentTree 20 0 R' => '/ParentTrex 20 0 R',
        ],
    ),
    $outputDir . '/pdf-ua-1-negative-missing-mark-info.pdf' => fn (): string => mutatePdf(
        writeDocumentToString(createPdfUaBaselineFixture()),
        [
            '/MarkInfo << /Marked true >>' => '/MarkInfo << /Markex true >>',
        ],
    ),
    $outputDir . '/pdf-ua-1-negative-missing-display-title.pdf' => fn (): string => mutatePdf(
        writeDocumentToString(createPdfUaBaselineFixture()),
        [
            '/DisplayDocTitle true' => '/DisplayDocTitle null',
        ],
    ),
    $outputDir . '/pdf-ua-1-negative-document-tag.pdf' => fn (): string => mutatePdf(
        writeDocumentToString(createPdfUaBaselineFixture()),
        [
            '/Type /StructElem /S /Document' => '/Type /StructElem /S /Documxnt',
        ],
    ),
    $outputDir . '/pdf-ua-1-negative-figure-without-alt.pdf' => fn (): string => mutatePdf(
        writeDocumentToString(createPdfUaBaselineFixture()),
        [
            '/Alt (Negative regression marker image)' => '/AIt (Negative regression marker image)',
        ],
    ),
    $outputDir . '/pdf-ua-1-negative-standalone-link-without-structure.pdf' => fn (): string => mutatePdf(
        writeDocumentToString(createPdfUaLinksFixture()),
        [
            '/StructParent 1' => '/StrvctParent 1',
        ],
    ),
    $outputDir . '/pdf-ua-1-negative-standalone-link-tag.pdf' => fn (): string => mutatePdf(
        writeDocumentToString(createPdfUaLinksFixture()),
        [
            '/Type /StructElem /S /Link /P 21 0 R /Pg 16 0 R /Alt (Open standalone guide)' => '/Type /StructElem /S /Llnk /P 21 0 R /Pg 16 0 R /Alt (Open standalone guide)',
        ],
    ),
    $outputDir . '/pdf-ua-1-negative-form-field-without-structure.pdf' => fn (): string => mutatePdf(
        writeDocumentToString(createPdfUaFormsFixture()),
        [
            '/StructParent 1' => '/StrvctParent 1',
        ],
    ),
    $outputDir . '/pdf-ua-1-negative-form-page-without-tab-order.pdf' => fn (): string => mutatePdf(
        writeDocumentToString(createPdfUaFormsFixture()),
        [
            '/Tabs /S' => '/Tabx /S',
        ],
    ),
    $outputDir . '/pdf-ua-1-negative-page-without-struct-parents.pdf' => fn (): string => mutatePdf(
        writeDocumentToString(createPdfUaBaselineFixture()),
        [
            '/StructParents 0' => '/StructParentx 0',
        ],
    ),
    $outputDir . '/pdf-ua-1-negative-form-struct-tag.pdf' => fn (): string => mutatePdf(
        writeDocumentToString(createPdfUaFormLabelFixture()),
        [
            '/Type /StructElem /S /Form' => '/Type /StructElem /S /From',
        ],
    ),
    $outputDir . '/pdf-ua-1-negative-form-label-container-tag.pdf' => fn (): string => mutatePdf(
        writeDocumentToString(createPdfUaFormLabelFixture()),
        [
            '/Type /StructElem /S /Div' => '/Type /StructElem /S /Dlv',
        ],
    ),
    $outputDir . '/pdf-ua-1-negative-annotation-without-structure.pdf' => fn (): string => mutatePdf(
        writeDocumentToString(createPdfUaAnnotationsFixture()),
        [
            '/StructParent 1' => '/StrvctParent 1',
        ],
    ),
    $outputDir . '/pdf-ua-1-negative-annotation-tag.pdf' => fn (): string => mutatePdf(
        writeDocumentToString(createPdfUaAnnotationsFixture()),
        [
            '/Type /StructElem /S /Annot /P 21 0 R /Pg 16 0 R /Alt (Review note)' => '/Type /StructElem /S /Anotx /P 21 0 R /Pg 16 0 R /Alt (Review note)',
        ],
    ),
    $outputDir . '/pdf-ua-1-negative-table-tag.pdf' => fn (): string => mutatePdf(
        writeDocumentToString(createPdfUaLayoutFixture()),
        [
            '/Type /StructElem /S /Table /P 21 0 R /K [24 0 R 27 0 R]' => '/Type /StructElem /S /Tblex /P 21 0 R /K [24 0 R 27 0 R]',
        ],
    ),
    $outputDir . '/pdf-ua-1-negative-table-row-tag.pdf' => fn (): string => mutatePdf(
        writeDocumentToString(createPdfUaLayoutFixture()),
        [
            '/Type /StructElem /S /TR /P 23 0 R /K [25 0 R]' => '/Type /StructElem /S /Tz /P 23 0 R /K [25 0 R]',
        ],
    ),
    $outputDir . '/pdf-ua-1-negative-table-header-without-scope.pdf' => fn (): string => mutatePdf(
        writeDocumentToString(createPdfUaLayoutFixture()),
        [
            '/Scope /Column' => '/Sc0pe /Column',
        ],
    ),
    $outputDir . '/pdf-ua-1-negative-list-without-label-tag.pdf' => fn (): string => mutatePdf(
        writeDocumentToString(createPdfUaListFixture()),
        [
            '/S /Lbl' => '/S /Lzl',
        ],
    ),
];

foreach ($fixtures as $path => $createFixture) {
    file_put_contents($path, $createFixture());
    fwrite(STDOUT, $path . PHP_EOL);
}

function writeDocumentToString(Document $document): string
{
    $stream = fopen('php://temp', 'w+b');

    if ($stream === false) {
        throw new RuntimeException('Unable to open temporary stream for PDF mutation.');
    }

    try {
        $document->writeToStream($stream);

        if (rewind($stream) === false) {
            throw new RuntimeException('Unable to rewind temporary PDF stream.');
        }

        $writtenOutput = stream_get_contents($stream);

        if ($writtenOutput === false) {
            throw new RuntimeException('Unable to read temporary PDF stream.');
        }

        return $writtenOutput;
    } finally {
        fclose($stream);
    }
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

function createPdfUaFormLabelFixture(): Document
{
    $document = createPdfUaDocument('PDF/UA-1 Negative Form Labels', 'Source for PDF/UA negative form label semantics validation');
    $page = $document->addPage(PageSize::custom(200, 180));

    $page->addText(
        'Negative Form Labels',
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
        ->addHeaderRow(['Area'])
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
