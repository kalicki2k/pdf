#!/usr/bin/env php
<?php

declare(strict_types=1);

use Kalle\Pdf\Document\Document;
use Kalle\Pdf\Image\Image;
use Kalle\Pdf\Layout\Geometry\Position;
use Kalle\Pdf\Layout\Geometry\Rect;
use Kalle\Pdf\Layout\Page\PageSize;
use Kalle\Pdf\Layout\Page\Units;
use Kalle\Pdf\Layout\Table\Definition\TableCaption;
use Kalle\Pdf\Layout\Table\Definition\TableCell;
use Kalle\Pdf\Layout\Table\Definition\TableHeaderScope;
use Kalle\Pdf\Layout\Text\Input\ListOptions;
use Kalle\Pdf\Layout\Text\Input\ParagraphOptions;
use Kalle\Pdf\Layout\Text\Input\TextOptions;
use Kalle\Pdf\Page\Annotation\Style\AnnotationBorderStyle;
use Kalle\Pdf\Page\Annotation\Style\LineEndingStyle;
use Kalle\Pdf\Page\Content\ImageOptions;
use Kalle\Pdf\Page\Content\Style\BadgeStyle;
use Kalle\Pdf\Page\Content\Style\CalloutStyle;
use Kalle\Pdf\Page\Content\Style\PanelStyle;
use Kalle\Pdf\Page\Form\FormFieldFlags;
use Kalle\Pdf\Page\Form\FormFieldLabel;
use Kalle\Pdf\Page\Link\LinkTarget;
use Kalle\Pdf\Page\Page;
use Kalle\Pdf\Profile\Profile;
use Kalle\Pdf\Style\Color;
use Kalle\Pdf\TaggedPdf\StructureTag;

require dirname(__DIR__) . '/vendor/autoload.php';

if ($argc !== 2) {
    fwrite(STDERR, "Usage: bin/generate-pdfua-regression-fixtures.php <output-dir>\n");
    exit(1);
}

$outputDir = $argv[1];

if (!is_dir($outputDir) && !mkdir($outputDir, 0777, true) && !is_dir($outputDir)) {
    fwrite(STDERR, "Could not create output directory: $outputDir\n");
    exit(1);
}

$fixtures = [
    $outputDir . '/pdf-ua-1-minimal.pdf' => createMinimalPdfUa1Fixture(...),
    $outputDir . '/pdf-ua-1-layout.pdf' => createPdfUa1LayoutFixture(...),
    $outputDir . '/pdf-ua-1-links.pdf' => createPdfUa1LinksFixture(...),
    $outputDir . '/pdf-ua-1-forms.pdf' => createPdfUa1FormsFixture(...),
    $outputDir . '/pdf-ua-1-widget-appearances.pdf' => createPdfUa1WidgetAppearanceFixture(...),
    $outputDir . '/pdf-ua-1-widget-states.pdf' => createPdfUa1WidgetStateFixture(...),
    $outputDir . '/pdf-ua-1-annotation-batch.pdf' => createPdfUa1AnnotationBatchFixture(...),
    $outputDir . '/pdf-ua-1-table-caption-pagination.pdf' => createPdfUa1TableCaptionPaginationFixture(...),
    $outputDir . '/pdf-ua-1-table-caption-spans.pdf' => createPdfUa1TableCaptionSpansFixture(...),
    $outputDir . '/pdf-ua-1-table-span-breaks.pdf' => createPdfUa1TableSpanBreaksFixture(...),
    $outputDir . '/pdf-ua-1-table-header-matrix.pdf' => createPdfUa1TableHeaderMatrixFixture(...),
    $outputDir . '/pdf-ua-1-table-header-matrix-breaks.pdf' => createPdfUa1TableHeaderMatrixBreaksFixture(...),
    $outputDir . '/pdf-ua-1-table-narrow-columns.pdf' => createPdfUa1TableNarrowColumnFixture(...),
    $outputDir . '/pdf-ua-1-mixed.pdf' => createPdfUa1MixedFixture(...),
    $outputDir . '/pdf-ua-1-mixed-deep.pdf' => createPdfUa1DeepMixedFixture(...),
];

foreach ($fixtures as $path => $createFixture) {
    $createFixture()->writeToFile($path);
    fwrite(STDOUT, $path . PHP_EOL);
}

function createMinimalPdfUa1Fixture(): Document
{
    $document = createPdfUaDocument('PDF/UA-1 Minimal Regression', 'Representative PDF/UA-1 minimal regression fixture');
    $page = $document->addPage(PageSize::A4());

    $page->addText(
        'PDF/UA-1 Minimal Regression',
        new Position(Units::mm(20), Units::mm(278)),
        'NotoSans-Bold',
        18,
        new TextOptions(
            structureTag: StructureTag::Heading1,
            color: Color::rgb(20, 40, 90),
        ),
    );

    $page->createTextFrame(
        new Position(Units::mm(20), Units::mm(255)),
        Units::mm(170),
        Units::mm(120),
    )
        ->addParagraph(
            'This fixture validates the tagged PDF/UA-1 baseline with document metadata, language, structure tree and marked content.',
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
                'Heading and paragraph are tagged.',
                'List semantics are emitted through L, LI, Lbl and LBody.',
                'The image is tagged as Figure and carries alt text.',
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
        new Position(Units::mm(160), Units::mm(250)),
        Units::mm(10),
        Units::mm(10),
        new ImageOptions(
            structureTag: StructureTag::Figure,
            altText: 'Small decorative example image',
        ),
    );

    return $document;
}

function createPdfUa1LinksFixture(): Document
{
    $document = createPdfUaDocument('PDF/UA-1 Links Regression', 'Representative PDF/UA-1 link regression fixture');
    $page = $document->addPage(PageSize::custom(180, 180));

    $page->addText(
        'Accessible Links',
        new Position(12, 160),
        'NotoSans-Bold',
        14,
        new TextOptions(structureTag: StructureTag::Heading1),
    );
    $page->addText(
        'Open documentation',
        new Position(12, 138),
        'NotoSans-Regular',
        12,
        new TextOptions(
            structureTag: StructureTag::Paragraph,
            link: LinkTarget::externalUrl('https://example.com/docs'),
        ),
    );
    $page->addLink(new Rect(12, 114, 60, 14), 'https://example.com/guide', 'Read standalone guide');
    $page->addPanel(
        'Visible body link.',
        12,
        54,
        110,
        55,
        'Panel link',
        'NotoSans-Regular',
        new PanelStyle(),
        null,
        LinkTarget::externalUrl('https://example.com/panel'),
    );

    return $document;
}

