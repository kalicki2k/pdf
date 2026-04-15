<?php

declare(strict_types=1);

use Kalle\Pdf\Document\TableOfContents\TableOfContentsLeaderStyle;
use Kalle\Pdf\Document\TableOfContents\TableOfContentsOptions;
use Kalle\Pdf\Document\TableOfContents\TableOfContentsPlacement;
use Kalle\Pdf\Document\TableOfContents\TableOfContentsStyle;

require __DIR__ . '/_table_of_contents_demo.php';

writeTableOfContentsDemo([
    'filename' => 'table-of-contents-dashes',
    'subject' => 'Table of contents with dash leaders',
    'cover_subtitle' => 'A TOC variant with dash leaders for a stronger visual rule between title and page number.',
    'cover_body' => 'This example reuses the same document flow but changes only the TOC style configuration.',
    'chapter_note' => 'Leader rendering is deterministic because the line is computed from the final entry and page number widths.',
    'toc_options' => new TableOfContentsOptions(
        title: 'Contents',
        placement: TableOfContentsPlacement::start(),
        style: new TableOfContentsStyle(
            leaderStyle: TableOfContentsLeaderStyle::DASHES,
            entrySpacing: 2.0,
        ),
    ),
]);
