<?php

declare(strict_types=1);

use Kalle\Pdf\Document;
use Kalle\Pdf\Document\Geometry\Position;
use Kalle\Pdf\Document\Text\ParagraphOptions;
use Kalle\Pdf\Document\Text\TextOptions;
use Kalle\Pdf\Graphics\Color;
use Kalle\Pdf\Layout\PageSize;
use Kalle\Pdf\Layout\TableOfContentsLeaderStyle;
use Kalle\Pdf\Layout\TableOfContentsOptions;
use Kalle\Pdf\Layout\TableOfContentsPlacement;
use Kalle\Pdf\Layout\TableOfContentsStyle;
use Kalle\Pdf\Layout\Units;

require __DIR__ . '/../vendor/autoload.php';

$outputDir = __DIR__ . '/../var/examples';

if (!is_dir($outputDir) && !mkdir($outputDir, 0777, true) && !is_dir($outputDir)) {
    throw new RuntimeException(sprintf('Unable to create output directory "%s".', $outputDir));
}

$document = new Document(
    version: 1.4,
    title: 'Project Handbook',
    author: 'kalle/pdf',
    subject: 'Minimal table of contents demo',
    language: 'en-US',
    creator: 'Example Script',
    creatorTool: 'examples/table-of-contents-minimal.php',
);

$document
    ->registerFont('Helvetica')
    ->registerFont('Helvetica-Bold')
    ->addKeyword('outline')
    ->addKeyword('table-of-contents')
    ->addKeyword('minimal-style')
    ->addPageNumbers(new Position(Units::mm(20), Units::mm(10)), 'Helvetica', 10, 'Page {{page}} / {{pages}}');

$coverPage = $document->addPage(PageSize::A4());
$coverPage->addText(
    'Project Handbook',
    new Position(Units::mm(20), Units::mm(230)),
    'Helvetica-Bold',
    30,
    new TextOptions(color: Color::rgb(20, 20, 20)),
);
$coverPage->addText(
    'A minimal TOC variant without leader dots.',
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
    'This example uses a table of contents style without leader characters and with more vertical spacing between entries.',
    'Helvetica',
    12,
    new ParagraphOptions(lineHeight: 18.0),
);

$chapters = [
    [
        'title' => 'Introduction',
        'lead' => 'This chapter introduces the document structure and the overall goal of the example.',
    ],
    [
        'title' => 'Usage',
        'lead' => 'The public API stays intentionally small: document, pages and a few focused building blocks.',
    ],
    [
        'title' => 'Rendering',
        'lead' => 'Rendering remains a final step after the complete document structure has been built.',
    ],
];

foreach ($chapters as $chapter) {
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

    $document->addOutline($chapter['title'], $page);
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
        style: new TableOfContentsStyle(
            leaderStyle: TableOfContentsLeaderStyle::NONE,
            entrySpacing: 6.0,
            pageNumberGap: 12.0,
        ),
    ),
);

$outputPath = $outputDir . '/table-of-contents-minimal.pdf';
file_put_contents($outputPath, $document->render());

printf('Generated %s%s', $outputPath, PHP_EOL);
