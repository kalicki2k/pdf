<?php

declare(strict_types=1);

use Kalle\Pdf\Document;
use Kalle\Pdf\Internal\Document\TableOfContents\TableOfContentsLeaderStyle;
use Kalle\Pdf\Internal\Document\TableOfContents\TableOfContentsOptions;
use Kalle\Pdf\Internal\Document\TableOfContents\TableOfContentsPlacement;
use Kalle\Pdf\Internal\Document\TableOfContents\TableOfContentsStyle;
use Kalle\Pdf\Internal\Layout\Geometry\Position;
use Kalle\Pdf\Internal\Layout\Page\PageSize;
use Kalle\Pdf\Internal\Layout\Page\Units;
use Kalle\Pdf\Internal\Layout\Text\Input\ParagraphOptions;
use Kalle\Pdf\Internal\Layout\Text\Input\TextOptions;
use Kalle\Pdf\Profile;
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
    subject: 'Table of contents demo with dot leaders',
    language: 'en-US',
    creator: 'Example Script',
    creatorTool: 'examples/table-of-contents-dots.php',
);

$document
    ->registerFont('Helvetica')
    ->registerFont('Helvetica-Bold')
    ->addKeyword('outline')
    ->addKeyword('table-of-contents')
    ->addKeyword('dot-leaders')
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
    'A classic TOC variant with dot leaders.',
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
    'This example makes the default dot leader style explicit so it can be compared directly against the dash and no-leader variants.',
    'Helvetica',
    12,
    new ParagraphOptions(lineHeight: 18.0),
);

$chapters = [
    ['title' => 'Introduction', 'lead' => 'This chapter introduces the document structure and the overall goal of the example.'],
    ['title' => 'Usage', 'lead' => 'The public API stays intentionally small: document, pages and a few focused building blocks.'],
    ['title' => 'Rendering', 'lead' => 'Rendering remains a final step after the complete document structure has been built.'],
];

foreach ($chapters as $chapter) {
    $page = $document->addPage(PageSize::A4());
    $page->addText($chapter['title'], new Position(Units::mm(20), Units::mm(270)), 'Helvetica-Bold', 24, new TextOptions(color: Color::rgb(40, 40, 40)));
    $page->addText($chapter['lead'], new Position(Units::mm(20), Units::mm(257)), 'Helvetica', 11, new TextOptions(color: Color::gray(0.25)));
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
            leaderStyle: TableOfContentsLeaderStyle::DOTS,
            entrySpacing: 2.0,
            pageNumberGap: 8.0,
        ),
    ),
);

$outputPath = $outputDir . '/table-of-contents-dots.pdf';
$document->writeToFile($outputPath);

printf('Generated %s%s', $outputPath, PHP_EOL);
