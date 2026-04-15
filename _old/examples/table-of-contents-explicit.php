<?php

declare(strict_types=1);

use Kalle\Pdf\Document\TableOfContents\TableOfContentsOptions;
use Kalle\Pdf\Document\TableOfContents\TableOfContentsPlacement;

require __DIR__ . '/_table_of_contents_demo.php';

writeTableOfContentsDemo([
    'filename' => 'table-of-contents-explicit',
    'subject' => 'Table of contents from explicit entries',
    'cover_subtitle' => 'This variant uses explicit TOC entries instead of PDF outlines as the TOC source.',
    'cover_body' => 'That keeps the example aligned with the current pdf2 API when visible TOC navigation is needed without writing bookmark objects.',
    'chapter_note' => 'In the current implementation, explicit TOC entries override outlines as the TOC source. This example uses only explicit entries.',
    'toc_options' => new TableOfContentsOptions(
        title: 'Contents',
        placement: TableOfContentsPlacement::start(),
    ),
    'use_explicit_entries' => true,
]);
