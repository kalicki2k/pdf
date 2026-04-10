<?php

declare(strict_types=1);

use Kalle\Pdf\Document\Document;
use Kalle\Pdf\Document\TableOfContents\TableOfContentsOptions;
use Kalle\Pdf\Document\TableOfContents\TableOfContentsPlacement;
use Kalle\Pdf\Layout\Geometry\Position;
use Kalle\Pdf\Layout\Page\PageSize;
use Kalle\Pdf\Layout\Page\Units;
use Kalle\Pdf\Layout\Text\Input\ParagraphOptions;
use Kalle\Pdf\Layout\Text\Input\TextOptions;
use Kalle\Pdf\Page\Page;
use Kalle\Pdf\Profile\Profile;
use Kalle\Pdf\Style\Color;

require __DIR__ . '/../vendor/autoload.php';

$outputDir = __DIR__ . '/../var/examples';

if (!is_dir($outputDir) && !mkdir($outputDir, 0777, true) && !is_dir($outputDir)) {
    throw new RuntimeException(sprintf('Unable to create output directory "%s".', $outputDir));
}

$document = new Document(
    profile: Profile::standard(1.4),
    title: 'Project Handbook',
    author: 'kalle/pdf',
    subject: 'Table of contents demo',
    language: 'en-US',
    creator: 'Example Script',
    creatorTool: 'examples/table-of-contents-after-cover.php',
);

$document
    ->registerFont('Helvetica')
    ->registerFont('Helvetica-Bold')
    ->addKeyword('outline')
    ->addKeyword('table-of-contents')
    ->addKeyword('example')
    ->addPageNumbers(new Position(Units::mm(20), Units::mm(10)), 'Helvetica', 10, 'Page {{page}} / {{pages}}')
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
$coverPage->addText(
    'Project Handbook',
    new Position(Units::mm(20), Units::mm(230)),
    'Helvetica-Bold',
    30,
    new TextOptions(color: Color::rgb(20, 20, 20)),
);
$coverPage->addText(
    'A compact example showing outlines and a table of contents after the cover.',
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
    'The content pages are created first, outline entries are registered for the visible chapters and the table of contents is inserted after the cover afterwards.',
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
            'At the end, addTableOfContents(...) inserts one or more TOC pages after the cover page.',
        ],
    ],
    [
        'title' => 'Usage',
        'lead' => 'The public API stays intentionally small: document, pages and a few focused building blocks.',
        'body' => [
            'A typical flow is: create the document, register fonts, create pages, add visible content and register outline entries for the sections you want to expose in the TOC.',
            'This keeps the authoring code explicit and makes the final navigation structure deterministic.',
        ],
    ],
    [
        'title' => 'Rendering',
        'lead' => 'Rendering remains a final step after the complete document structure has been built.',
        'body' => [
            'The example uses automatic page numbers so the generated TOC can reference the final page count after insertion after the cover.',
            'Because the TOC sits between cover and chapters in this variant, the first chapter starts on the third physical page.',
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
        placement: TableOfContentsPlacement::afterPage(1),
    ),
);

$outputPath = $outputDir . '/table-of-contents-after-cover.pdf';
$document->writeToFile($outputPath);

printf('Generated %s%s', $outputPath, PHP_EOL);