function createPdfUa1LayoutFixture(): Document
{
    $document = createPdfUaDocument('PDF/UA-1 Layout Regression', 'Representative PDF/UA-1 layout and graphics regression fixture');
    $page = $document->addPage(PageSize::custom(364, 320));

    $page->addText(
        'Accessible Layout',
        new Position(12, 220),
        'NotoSans-Bold',
        14,
        new TextOptions(structureTag: StructureTag::Heading1),
    );
    $page->addText(
        'Decorative graphics are emitted through high-level APIs so they stay artifacts while the visible text and table content remain tagged.',
        new Position(12, 204),
        'NotoSans-Regular',
        10,
        new TextOptions(structureTag: StructureTag::Paragraph),
    );

    $page->addBadge(
        'Stable',
        new Position(12, 184),
        'NotoSans-Regular',
        10,
        new BadgeStyle(
            fillColor: Color::rgb(225, 238, 252),
            textColor: Color::rgb(20, 40, 90),
        ),
    );
    $page->addPanel(
        'Panel body',
        12,
        116,
        92,
        54,
        'Panel title',
        'NotoSans-Regular',
        new PanelStyle(),
    );
    $page->addCallout(
        'Callout body',
        118,
        116,
        92,
        54,
        108,
        110,
        'Callout title',
        'NotoSans-Regular',
        new CalloutStyle(),
    );

    $table = $page->createTable(new Position(12, 96), 194, [96, 98]);
    $table
        ->font('NotoSans-Regular', 10)
        ->addHeaderRow(['Area', 'State'])
        ->addRow(['Badge', 'Artifact background with tagged text'])
        ->addRow(['Panel', 'Artifact frame with tagged body content']);

    $page->addImage(
        new Image(1, 1, 'DeviceGray', 'FlateDecode', "\x00"),
        new Position(188, 24),
        12,
        12,
        new ImageOptions(
            structureTag: StructureTag::Figure,
            altText: 'Small layout marker image',
        ),
    );

    return $document;
}

function createPdfUa1FormsFixture(): Document
{
    $document = createPdfUaDocument('PDF/UA-1 Forms Regression', 'Representative PDF/UA-1 form regression fixture');
    $page = $document->addPage(PageSize::custom(240, 240));

    $page->addText(
        'Accessible Form Widgets',
        new Position(12, 220),
        'NotoSans-Bold',
        14,
        new TextOptions(structureTag: StructureTag::Heading1),
    );
    $page->addText(
        'All currently supported PDF/UA-1 form widget paths are exercised here.',
        new Position(12, 204),
        'NotoSans-Regular',
        10,
        new TextOptions(structureTag: StructureTag::Paragraph),
    );

    $page->addTextField(
        'customer_name',
        new Rect(12, 176, 90, 16),
        'Ada Lovelace',
        'NotoSans-Regular',
        11,
        accessibleName: 'Customer name',
        fieldLabel: new FormFieldLabel('Customer name', new Position(12, 196), 'NotoSans-Regular', 10),
    );
    $page->addCheckbox(
        'accept_terms',
        new Position(12, 150),
        12,
        true,
        'Accept terms',
        new FormFieldLabel('Accept terms', new Position(30, 152), 'NotoSans-Regular', 10),
    );
    $page->addPushButton('save_form', 'Save', new Rect(12, 126, 56, 16), 'NotoSans-Regular', 11, accessibleName: 'Save form');
    $page->addRadioButton(
        'delivery',
        'standard',
        new Position(12, 102),
        12,
        true,
        'Standard delivery',
        new FormFieldLabel('Standard delivery', new Position(30, 104), 'NotoSans-Regular', 10),
    );
    $page->addRadioButton(
        'delivery',
        'express',
        new Position(110, 102),
        12,
        false,
        'Express delivery',
        new FormFieldLabel('Express delivery', new Position(128, 104), 'NotoSans-Regular', 10),
    );
    $page->addComboBox(
        'country',
        new Rect(12, 74, 90, 16),
        ['de' => 'Germany', 'at' => 'Austria'],
        'de',
        'NotoSans-Regular',
        11,
        accessibleName: 'Country selection',
        fieldLabel: new FormFieldLabel('Country', new Position(12, 94), 'NotoSans-Regular', 10),
    );
    $page->addListBox(
        'topics',
        new Rect(12, 28, 90, 34),
        ['pdf' => 'PDF', 'forms' => 'Forms', 'tables' => 'Tables'],
        'forms',
        'NotoSans-Regular',
        11,
        accessibleName: 'Topics selection',
        fieldLabel: new FormFieldLabel('Topics', new Position(12, 66), 'NotoSans-Regular', 10),
    );
    $page->addSignatureField(
        'approval_signature',
        new Rect(124, 176, 90, 16),
        'Approval signature',
        new FormFieldLabel('Approval signature', new Position(124, 196), 'NotoSans-Regular', 10),
    );

    return $document;
}

function createPdfUa1WidgetAppearanceFixture(): Document
{
    $document = createPdfUaDocument('PDF/UA-1 Widget Appearance Regression', 'Representative PDF/UA-1 widget appearance regression fixture');
    $page = $document->addPage(PageSize::custom(240, 240));

    $page->addText(
        'Widget Appearance Rendering',
        new Position(12, 220),
        'NotoSans-Bold',
        14,
        new TextOptions(structureTag: StructureTag::Heading1),
    );
    $page->addText(
        'This fixture focuses on visible appearance streams for text, choice, button and signature widgets.',
        new Position(12, 204),
        'NotoSans-Regular',
        10,
        new TextOptions(structureTag: StructureTag::Paragraph),
    );

    $page->addTextField(
        'notes',
        new Rect(12, 158, 92, 28),
        "Ada Lovelace\nGrace Hopper",
        'NotoSans-Regular',
        11,
        true,
        accessibleName: 'Contact notes',
        fieldLabel: new FormFieldLabel('Contact notes', new Position(12, 190), 'NotoSans-Regular', 10),
    );
    $page->addComboBox(
        'country',
        new Rect(12, 116, 92, 16),
        ['de' => 'Germany', 'at' => 'Austria', 'ch' => 'Switzerland'],
        'ch',
        'NotoSans-Regular',
        11,
        accessibleName: 'Country selection',
        fieldLabel: new FormFieldLabel('Country', new Position(12, 136), 'NotoSans-Regular', 10),
    );
    $page->addListBox(
        'topics',
        new Rect(12, 54, 92, 42),
        ['pdf' => 'PDF', 'forms' => 'Forms', 'tables' => 'Tables'],
        ['pdf', 'tables'],
        'NotoSans-Regular',
        11,
        flags: new FormFieldFlags(multiSelect: true),
        accessibleName: 'Topics selection',
        fieldLabel: new FormFieldLabel('Topics', new Position(12, 100), 'NotoSans-Regular', 10),
    );
    $page->addPushButton(
        'apply_changes',
        'Apply',
        new Rect(128, 158, 84, 18),
        'NotoSans-Regular',
        11,
        accessibleName: 'Apply changes',
        fieldLabel: new FormFieldLabel('Primary action', new Position(128, 180), 'NotoSans-Regular', 10),
    );
    $page->addSignatureField(
        'approval_signature',
        new Rect(128, 116, 84, 18),
        'Approval signature',
        new FormFieldLabel('Approval signature', new Position(128, 136), 'NotoSans-Regular', 10),
    );

    return $document;
}

