<?php

declare(strict_types=1);

use Kalle\Pdf\Document\TableOfContents\TableOfContentsLeaderStyle;
use Kalle\Pdf\Document\TableOfContents\TableOfContentsOptions;
use Kalle\Pdf\Document\TableOfContents\TableOfContentsPlacement;
use Kalle\Pdf\Document\TableOfContents\TableOfContentsStyle;

require __DIR__ . '/_table_of_contents_demo.php';

writeTableOfContentsDemo([
    'filename' => 'table-of-contents-dots',
    'subject' => 'Table of contents with dotted leaders',
    'cover_subtitle' => 'A TOC variant with explicit dotted leaders and a slightly tighter layout.',
    'cover_body' => 'The default TOC style already uses dots, but this example keeps the style explicit to show the builder configuration directly.',
    'chapter_note' => 'The dotted leader style mirrors the classic TOC layout while keeping the page targets clickable through named destinations.',
    'toc_options' => new TableOfContentsOptions(
        title: 'Contents',
        placement: TableOfContentsPlacement::start(),
        style: new TableOfContentsStyle(
            leaderStyle: TableOfContentsLeaderStyle::DOTS,
            pageNumberGap: 10.0,
        ),
    ),
]);
