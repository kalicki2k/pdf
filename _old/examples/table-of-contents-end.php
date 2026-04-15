<?php

declare(strict_types=1);

use Kalle\Pdf\Document\TableOfContents\TableOfContentsOptions;

require __DIR__ . '/_table_of_contents_demo.php';

writeTableOfContentsDemo([
    'filename' => 'table-of-contents-end',
    'subject' => 'Table of contents at the end',
    'cover_subtitle' => 'This variant appends the TOC after all content pages.',
    'cover_body' => 'The chapter pages remain on their original physical page numbers while the TOC still links back into the document through named destinations.',
    'chapter_note' => 'Because the TOC stays at the end in this variant, the chapter pages remain on their original physical page numbers.',
    'toc_options' => new TableOfContentsOptions(
        title: 'Contents',
    ),
]);