function createPdfUa1WidgetStateFixture(): Document
{
    $document = createPdfUaDocument('PDF/UA-1 Widget State Regression', 'Representative PDF/UA-1 widget state regression fixture');
    $page = $document->addPage(PageSize::custom(240, 240));

    $page->addText(
        'Widget State Rendering',
        new Position(12, 220),
        'NotoSans-Bold',
        14,
        new TextOptions(structureTag: StructureTag::Heading1),
    );
    $page->addText(
        'This fixture focuses on checked, unchecked, selected and multi-selected widget states with visible labels.',
        new Position(12, 204),
        'NotoSans-Regular',
        10,
        new TextOptions(structureTag: StructureTag::Paragraph),
    );

    $page->addCheckbox(
        'accepted',
        new Position(12, 174),
        12,
        true,
        'Accepted',
        new FormFieldLabel('Accepted', new Position(30, 176), 'NotoSans-Regular', 10),
    );
    $page->addCheckbox(
        'archived',
        new Position(12, 152),
        12,
        false,
        'Archived',
        new FormFieldLabel('Archived', new Position(30, 154), 'NotoSans-Regular', 10),
    );
    $page->addRadioButton(
        'delivery',
        'standard',
        new Position(12, 124),
        12,
        true,
        'Standard delivery',
        new FormFieldLabel('Standard delivery', new Position(30, 126), 'NotoSans-Regular', 10),
    );
    $page->addRadioButton(
        'delivery',
        'express',
        new Position(120, 124),
        12,
        false,
        'Express delivery',
        new FormFieldLabel('Express delivery', new Position(138, 126), 'NotoSans-Regular', 10),
    );
    $page->addComboBox(
        'country',
        new Rect(12, 84, 92, 16),
        ['de' => 'Germany', 'at' => 'Austria', 'ch' => 'Switzerland'],
        'at',
        'NotoSans-Regular',
        11,
        accessibleName: 'Country selection',
        fieldLabel: new FormFieldLabel('Country', new Position(12, 104), 'NotoSans-Regular', 10),
    );
    $page->addListBox(
        'topics',
        new Rect(120, 52, 92, 48),
        ['pdf' => 'PDF', 'forms' => 'Forms', 'tables' => 'Tables'],
        ['pdf', 'forms'],
        'NotoSans-Regular',
        11,
        flags: new FormFieldFlags(multiSelect: true),
        accessibleName: 'Topics selection',
        fieldLabel: new FormFieldLabel('Topics', new Position(120, 104), 'NotoSans-Regular', 10),
    );

    return $document;
}

function createPdfUa1AnnotationBatchFixture(): Document
{
    $document = createPdfUaDocument('PDF/UA-1 Annotation Regression', 'Representative PDF/UA-1 annotation regression fixture');
    $document->addAttachment('note.txt', 'Regression attachment', 'Regression attachment', 'text/plain');
    $page = $document->addPage(PageSize::custom(260, 260));

    $page->addText(
        'Accessible Annotations',
        new Position(12, 240),
        'NotoSans-Bold',
        14,
        new TextOptions(structureTag: StructureTag::Heading1),
    );
    $page->addText(
        'This fixture covers the current non-widget annotation paths plus popup and attachment annotations.',
        new Position(12, 224),
        'NotoSans-Regular',
        10,
        new TextOptions(structureTag: StructureTag::Paragraph),
    );

    $page->addTextAnnotation(new Rect(12, 196, 12, 12), 'Review note', 'QA');
    $popupParent = internalPage($page)->getAnnotations()[0];
    $page->addPopupAnnotation($popupParent, new Rect(28, 186, 36, 20), true);
    $page->addFreeTextAnnotation(new Rect(72, 186, 62, 24), 'Free text note', 'NotoSans-Regular', 11);
    $page->addHighlightAnnotation(new Rect(12, 160, 48, 12), Color::rgb(255, 230, 0), 'Highlight note', 'QA');
    $page->addUnderlineAnnotation(new Rect(72, 160, 48, 12), Color::rgb(0, 90, 200), 'Underline note', 'QA');
    $page->addStrikeOutAnnotation(new Rect(132, 160, 48, 12), Color::rgb(200, 30, 30), 'Strikeout note', 'QA');
    $page->addSquigglyAnnotation(new Rect(192, 160, 48, 12), Color::rgb(0, 130, 80), 'Squiggly note', 'QA');
    $page->addStampAnnotation(new Rect(12, 126, 42, 18), 'Draft', Color::rgb(190, 60, 60), 'Draft stamp', 'QA');
    $page->addSquareAnnotation(
        new Rect(72, 120, 32, 24),
        Color::rgb(30, 70, 160),
        Color::rgb(220, 235, 255),
        'Square note',
        'QA',
        new AnnotationBorderStyle(width: 1.2),
    );
    $page->addCircleAnnotation(
        new Rect(120, 120, 32, 24),
        Color::rgb(50, 120, 70),
        Color::rgb(220, 245, 225),
        'Circle note',
        'QA',
        new AnnotationBorderStyle(width: 1.2),
    );
    $page->addInkAnnotation(
        new Rect(168, 118, 48, 26),
        [[[172.0, 124.0], [180.0, 132.0], [188.0, 128.0], [198.0, 136.0], [208.0, 126.0]]],
        Color::rgb(80, 60, 160),
        'Ink note',
        'QA',
    );
    $page->addLineAnnotation(
        new Position(12, 92),
        new Position(58, 80),
        Color::rgb(40, 40, 40),
        'Line note',
        'QA',
        LineEndingStyle::CLOSED_ARROW,
        LineEndingStyle::NONE,
        'Line subject',
        new AnnotationBorderStyle(width: 1.2),
    );
    $page->addPolyLineAnnotation(
        [[72.0, 90.0], [92.0, 82.0], [112.0, 88.0]],
        Color::rgb(20, 110, 140),
        'Polyline note',
        'QA',
        LineEndingStyle::NONE,
        LineEndingStyle::OPEN_ARROW,
        'Polyline subject',
        new AnnotationBorderStyle(width: 1.2),
    );
    $page->addPolygonAnnotation(
        [[126.0, 90.0], [148.0, 82.0], [160.0, 96.0], [136.0, 104.0]],
        Color::rgb(130, 80, 20),
        Color::rgb(250, 232, 210),
        'Polygon note',
        'QA',
        'Polygon subject',
        new AnnotationBorderStyle(width: 1.2),
    );
    $page->addCaretAnnotation(new Rect(184, 84, 18, 18), 'Caret note', 'QA');

    $file = $document->getAttachment('note.txt');

    if ($file === null) {
        throw new RuntimeException('Expected regression attachment to exist.');
    }

    $page->addFileAttachment(new Rect(220, 84, 14, 16), $file, 'Graph', 'Regression attachment');

    return $document;
}

