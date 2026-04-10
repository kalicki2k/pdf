<?php

declare(strict_types=1);

use Kalle\Pdf\Document;
use Kalle\Pdf\Geometry\Position;
use Kalle\Pdf\Graphics\Color;
use Kalle\Pdf\Layout\PageSize;
use Kalle\Pdf\Layout\TableOfContentsOptions;
use Kalle\Pdf\Layout\TableOfContentsPlacement;
use Kalle\Pdf\Layout\Units;
use Kalle\Pdf\Page;
use Kalle\Pdf\Profile;
use Kalle\Pdf\Text\ParagraphOptions;
use Kalle\Pdf\Text\TextOptions;

require __DIR__ . '/../vendor/autoload.php';

$outputDir = __DIR__ . '/../var/examples';

if (!is_dir($outputDir) && !mkdir($outputDir, 0777, true) && !is_dir($outputDir)) {
    throw new RuntimeException(sprintf('Unable to create output directory "%s".', $outputDir));
}

$document = new Document(
    profile: Profile::standard(1.4),
    title: 'Project Handbook',
    author: 'kalle/pdf',
    subject: 'Table of contents demo with logical page numbers',
    language: 'en-US',
    creator: 'Example Script',
    creatorTool: 'examples/table-of-contents-logical.php',
);

$document
    ->registerFont('Helvetica')
    ->registerFont('Helvetica-Bold')
    ->addKeyword('outline')
    ->addKeyword('table-of-contents')
    ->addKeyword('logical-page-numbers')
    ->addHeader(static function (Page $page, int $pageNumber): void {
        $page->addText(
            'Project Handbook',
            new Position(Units::mm(20), Units::mm(287)),
            'Helvetica',
            9,
            new TextOptions(color: Color::gray(0.35)),
        );

        $page->addText(
            sprintf('Page %d', $pageNumber),
            new Position(Units::mm(150), Units::mm(287)),
            'Helvetica',
            9,
            new TextOptions(color: Color::gray(0.35)),
        );
    });

$coverPage = $document->addPage(PageSize::A4());
$document->excludePageFromNumbering($coverPage);
$document->addPageNumbers(new Position(Units::mm(20), Units::mm(10)), 'Helvetica', 10, 'Page {{page}} / {{pages}}', true, true);

$coverPage->addText(
    'Project Handbook',
    new Position(Units::mm(20), Units::mm(230)),
    'Helvetica-Bold',
    30,
    new TextOptions(color: Color::rgb(20, 20, 20)),
);
$coverPage->addText(
    'A compact example showing a TOC with logical page numbers.',
    new Position(Units::mm(20), Units::mm(214)),
    'Helvetica',
    13,
    new TextOptions(color: Color::gray(0.3)),
);
$coverPage->createTextFrame(
    new Position(Units::mm(20), Units::mm(190)),
    Units::mm(155),
    Units::mm(40),
)->addParagraph(
    'The cover is excluded from logical numbering. The table of contents still sits at the start, but its entry page numbers skip the cover and therefore begin with the first chapter as logical page 2. The visible footer page numbers use the same logical numbering, so the cover remains unnumbered while the first chapter becomes page 1.',
    'Helvetica',
    12,
    new ParagraphOptions(lineHeight: 18.0),
);

$chapters = [
    [
        'title' => 'Introduction',
        'lead' => 'This chapter introduces the document structure and the overall goal of the example.',
        'body' => [
            'The table of contents is generated from explicit outline entries. Each chapter page is created first and then registered via addOutline(...).',
            'The cover is excluded from logical numbering before the table of contents and visible page numbers are generated.',
        ],
    ],
    [
        'title' => 'Usage',
        'lead' => 'The public API stays intentionally small: document, pages and a few focused building blocks.',
        'body' => [
            'A typical flow is: create the document, register fonts, create pages, add visible content and register outline entries for the sections you want to expose in the TOC.',
            'This variant keeps the physical PDF page order intact while using logical page numbers consistently for the TOC entries and the visible footer numbers.',
        ],
    ],
    [
        'title' => 'Rendering',
        'lead' => 'Rendering remains a final step after the complete document structure has been built.',
        'body' => [
            'The TOC is inserted at the front of the document, but its visible page references use logical numbering.',
            'That means the chapter entries can show 2, 3 and 4 even though the physical page order is TOC, cover, chapter 1, chapter 2, chapter 3.',
        ],
    ],
];

/**
 * @param array{title: string, lead: string, body: list<string>} $chapter
 */
$renderChapter = static function (Document $document, array $chapter): void {
    $page = $document->addPage(PageSize::A4());

    $page->addText(
        $chapter['title'],
        new Position(Units::mm(20), Units::mm(270)),
        'Helvetica-Bold',
        24,
        new TextOptions(color: Color::rgb(40, 40, 40)),
    );

    $page->addText(
        $chapter['lead'],
        new Position(Units::mm(20), Units::mm(257)),
        'Helvetica',
        11,
        new TextOptions(color: Color::gray(0.25)),
    );

    $frame = $page->createTextFrame(
        new Position(Units::mm(20), Units::mm(240)),
        Units::mm(170),
        Units::mm(20),
    );

    foreach ($chapter['body'] as $paragraph) {
        $frame->addParagraph(
            $paragraph,
            'Helvetica',
            12,
            new ParagraphOptions(lineHeight: 18.0, spacingAfter: 18.0),
        );
    }

    $page->addText(
        'Outline entry registered for this chapter.',
        new Position(Units::mm(20), Units::mm(35)),
        'Helvetica',
        10,
        new TextOptions(color: Color::rgb(0, 102, 153)),
    );

    $document->addOutline($chapter['title'], $page);
};

foreach ($chapters as $chapter) {
    $renderChapter($document, $chapter);
}

$document->addDestination('cover', $coverPage);

$document->addTableOfContents(
    PageSize::A4(),
    options: new TableOfContentsOptions(
        title: 'Contents',
        baseFont: 'Helvetica',
        titleSize: 20,
        entrySize: 11,
        margin: Units::mm(20),
        placement: TableOfContentsPlacement::start(),
        useLogicalPageNumbers: true,
    ),
);

$outputPath = $outputDir . '/table-of-contents-logical.pdf';
$document->writeToFile($outputPath);

printf('Generated %s%s', $outputPath, PHP_EOL);
