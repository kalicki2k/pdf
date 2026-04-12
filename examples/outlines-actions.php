<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Kalle\Pdf\Color\Color;
use Kalle\Pdf\Document\DefaultDocumentBuilder;
use Kalle\Pdf\Document\Outline;
use Kalle\Pdf\Document\OutlineStyle;
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
$body = new TextOptions(
    fontSize: 11,
    lineHeight: 15,
    spacingAfter: 8,
    color: Color::hex('#334155'),
);

DefaultDocumentBuilder::make()
    ->title('Outline Actions Example')
    ->author('Kalle PDF')
    ->subject('Demonstrates named destinations, GoToR actions and additional outline flags')
    ->language('en-US')
    ->creator('examples/outlines-actions.php')
    ->creatorTool('pdf2')
    ->pageSize(PageSize::A4())
    ->margin($margin)
    ->namedDestinationPosition('intro', 0, 760)
    ->addOutline(
        Outline::named('Introduction', 'intro', 1)->withStyle(
            (new OutlineStyle())
                ->withColor(Color::hex('#0369a1'))
                ->withBold()
                ->withAdditionalFlags(4),
        ),
    )
    ->paragraph('Introduction', $headline)
    ->paragraph(
        'The first bookmark targets a named destination on this page and adds custom viewer flags on top of bold styling.',
        $body,
    )
    ->newPage()
    ->namedDestination('chapter-2')
    ->addOutline(
        Outline::named('Open Chapter 2 Via GoTo', 'chapter-2', 2)->asGoToAction(),
    )
    ->paragraph('Chapter 2', $headline)
    ->paragraph(
        'This bookmark resolves through a named destination as a local GoTo action instead of a direct Dest entry.',
        $body,
    )
    ->newPage()
    ->addOutline(
        Outline::fit('External Appendix', 5)->withDestination(
            Outline::named('External Appendix', 'appendix', 5)
                ->destination
                ->asRemoteGoTo('reference-manual.pdf', true),
        ),
    )
    ->paragraph('External Reference', $headline)
    ->paragraph(
        'The last bookmark points into another PDF via a GoToR action and asks the viewer to open it in a new window.',
        $body,
    )
    ->writeToFile($outputDirectory . '/outlines-actions.pdf');