function createPdfUa1TableCaptionPaginationFixture(): Document
{
    $document = createPdfUaDocument(
        'PDF/UA-1 Multipage Table Regression',
        'Representative PDF/UA-1 regression fixture for table captions, repeated headers and row headers across multiple pages',
    );
    $page = $document->addPage(PageSize::custom(220, 190));

    $page->addText(
        'Accessible Multipage Table',
        new Position(12, 172),
        'NotoSans-Bold',
        14,
        new TextOptions(structureTag: StructureTag::Heading1),
    );
    $page->addText(
        'This fixture keeps the caption on the first page while the header row repeats and the first body column stays tagged as row headers across following pages.',
        new Position(12, 156),
        'NotoSans-Regular',
        10,
        new TextOptions(structureTag: StructureTag::Paragraph),
    );

    $table = $page->createTable(new Position(12, 132), 196, [46, 50, 50, 50], 18);
    $table
        ->font('NotoSans-Regular', 10)
        ->caption(new TableCaption(
            'Regional service quality by quarter',
            fontName: 'NotoSans-Bold',
            size: 12,
            color: Color::rgb(20, 40, 90),
            spacingAfter: 5.0,
        ))
        ->addHeaderRow([
            new TableCell('Region', headerScope: TableHeaderScope::Both),
            'January',
            'February',
            'March',
        ]);

    $rows = [
        ['North', '98 %', '97 %', '99 %'],
        ['South', '94 %', '95 %', '96 %'],
        ['West', '99 %', '98 %', '97 %'],
        ['East', '96 %', '97 %', '95 %'],
        ['Central', '93 %', '94 %', '95 %'],
        ['Coastal', '97 %', '96 %', '98 %'],
        ['Mountain', '95 %', '94 %', '96 %'],
        ['Metro', '99 %', '99 %', '98 %'],
        ['Rural', '92 %', '93 %', '94 %'],
        ['Delta', '96 %', '95 %', '97 %'],
        ['Harbor', '98 %', '97 %', '99 %'],
        ['Valley', '94 %', '95 %', '95 %'],
    ];

    foreach ($rows as [$region, $january, $february, $march]) {
        $table->addRow([
            new TableCell($region, headerScope: TableHeaderScope::Row),
            $january,
            $february,
            $march,
        ]);
    }

    return $document;
}

function createPdfUa1TableCaptionSpansFixture(): Document
{
    $document = createPdfUaDocument(
        'PDF/UA-1 Table Span Regression',
        'Representative PDF/UA-1 regression fixture for multipage table captions with row headers, rowspan and colspan',
    );
    $page = $document->addPage(PageSize::custom(220, 190));

    $page->addText(
        'Accessible Multipage Table With Spans',
        new Position(12, 172),
        'NotoSans-Bold',
        14,
        new TextOptions(structureTag: StructureTag::Heading1),
    );
    $page->addText(
        'This fixture combines a caption, repeated headers, row headers, rowspan groups and summary rows with colspan across multiple pages.',
        new Position(12, 156),
        'NotoSans-Regular',
        10,
        new TextOptions(structureTag: StructureTag::Paragraph),
    );

    $table = $page->createTable(new Position(12, 132), 196, [34, 36, 42, 42, 42], 18);
    $table
        ->font('NotoSans-Regular', 10)
        ->caption(new TableCaption(
            'Regional service quality and follow-up metrics',
            fontName: 'NotoSans-Bold',
            size: 12,
            color: Color::rgb(20, 40, 90),
            spacingAfter: 5.0,
        ))
        ->addHeaderRow([
            new TableCell('Region', headerScope: TableHeaderScope::Both),
            'Metric',
            'January',
            'February',
            'March',
        ]);

    foreach ([
        ['North', '98 %', '97 %', '99 %', '1.2 h', '1.1 h', '1.0 h'],
        ['South', '94 %', '95 %', '96 %', '1.8 h', '1.6 h', '1.5 h'],
        ['West', '99 %', '98 %', '97 %', '0.9 h', '1.0 h', '1.1 h'],
        ['East', '96 %', '97 %', '95 %', '1.4 h', '1.3 h', '1.4 h'],
        ['Central', '93 %', '94 %', '95 %', '2.1 h', '1.9 h', '1.8 h'],
        ['Coastal', '97 %', '96 %', '98 %', '1.0 h', '1.1 h', '1.0 h'],
    ] as [$region, $janAvailability, $febAvailability, $marAvailability, $janResponse, $febResponse, $marResponse]) {
        $table->addRow([
            new TableCell($region, rowspan: 2, headerScope: TableHeaderScope::Row),
            'Availability',
            $janAvailability,
            $febAvailability,
            $marAvailability,
        ]);
        $table->addRow([
            'Response time',
            $janResponse,
            $febResponse,
            $marResponse,
        ]);
        $table->addRow([
            new TableCell($region . ' summary', headerScope: TableHeaderScope::Row),
            new TableCell(
                'Stable service quality with a dedicated follow-up note across all reported months.',
                colspan: 4,
            ),
        ]);
    }

    return $document;
}

