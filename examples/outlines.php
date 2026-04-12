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
    ->subject('Demonstrates document outlines across multiple pages')
    ->language('en-US')
    ->creator('examples/outlines.php')
    ->creatorTool('pdf2')
    ->pageSize(PageSize::A4())
    ->margin($margin)
    ->outline('Overview')
    ->paragraph('Outline Example', $headline)
    ->paragraph(
        'This example creates several top-level PDF bookmarks. The first bookmark points to the current page, later bookmarks jump to explicit pages and positions.',
        $body,
    )
    ->paragraph('Overview', $section)
    ->paragraph(
        'Use the bookmarks panel in your PDF viewer to jump between the pages in this document. The ordering matches the API call sequence.',
        $body,
    )
    ->newPage()
    ->outlineAt('Chapter 1', 2)
    ->paragraph('Chapter 1', $headline)
    ->paragraph(
        'A top-level outline can target a whole page. In this first iteration pdf2 writes explicit /XYZ destinations and uses the page top when no coordinates are given.',
        $body,
    )
    ->paragraph('Highlights', $section)
    ->paragraph(
        'The implementation currently supports only top-level items, which keeps the object model and serializer changes small and predictable.',
        $body,
    )
    ->newPage()
    ->paragraph('Chapter 2', $headline)
    ->paragraph(
        'This page contains a more precise bookmark target. The next outline points to the section below instead of the page top.',
        $body,
    )
    ->paragraph('Target Section', new TextOptions(
        x: 72,
        y: 620,
        fontSize: 18,
        lineHeight: 22,
        spacingAfter: 6,
        fontName: StandardFont::HELVETICA_BOLD->value,
        color: Color::hex('#b45309'),
    ))
    ->paragraph(
        'The outline named "Chapter 2 Section" uses explicit coordinates so the viewer opens this page close to the highlighted block.',
        new TextOptions(
            x: 72,
            y: 590,
            width: 360,
            fontSize: 11,
            lineHeight: 15,
            color: Color::hex('#334155'),
        ),
    )
    ->outlineAt('Chapter 2 Section', 3, 72, 620)
    ->newPage()
    ->outlineAt('Appendix', 4)
    ->paragraph('Appendix', $headline)
    ->paragraph(
        'The last bookmark points to this page. Together the four bookmarks form a minimal but complete outline tree with a single root and multiple top-level items.',
        $body,
    )
    ->writeToFile($outputDirectory . '/outlines.pdf');
