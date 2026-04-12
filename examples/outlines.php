<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Kalle\Pdf\Color\Color;
use Kalle\Pdf\Document\DefaultDocumentBuilder;
use Kalle\Pdf\Drawing\Units;
use Kalle\Pdf\Font\StandardFont;
use Kalle\Pdf\Page\Margin;
use Kalle\Pdf\Page\PageSize;
use Kalle\Pdf\Text\TextOptions;

$outputDirectory = __DIR__ . '/../var/examples';

if (!is_dir($outputDirectory) && !mkdir($outputDirectory, 0777, true) && !is_dir($outputDirectory)) {
    throw new RuntimeException('Unable to create example output directory.');
}

$margin = Margin::all(Units::mm(18));
$headline = new TextOptions(
    fontSize: 24,
    lineHeight: 28,
    spacingAfter: 8,
    fontName: StandardFont::HELVETICA_BOLD->value,
    color: Color::hex('#0f172a'),
);
$section = new TextOptions(
    fontSize: 16,
    lineHeight: 20,
    spacingBefore: 10,
    spacingAfter: 6,
    fontName: StandardFont::HELVETICA_BOLD->value,
    color: Color::hex('#1d4ed8'),
);
$body = new TextOptions(
    fontSize: 11,
    lineHeight: 15,
    spacingAfter: 8,
    color: Color::hex('#334155'),
);

DefaultDocumentBuilder::make()
    ->title('Outline Example')
    ->author('Kalle PDF')
    ->subject('Demonstrates nested document outlines across multiple pages')
    ->language('en-US')
    ->creator('examples/outlines.php')
    ->creatorTool('pdf2')
    ->pageSize(PageSize::A4())
    ->margin($margin)
    ->outline('Overview')
    ->text('Outline Example', $headline)
    ->text(
        'This example creates a small nested outline tree with chapters, sections and a subsection. The first bookmark points to the current page, later bookmarks jump to explicit pages and positions.',
        $body,
    )
    ->text('Overview', $section)
    ->text(
        'Use the bookmarks panel in your PDF viewer to jump between the pages in this document. The hierarchy in the viewer matches the explicit outline level used in the builder API.',
        $body,
    )
    ->newPage()
    ->outlineAt('Chapter 1', 2)
    ->outlineAtLevel('Highlights', 2, 2, 72, 720)
    ->text('Chapter 1', $headline)
    ->text(
        'A top-level outline can target a whole page. In this first iteration pdf2 writes explicit /XYZ destinations and uses the page top when no coordinates are given.',
        $body,
    )
    ->text('Highlights', $section)
    ->text(
        'Chapter 1 has a nested section bookmark. This keeps the example small while still showing that child outline items are linked to their parent in the generated PDF outline tree.',
        $body,
    )
    ->newPage()
    ->outlineAt('Chapter 2', 3)
    ->outlineAtLevel('Target Section', 2, 3, 72, 620)
    ->outlineAtLevel('Implementation Notes', 3, 3, 72, 520)
    ->text('Chapter 2', $headline)
    ->text(
        'This page contains more precise bookmark targets. One child outline points to the target section below instead of the page top, and a grandchild outline points even further down on the same page.',
        $body,
    )
    ->text('Target Section', new TextOptions(
        x: 72,
        y: 620,
        fontSize: 18,
        lineHeight: 22,
        spacingAfter: 6,
        fontName: StandardFont::HELVETICA_BOLD->value,
        color: Color::hex('#b45309'),
    ))
    ->text(
        'The child outline named "Target Section" uses explicit coordinates so the viewer opens this page close to the highlighted block.',
        new TextOptions(
            x: 72,
            y: 590,
            width: 360,
            fontSize: 11,
            lineHeight: 15,
            color: Color::hex('#334155'),
        ),
    )
    ->text('Implementation Notes', new TextOptions(
        x: 72,
        y: 520,
        fontSize: 16,
        lineHeight: 20,
        spacingAfter: 6,
        fontName: StandardFont::HELVETICA_BOLD->value,
        color: Color::hex('#7c2d12'),
    ))
    ->text(
        'This subsection bookmark demonstrates one additional nesting level. The current API keeps levels explicit, which makes the resulting hierarchy deterministic and easy to reason about.',
        new TextOptions(
            x: 72,
            y: 492,
            width: 360,
            fontSize: 11,
            lineHeight: 15,
            color: Color::hex('#334155'),
        ),
    )
    ->newPage()
    ->outlineAt('Appendix', 4)
    ->text('Appendix', $headline)
    ->text(
        'The last bookmark points to this page. Together the bookmarks form a small but complete nested outline tree with top-level items, child items and one grandchild item.',
        $body,
    )
    ->writeToFile($outputDirectory . '/outlines.pdf');