function createPdfUa1TableSpanBreaksFixture(): Document
{
    $document = createPdfUaDocument(
        'PDF/UA-1 Table Span Break Regression',
        'Representative PDF/UA-1 regression fixture for multipage rowspan and colspan groups with long content',
    );
    $page = $document->addPage(PageSize::custom(364, 260));

    $page->addText(
        'Accessible Span Groups Under Break Pressure',
        new Position(12, 302),
        'NotoSans-Bold',
        14,
        new TextOptions(structureTag: StructureTag::Heading1),
    );
    $page->addText(
        'This fixture combines repeated headers, rowspan groups, colspan summaries and longer cell content across multiple pages.',
        new Position(12, 286),
        'NotoSans-Regular',
        10,
        new TextOptions(structureTag: StructureTag::Paragraph),
    );

    $table = $page->createTable(new Position(12, 260), 340, [42, 46, 84, 84, 84], 14);
    $table
        ->font('NotoSans-Regular', 9)
        ->caption(new TableCaption(
            'Regional monthly service span review',
            fontName: 'NotoSans-Bold',
            size: 12,
            color: Color::rgb(20, 40, 90),
            spacingAfter: 4.0,
        ))
        ->addHeaderRow([
            new TableCell('Region', headerScope: TableHeaderScope::Both),
            'Metric',
            'January',
            'February',
            'March',
        ]);

    foreach ([
        [
            'North',
            'Availability review',
            '98 %',
            '97 %',
            '99 %',
            'Follow-up action',
            '1.2 h',
            '1.1 h',
            '1.0 h',
            'North summary',
            'North remains stable overall, but the reconciled figures, closeout note and owner handover all need to stay tied to the same monthly evidence set before archival.',
        ],
        [
            'South',
            'Availability review',
            '94 %',
            '95 %',
            '96 %',
            'Follow-up action',
            '1.8 h',
            '1.6 h',
            '1.5 h',
            'South summary',
            'South is close to completion, but the rollout history, remote branch notes and final acknowledgements still need one consistent summary across all three months.',
        ],
        [
            'West',
            'Availability review',
            '99 %',
            '98 %',
            '97 %',
            'Follow-up action',
            '0.9 h',
            '1.0 h',
            '1.1 h',
            'West summary',
            'West remains within tolerance, but the corrected timeline, merged evidence pack and vendor follow-up still need to remain traceable as one combined record.',
        ],
        [
            'East',
            'Availability review',
            '96 %',
            '97 %',
            '95 %',
            'Follow-up action',
            '1.4 h',
            '1.3 h',
            '1.4 h',
            'East summary',
            'East is structurally stable, but the handover acknowledgements, branch cross references and ownership map still need to stay linked in one archive packet.',
        ],
    ] as [
        $region,
        $firstMetric,
        $janFirst,
        $febFirst,
        $marFirst,
        $secondMetric,
        $janSecond,
        $febSecond,
        $marSecond,
        $summaryLabel,
        $summaryText,
    ]) {
        $table->addRow([
            new TableCell($region, rowspan: 2, headerScope: TableHeaderScope::Row),
            $firstMetric,
            $janFirst,
            $febFirst,
            $marFirst,
        ]);
        $table->addRow([
            $secondMetric,
            $janSecond,
            $febSecond,
            $marSecond,
        ]);
        $table->addRow([
            new TableCell($summaryLabel, headerScope: TableHeaderScope::Row),
            new TableCell($summaryText, colspan: 4),
        ]);
    }

    return $document;
}

function createPdfUa1TableHeaderMatrixFixture(): Document
{
    $document = createPdfUaDocument(
        'PDF/UA-1 Table Header Matrix Regression',
        'Representative PDF/UA-1 regression fixture for multipage table header matrices with grouped column headers',
    );
    $page = $document->addPage(PageSize::custom(220, 190));

    $page->addText(
        'Accessible Multipage Header Matrix',
        new Position(12, 172),
        'NotoSans-Bold',
        14,
        new TextOptions(structureTag: StructureTag::Heading1),
    );
    $page->addText(
        'This fixture combines a table caption, two repeated header rows, grouped column headers and row headers across multiple pages.',
        new Position(12, 156),
        'NotoSans-Regular',
        10,
        new TextOptions(structureTag: StructureTag::Paragraph),
    );

    $table = $page->createTable(new Position(12, 132), 196, [36, 40, 40, 40, 40], 18);
    $table
        ->font('NotoSans-Regular', 10)
        ->caption(new TableCaption(
            'Regional service review matrix',
            fontName: 'NotoSans-Bold',
            size: 12,
            color: Color::rgb(20, 40, 90),
            spacingAfter: 5.0,
        ))
        ->addHeaderRow([
            new TableCell('Region', rowspan: 2, headerScope: TableHeaderScope::Both),
            new TableCell('Service quality', colspan: 2, headerScope: TableHeaderScope::Column),
            new TableCell('Follow-up', colspan: 2, headerScope: TableHeaderScope::Column),
        ])
        ->addHeaderRow([
            'Availability',
            'Response time',
            'Escalations',
            'Resolved',
        ]);

    foreach ([
        ['North', '98 %', '1.2 h', '2', '18'],
        ['South', '94 %', '1.8 h', '4', '14'],
        ['West', '99 %', '0.9 h', '1', '20'],
        ['East', '96 %', '1.4 h', '3', '16'],
        ['Central', '93 %', '2.1 h', '5', '12'],
        ['Coastal', '97 %', '1.0 h', '2', '19'],
        ['Mountain', '95 %', '1.5 h', '3', '15'],
        ['Metro', '99 %', '0.8 h', '1', '21'],
        ['Rural', '92 %', '2.3 h', '6', '11'],
        ['Delta', '96 %', '1.3 h', '2', '17'],
        ['Harbor', '98 %', '1.1 h', '1', '20'],
        ['Valley', '94 %', '1.9 h', '4', '13'],
    ] as [$region, $availability, $responseTime, $escalations, $resolved]) {
        $table->addRow([
            new TableCell($region, headerScope: TableHeaderScope::Row),
            $availability,
            $responseTime,
            $escalations,
            $resolved,
        ]);
    }

    return $document;
}

