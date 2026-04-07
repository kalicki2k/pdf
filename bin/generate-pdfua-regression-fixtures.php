#!/usr/bin/env php
<?php

declare(strict_types=1);

use Kalle\Pdf\Document;
use Kalle\Pdf\Document\Annotation\AnnotationBorderStyle;
use Kalle\Pdf\Document\Annotation\LineEndingStyle;
use Kalle\Pdf\Document\Form\FormFieldLabel;
use Kalle\Pdf\Document\Form\FormFieldFlags;
use Kalle\Pdf\Document\Geometry\Position;
use Kalle\Pdf\Document\Geometry\Rect;
use Kalle\Pdf\Document\ImageOptions;
use Kalle\Pdf\Document\LinkTarget;
use Kalle\Pdf\Document\Page as InternalPage;
use Kalle\Pdf\Document\Style\BadgeStyle;
use Kalle\Pdf\Document\Style\CalloutStyle;
use Kalle\Pdf\Document\Style\PanelStyle;
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
    $outputDir . '/pdf-ua-1-annotation-batch.pdf' => createPdfUa1AnnotationBatchFixture(...),
    $outputDir . '/pdf-ua-1-mixed.pdf' => createPdfUa1MixedFixture(...),
];

foreach ($fixtures as $path => $createFixture) {
    file_put_contents($path, $createFixture()->render());
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
    $page = $document->addPage(PageSize::custom(220, 240));

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
        ->addRow(['Area', 'State'], header: true)
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
        ->addRow(['Area', 'Result'], header: true)
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

function internalPage(Page $page): InternalPage
{
    $property = new ReflectionProperty($page, 'page');

    /** @var InternalPage $internalPage */
    $internalPage = $property->getValue($page);

    return $internalPage;
}
