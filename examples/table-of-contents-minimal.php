<?php

declare(strict_types=1);

use Kalle\Pdf\Document\TableOfContents\TableOfContentsLeaderStyle;
use Kalle\Pdf\Document\TableOfContents\TableOfContentsOptions;
use Kalle\Pdf\Document\TableOfContents\TableOfContentsPlacement;
use Kalle\Pdf\Document\TableOfContents\TableOfContentsStyle;

require __DIR__ . '/_table_of_contents_demo.php';

writeTableOfContentsDemo([
    'filename' => 'table-of-contents-minimal',
    'subject' => 'Minimal table of contents demo',
    'cover_subtitle' => 'A minimal TOC variant without leader characters.',
    'cover_body' => 'This example uses a looser vertical rhythm and suppresses leader characters entirely to show the style hooks of the TOC API.',
    'chapter_note' => 'The minimal style keeps entry titles and page numbers aligned without leader dots or dashes.',
    'toc_options' => new TableOfContentsOptions(
        title: 'Contents',
        placement: TableOfContentsPlacement::start(),
        style: new TableOfContentsStyle(
            leaderStyle: TableOfContentsLeaderStyle::NONE,
            entrySpacing: 6.0,
            pageNumberGap: 12.0,
        ),
    ),
]);