function createPdfUa1TableHeaderMatrixBreaksFixture(): Document
{
    $document = createPdfUaDocument(
        'PDF/UA-1 Table Header Matrix Break Regression',
        'Representative PDF/UA-1 regression fixture for header matrices with long content and aggressive page breaks',
    );
    $page = $document->addPage(PageSize::custom(364, 260));

    $page->addText(
        'Accessible Header Matrix Under Pressure',
        new Position(12, 242),
        'NotoSans-Bold',
        14,
        new TextOptions(structureTag: StructureTag::Heading1),
    );
    $page->addText(
        'This fixture keeps the caption and repeated matrix headers stable while long cell content forces larger row heights and more frequent page breaks.',
        new Position(12, 226),
        'NotoSans-Regular',
        10,
        new TextOptions(structureTag: StructureTag::Paragraph),
    );

    $table = $page->createTable(new Position(12, 200), 340, [46, 44, 80, 66, 104], 14);
    $table
        ->font('NotoSans-Regular', 9)
        ->caption(new TableCaption(
            'Regional issue review matrix',
            fontName: 'NotoSans-Bold',
            size: 12,
            color: Color::rgb(20, 40, 90),
            spacingAfter: 4.0,
        ))
        ->addHeaderRow([
            new TableCell('Region', rowspan: 2, headerScope: TableHeaderScope::Both),
            new TableCell('Operations', colspan: 2, headerScope: TableHeaderScope::Column),
            new TableCell('Follow-up', colspan: 2, headerScope: TableHeaderScope::Column),
        ])
        ->addHeaderRow([
            'Status',
            'Assessment',
            'Owner',
            'Next step',
        ]);

    foreach ([
        [
            'North',
            'Stable',
            'A longer assessment note confirms that availability stayed high while two edge locations still need manual monitoring during the morning handover.',
            'Regional ops lead',
            'Confirm the revised handover checklist, collect one additional day of evidence and send the final note back to the service desk lead.',
        ],
        [
            'South',
            'Review',
            'The service recovered after a routing issue, but the branch rollout still needs another validation round before the region can close the incident.',
            'Field coordination',
            'Review the remaining branch exceptions, update the communication pack and schedule the final rollback window with the network team.',
        ],
        [
            'West',
            'Stable',
            'The quarterly review is positive, although one escalation requires a written explanation because the response-time target was missed on a single weekend.',
            'Incident manager',
            'Attach the retrospective summary, publish the corrected service note and validate the revised escalation rota with the on-call team.',
        ],
        [
            'East',
            'Watch',
            'The region meets the main targets, but repeated staffing changes created inconsistent follow-up notes and an incomplete ownership handover.',
            'Regional support',
            'Document the ownership changes, align the handover template and review the open actions with the regional support lead.',
        ],
        [
            'Central',
            'Escalated',
            'This region still shows the highest risk because the branch deployment, response backlog and vendor coordination all remain open in parallel.',
            'Programme office',
            'Consolidate the vendor timeline, close the oldest backlog items and escalate unresolved blockers to the steering round this week.',
        ],
        [
            'Coastal',
            'Stable',
            'Coastal performance is strong overall, but the offshore sites need a clearer fallback plan for short-notice weather interruptions.',
            'Site operations',
            'Validate the weather fallback checklist, refresh the standby contacts and publish the updated fallback instructions to local teams.',
        ],
        [
            'Mountain',
            'Review',
            'Monitoring remained stable, yet one remote site produced delayed acknowledgements and therefore a misleading escalation trail in the weekly report.',
            'Monitoring lead',
            'Correct the weekly report, explain the delayed acknowledgements and keep the remote-site checks active for another reporting cycle.',
        ],
        [
            'Metro',
            'Stable',
            'Metro closed all urgent incidents, but the final documentation pass still needs to confirm that the temporary workaround is fully removed.',
            'Service owner',
            'Confirm the workaround removal, archive the temporary instructions and publish the final closeout note to stakeholders.',
        ],
    ] as [$region, $status, $assessment, $owner, $nextStep]) {
        $table->addRow([
            new TableCell($region, headerScope: TableHeaderScope::Row),
            $status,
            $assessment,
            $owner,
            $nextStep,
        ]);
    }

    return $document;
}

function createPdfUa1TableNarrowColumnFixture(): Document
{
    $document = createPdfUaDocument(
        'PDF/UA-1 Narrow Column Table Regression',
        'Representative PDF/UA-1 regression fixture for narrow columns, empty cells and hard unbreakable tokens',
    );
    $page = $document->addPage(PageSize::custom(240, 220));

    $page->addText(
        'Accessible Narrow Column Stress Table',
        new Position(12, 202),
        'NotoSans-Bold',
        14,
        new TextOptions(structureTag: StructureTag::Heading1),
    );
    $page->addText(
        'This fixture keeps compact columns, empty cells and hard tokens stable without dropping table semantics.',
        new Position(12, 186),
        'NotoSans-Regular',
        10,
        new TextOptions(structureTag: StructureTag::Paragraph),
    );

    $table = $page->createTable(new Position(12, 162), 216, [28, 32, 48, 34, 74], 12);
    $table
        ->font('NotoSans-Regular', 9)
        ->caption(new TableCaption(
            'Compact issue constraint log',
            fontName: 'NotoSans-Bold',
            size: 11,
            color: Color::rgb(20, 40, 90),
            spacingAfter: 4.0,
        ))
        ->addHeaderRow([
            'Area',
            'Queue',
            'Constraint token',
            'Owner',
            'Action',
        ]);

    foreach ([
        ['North', '', 'INC2026ALPHAOMEGA0004711', 'Ops', 'Escalate owner handover and capture the aftercare notes before Friday.'],
        ['South', 'Review', '', '', 'Keep the branch note open until the external approval arrives.'],
        ['West', 'Backlog', 'REGIONALHANDOVERALPHA2026040801', 'Team', 'Consolidate duplicated notes and publish the shortened exception list.'],
        ['East', '', 'SUPPLIERCHAINBLOCKER202604081245', 'Vendor', 'Align the contingency owners and archive the obsolete workaround memo.'],
        ['Central', 'Stable', '', 'Office', ''],
        ['Coastal', 'Watch', 'REMOTESITEFOLLOWUPTOKEN20260408Z', '', 'Refresh the fallback roster and confirm the weather escalation path.'],
    ] as [$area, $queue, $constraintToken, $owner, $action]) {
        $table->addRow([
            new TableCell($area, headerScope: TableHeaderScope::Row),
            $queue,
            $constraintToken,
            $owner,
            $action,
        ]);
    }

    return $document;
}

