<?php

declare(strict_types=1);

use Kalle\Pdf\Document\TableOfContents\TableOfContentsOptions;
use Kalle\Pdf\Document\TableOfContents\TableOfContentsPlacement;

require __DIR__ . '/_table_of_contents_demo.php';

writeTableOfContentsDemo([
    'filename' => 'table-of-contents-after-cover',
    'subject' => 'Table of contents after the cover page',
    'cover_subtitle' => 'This variant inserts the TOC after the cover and before the chapter pages.',
    'cover_body' => 'That keeps the cover page first while the visible chapter navigation still shifts consistently behind the inserted TOC pages.',
    'chapter_note' => 'Because the TOC sits between cover and chapters in this variant, the first chapter starts later in the physical page order.',
    'toc_options' => new TableOfContentsOptions(
        title: 'Contents',
        placement: TableOfContentsPlacement::afterPage(1),
    ),
]);
