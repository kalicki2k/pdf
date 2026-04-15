<?php

declare(strict_types=1);

use Kalle\Pdf\Document\TableOfContents\TableOfContentsOptions;
use Kalle\Pdf\Document\TableOfContents\TableOfContentsPlacement;

require __DIR__ . '/_table_of_contents_demo.php';

writeTableOfContentsDemo([
    'filename' => 'table-of-contents',
    'subject' => 'Table of contents demo',
    'cover_subtitle' => 'A compact example showing outlines and an auto-generated table of contents at the start.',
    'cover_body' => 'The content pages are created first, outline entries are registered for the visible chapters and the table of contents is inserted at the beginning afterwards.',
    'chapter_note' => 'Insertion remains deterministic because the TOC page count is resolved before chapter page numbers are shifted.',
    'toc_options' => new TableOfContentsOptions(
        title: 'Contents',
        placement: TableOfContentsPlacement::start(),
    ),
]);