function createPdfUa1MixedFixture(): Document
{
    $document = createPdfUaDocument('PDF/UA-1 Mixed Regression', 'Representative PDF/UA-1 mixed regression fixture');
    $document->addAttachment('source.xml', '<data/>', 'Source data attachment', 'application/xml');

    $overviewPage = $document->addPage(PageSize::custom(220, 260));

    $overviewPage->addText(
        'Accessible Summary',
        new Position(12, 242),
        'NotoSans-Bold',
        15,
        new TextOptions(structureTag: StructureTag::Heading1),
    );
    $overviewPage->addText(
        'This mixed fixture combines layout, links, tables, form labels, annotations and embedded files in one tagged PDF/UA-1 document.',
        new Position(12, 226),
        'NotoSans-Regular',
        10,
        new TextOptions(structureTag: StructureTag::Paragraph),
    );

    $overviewPage->addBadge(
        'Validated',
        new Position(12, 206),
        'NotoSans-Regular',
        10,
        new BadgeStyle(
            fillColor: Color::rgb(226, 240, 222),
            textColor: Color::rgb(38, 78, 45),
        ),
    );
    $overviewPage->addPanel(
        'Visible linked panel body.',
        12,
        138,
        92,
        54,
        'Status panel',
        'NotoSans-Regular',
        new PanelStyle(),
        null,
        LinkTarget::externalUrl('https://example.com/status'),
    );
    $overviewPage->addCallout(
        'Linked callout body.',
        116,
        138,
        92,
        54,
        108,
        132,
        'Callout',
        'NotoSans-Regular',
        new CalloutStyle(),
        null,
        LinkTarget::externalUrl('https://example.com/callout'),
    );

    $overviewPage->addText(
        'Read the detailed guide',
        new Position(12, 126),
        'NotoSans-Regular',
        11,
        new TextOptions(
            structureTag: StructureTag::Paragraph,
            link: LinkTarget::externalUrl('https://example.com/guide'),
        ),
    );
    $overviewPage->addLink(new Rect(12, 108, 72, 12), 'https://example.com/api', 'Open API guide');

    $table = $overviewPage->createTable(new Position(12, 96), 196, [92, 104]);
    $table
        ->font('NotoSans-Regular', 10)
        ->addHeaderRow(['Area', 'Result'])
        ->addRow(['Layout', 'Decorative frames are artifacts and visible text is tagged.'])
        ->addRow(['Links', 'Text and standalone links carry accessible descriptions.']);

    $overviewPage->addImage(
        new Image(1, 1, 'DeviceGray', 'FlateDecode', "\x00"),
        new Position(190, 20),
        12,
        12,
        new ImageOptions(
            structureTag: StructureTag::Figure,
            altText: 'Small mixed fixture marker image',
        ),
    );

    $formPage = $document->addPage(PageSize::custom(220, 260));

    $formPage->addText(
        'Accessible Form Review',
        new Position(12, 242),
        'NotoSans-Bold',
        15,
        new TextOptions(structureTag: StructureTag::Heading1),
    );
    $formPage->addTextField(
        'customer_name',
        new Rect(12, 204, 92, 16),
        'Ada Lovelace',
        'NotoSans-Regular',
        11,
        accessibleName: 'Customer name',
        fieldLabel: new FormFieldLabel('Customer name', new Position(12, 224), 'NotoSans-Regular', 10),
    );
    $formPage->addCheckbox(
        'accept_terms',
        new Position(12, 178),
        12,
        true,
        'Accept terms',
        new FormFieldLabel('Accept terms', new Position(30, 180), 'NotoSans-Regular', 10),
    );
    $formPage->addRadioButton(
        'delivery',
        'standard',
        new Position(12, 152),
        12,
        true,
        'Standard delivery',
        new FormFieldLabel('Standard delivery', new Position(30, 154), 'NotoSans-Regular', 10),
    );
    $formPage->addRadioButton(
        'delivery',
        'express',
        new Position(116, 152),
        12,
        false,
        'Express delivery',
        new FormFieldLabel('Express delivery', new Position(134, 154), 'NotoSans-Regular', 10),
    );
    $formPage->addComboBox(
        'country',
        new Rect(12, 118, 92, 16),
        ['de' => 'Germany', 'at' => 'Austria'],
        'de',
        'NotoSans-Regular',
        11,
        accessibleName: 'Country selection',
        fieldLabel: new FormFieldLabel('Country', new Position(12, 138), 'NotoSans-Regular', 10),
    );
    $formPage->addListBox(
        'topics',
        new Rect(12, 56, 92, 44),
        ['pdf' => 'PDF', 'forms' => 'Forms', 'tables' => 'Tables'],
        'forms',
        'NotoSans-Regular',
        11,
        accessibleName: 'Topics selection',
        fieldLabel: new FormFieldLabel('Topics', new Position(12, 104), 'NotoSans-Regular', 10),
    );
    $formPage->addSignatureField(
        'approval_signature',
        new Rect(116, 204, 92, 16),
        'Approval signature',
        new FormFieldLabel('Approval signature', new Position(116, 224), 'NotoSans-Regular', 10),
    );
    $formPage->addPushButton(
        'save_form',
        'Save',
        new Rect(116, 118, 56, 16),
        'NotoSans-Regular',
        11,
        accessibleName: 'Save form',
        fieldLabel: new FormFieldLabel('Save action', new Position(116, 138), 'NotoSans-Regular', 10),
    );

    $annotationPage = $document->addPage(PageSize::custom(220, 220));

    $annotationPage->addText(
        'Accessible Notes',
        new Position(12, 202),
        'NotoSans-Bold',
        15,
        new TextOptions(structureTag: StructureTag::Heading1),
    );
    $annotationPage->addText(
        'Annotations and file references stay tagged in the same document as the layout and form content.',
        new Position(12, 186),
        'NotoSans-Regular',
        10,
        new TextOptions(structureTag: StructureTag::Paragraph),
    );
    $annotationPage->addTextAnnotation(new Rect(12, 154, 12, 12), 'Review note', 'QA');
    $popupParent = internalPage($annotationPage)->getAnnotations()[0];
    $annotationPage->addPopupAnnotation($popupParent, new Rect(28, 144, 36, 20), true);
    $annotationPage->addHighlightAnnotation(new Rect(12, 126, 60, 12), Color::rgb(255, 230, 0), 'Highlight note', 'QA');
    $annotationPage->addFreeTextAnnotation(new Rect(84, 118, 80, 24), 'Annotation summary', 'NotoSans-Regular', 11);

    $attachment = $document->getAttachment('source.xml');

    if ($attachment === null) {
        throw new RuntimeException('Expected mixed regression attachment to exist.');
    }

    $annotationPage->addFileAttachment(new Rect(180, 118, 14, 16), $attachment, 'PushPin', 'Source data attachment');

    return $document;
}

