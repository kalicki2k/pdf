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
$headline = TextOptions::make(
    fontSize: 24,
    lineHeight: 28,
    spacingAfter: 8,
    fontName: StandardFont::HELVETICA_BOLD->value,
    color: Color::hex('#0f172a'),
);
$section = TextOptions::make(
    fontSize: 16,
    lineHeight: 20,
    spacingBefore: 10,
    spacingAfter: 6,
    fontName: StandardFont::HELVETICA_BOLD->value,
    color: Color::hex('#1d4ed8'),
);
$body = TextOptions::make(
    fontSize: 11,
    lineHeight: 15,
    spacingAfter: 8,
    color: Color::hex('#334155'),
);

DefaultDocumentBuilder::make()
    ->title('Open And Closed Outline Example')
    ->author('Kalle PDF')
    ->subject('Demonstrates open and closed PDF outline nodes')
    ->language('en-US')
    ->creator('examples/outlines-open-closed.php')
    ->creatorTool('pdf2')
    ->pageSize(PageSize::A4())
    ->margin($margin)
    ->outline('Overview')
    ->text('Open And Closed Outlines', $headline)
    ->text(
        'This example contains two top-level bookmark branches. The first one is open, so its children are visible immediately. The second one is closed, so the viewer should collapse its children until the user expands the node.',
        $body,
    )
    ->newPage()
    ->outlineAt('Open Chapter', 2)
    ->outlineAtLevel('Open Section 1', 2, 2, 72, 700)
    ->outlineAtLevel('Open Section 2', 2, 2, 72, 600)
    ->text('Open Chapter', $headline)
    ->text(
        'The bookmark branch for this chapter is open. Its child bookmarks should already be visible in the bookmarks panel of the PDF viewer.',
        $body,
    )
    ->text('Open Section 1', TextOptions::make(
        left: 72,
        bottom: 700,
        fontSize: 16,
        lineHeight: 20,
        fontName: StandardFont::HELVETICA_BOLD->value,
        color: Color::hex('#1d4ed8'),
    ))
    ->text(
        'This target belongs to an outline child under an open parent node.',
        TextOptions::make(
            left: 72,
            bottom: 676,
            width: 360,
            fontSize: 11,
            lineHeight: 15,
            color: Color::hex('#334155'),
        ),
    )
    ->text('Open Section 2', TextOptions::make(
        left: 72,
        bottom: 600,
        fontSize: 16,
        lineHeight: 20,
        fontName: StandardFont::HELVETICA_BOLD->value,
        color: Color::hex('#0f766e'),
    ))
    ->text(
        'This second child stays visible in the outline tree because its parent node is open.',
        TextOptions::make(
            left: 72,
            bottom: 576,
            width: 360,
            fontSize: 11,
            lineHeight: 15,
            color: Color::hex('#334155'),
        ),
    )
    ->newPage()
    ->outlineAtClosed('Closed Chapter', 3)
    ->outlineAtLevelClosed('Collapsed Section', 2, 3, 72, 690)
    ->outlineAtLevel('Nested Detail', 3, 3, 72, 590)
    ->text('Closed Chapter', $headline)
    ->text(
        'The bookmark branch for this chapter is closed. Its child outline items still exist, but the viewer should start with this subtree collapsed.',
        $body,
    )
    ->text('Collapsed Section', TextOptions::make(
        left: 72,
        bottom: 690,
        fontSize: 16,
        lineHeight: 20,
        fontName: StandardFont::HELVETICA_BOLD->value,
        color: Color::hex('#b45309'),
    ))
    ->text(
        'This section is itself closed and contains a nested child bookmark below.',
        TextOptions::make(
            left: 72,
            bottom: 666,
            width: 360,
            fontSize: 11,
            lineHeight: 15,
            color: Color::hex('#334155'),
        ),
    )
    ->text('Nested Detail', TextOptions::make(
        left: 72,
        bottom: 590,
        fontSize: 15,
        lineHeight: 19,
        fontName: StandardFont::HELVETICA_BOLD->value,
        color: Color::hex('#7c2d12'),
    ))
    ->text(
        'This bookmark target exists, but the closed ancestor path means it should not be expanded automatically when the document opens.',
        TextOptions::make(
            left: 72,
            bottom: 566,
            width: 360,
            fontSize: 11,
            lineHeight: 15,
            color: Color::hex('#334155'),
        ),
    )
    ->writeToFile($outputDirectory . '/outlines-open-closed.pdf');