function createPdfUa1DeepMixedFixture(): Document
{
    $document = createPdfUaDocument('PDF/UA-1 Deep Mixed Regression', 'Representative PDF/UA-1 deep mixed regression fixture');
    $document->addAttachment('review.xml', '<review status="ok"/>', 'Review data attachment', 'application/xml');

    $summaryPage = $document->addPage(PageSize::custom(220, 260));
    $reviewPage = $document->addPage(PageSize::custom(220, 260));
    $formPage = $document->addPage(PageSize::custom(220, 260));
    $notesPage = $document->addPage(PageSize::custom(220, 220));

    $document
        ->addDestination('deep-summary', $summaryPage)
        ->addDestination('deep-review', $reviewPage)
        ->addDestination('deep-form-review', $formPage)
        ->addDestination('deep-notes', $notesPage);

    $summaryPage->addText(
        'Accessible Review Packet',
        new Position(12, 242),
        'NotoSans-Bold',
        15,
        new TextOptions(structureTag: StructureTag::Heading1),
    );
    $summaryPage->addText(
        'This deep integration fixture combines internal destinations, tagged lists, tables, linked layout blocks, forms, annotations, attachments and figures across multiple pages.',
        new Position(12, 226),
        'NotoSans-Regular',
        10,
        new TextOptions(structureTag: StructureTag::Paragraph),
    );
    $summaryPage->addText(
        'Jump to review table',
        new Position(12, 210),
        'NotoSans-Regular',
        10,
        new TextOptions(
            structureTag: StructureTag::Paragraph,
            link: LinkTarget::namedDestination('deep-review'),
        ),
    );
    $summaryPage->addInternalLink(new Rect(12, 192, 86, 12), 'deep-form-review', 'Jump to form review');
    $summaryPage->addBadge(
        'Deep check',
        new Position(12, 176),
        'NotoSans-Regular',
        10,
        new BadgeStyle(
            fillColor: Color::rgb(229, 238, 254),
            textColor: Color::rgb(28, 56, 118),
        ),
    );
    $summaryPage->addPanel(
        'Status panel body with visible linked text.',
        116,
        142,
        92,
        54,
        'Status panel',
        'NotoSans-Regular',
        new PanelStyle(),
        null,
        LinkTarget::externalUrl('https://example.com/deep-status'),
    );
    $summaryPage->createTextFrame(
        new Position(12, 160),
        92,
        74,
    )
        ->addBulletList(
            [
                'Summary page exposes internal and external navigation.',
                'Review page validates list and table semantics together.',
                'Form and notes pages keep widgets, annotations and attachments in one tagged document.',
            ],
            'NotoSans-Regular',
            10,
            options: new ListOptions(
                structureTag: StructureTag::List,
                lineHeight: 14,
                itemSpacing: 6,
            ),
        );

    $reviewPage->addText(
        'Review Matrix',
        new Position(12, 242),
        'NotoSans-Bold',
        15,
        new TextOptions(structureTag: StructureTag::Heading1),
    );
    $reviewPage->addText(
        'Continue to the notes page',
        new Position(12, 226),
        'NotoSans-Regular',
        10,
        new TextOptions(
            structureTag: StructureTag::Paragraph,
            link: LinkTarget::namedDestination('deep-notes'),
        ),
    );

    $reviewTable = $reviewPage->createTable(new Position(12, 192), 196, [62, 62, 72]);
    $reviewTable
        ->font('NotoSans-Regular', 10)
        ->addHeaderRow(['Area', 'Check', 'Result'])
        ->addRow(['Links', 'Internal destinations', 'Summary and notes links stay tagged.'])
        ->addRow(['Forms', 'Visible labels', 'Widgets share a common form block with labels.'])
        ->addRow(['Annotations', 'OBJR mapping', 'Notes and attachments keep structure references.']);

    $reviewPage->addImage(
        new Image(1, 1, 'DeviceGray', 'FlateDecode', "\x00"),
        new Position(188, 24),
        12,
        12,
        new ImageOptions(
            structureTag: StructureTag::Figure,
            altText: 'Small review matrix marker image',
        ),
    );

    $formPage->addText(
        'Accessible Form Review',
        new Position(12, 242),
        'NotoSans-Bold',
        15,
        new TextOptions(structureTag: StructureTag::Heading1),
    );
    $formPage->addTextField(
        'reviewer_name',
        new Rect(12, 204, 92, 16),
        'Ada Lovelace',
        'NotoSans-Regular',
        11,
        accessibleName: 'Reviewer name',
        fieldLabel: new FormFieldLabel('Reviewer name', new Position(12, 224), 'NotoSans-Regular', 10),
    );
    $formPage->addCheckbox(
        'accept_findings',
        new Position(12, 178),
        12,
        true,
        'Accept findings',
        new FormFieldLabel('Accept findings', new Position(30, 180), 'NotoSans-Regular', 10),
    );
    $formPage->addRadioButton(
        'priority',
        'standard',
        new Position(12, 152),
        12,
        true,
        'Standard priority',
        new FormFieldLabel('Standard priority', new Position(30, 154), 'NotoSans-Regular', 10),
    );
    $formPage->addRadioButton(
        'priority',
        'expedited',
        new Position(116, 152),
        12,
        false,
        'Expedited priority',
        new FormFieldLabel('Expedited priority', new Position(134, 154), 'NotoSans-Regular', 10),
    );
    $formPage->addComboBox(
        'region',
        new Rect(12, 118, 92, 16),
        ['emea' => 'EMEA', 'apac' => 'APAC', 'na' => 'North America'],
        'emea',
        'NotoSans-Regular',
        11,
        accessibleName: 'Region selection',
        fieldLabel: new FormFieldLabel('Region', new Position(12, 138), 'NotoSans-Regular', 10),
    );
    $formPage->addListBox(
        'topics',
        new Rect(12, 46, 92, 56),
        ['pdf' => 'PDF', 'ua' => 'PDF/UA', 'forms' => 'Forms', 'tables' => 'Tables'],
        ['ua', 'forms'],
        'NotoSans-Regular',
        11,
        flags: new FormFieldFlags(multiSelect: true),
        accessibleName: 'Topics selection',
        fieldLabel: new FormFieldLabel('Topics', new Position(12, 106), 'NotoSans-Regular', 10),
    );
    $formPage->addPushButton(
        'submit_review',
        'Submit',
        new Rect(116, 204, 72, 16),
        'NotoSans-Regular',
        11,
        accessibleName: 'Submit review',
        fieldLabel: new FormFieldLabel('Submit action', new Position(116, 224), 'NotoSans-Regular', 10),
    );
    $formPage->addSignatureField(
        'review_signature',
        new Rect(116, 118, 92, 16),
        'Review signature',
        new FormFieldLabel('Review signature', new Position(116, 138), 'NotoSans-Regular', 10),
    );

    $notesPage->addText(
        'Accessible Notes',
        new Position(12, 202),
        'NotoSans-Bold',
        15,
        new TextOptions(structureTag: StructureTag::Heading1),
    );
    $notesPage->addText(
        'Return to summary',
        new Position(12, 186),
        'NotoSans-Regular',
        10,
        new TextOptions(
            structureTag: StructureTag::Paragraph,
            link: LinkTarget::namedDestination('deep-summary'),
        ),
    );
    $notesPage->addTextAnnotation(new Rect(12, 154, 12, 12), 'Review note', 'QA');
    $popupParent = internalPage($notesPage)->getAnnotations()[0];
    $notesPage->addPopupAnnotation($popupParent, new Rect(28, 144, 36, 20), true);
    $notesPage->addHighlightAnnotation(new Rect(12, 126, 72, 12), Color::rgb(255, 230, 0), 'Highlight note', 'QA');
    $notesPage->addFreeTextAnnotation(new Rect(96, 118, 88, 24), 'Deep integration note', 'NotoSans-Regular', 11);

    $attachment = $document->getAttachment('review.xml');

    if ($attachment === null) {
        throw new RuntimeException('Expected deep mixed regression attachment to exist.');
    }

    $notesPage->addFileAttachment(new Rect(188, 118, 14, 16), $attachment, 'PushPin', 'Review data attachment');

    return $document;
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
        creatorTool: 'bin/generate-pdfua-regression-fixtures.php',
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

function internalPage(Page $page): Page
{
    return $page;
}
