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
    ->title('Advanced Outline Example')
    ->author('Kalle PDF')
    ->subject('Demonstrates styled outlines and additional destination types')
    ->language('en-US')
    ->creator('examples/outlines-advanced.php')
    ->creatorTool('pdf2')
    ->pageSize(PageSize::A4())
    ->margin($margin)
    ->addOutline(
        Outline::fit('Overview', 1)
            ->withStyle((new OutlineStyle())->withColor(Color::hex('#1d4ed8'))->withBold()),
    )
    ->text('Advanced Outlines', $headline)
    ->text(
        'This example combines styled bookmarks, Fit and FitH destinations, a FitR target and the child or sibling helper methods.',
        $body,
    )
    ->newPage()
    ->outline('Chapter 1')
    ->outlineChild('Section 1.1')
    ->outlineSiblingClosed('Section 1.2')
    ->text('Chapter 1', $headline)
    ->text(
        'The bookmark helpers make common hierarchy changes less mechanical than manually managing numeric levels.',
        $body,
    )
    ->newPage()
    ->addOutline(
        Outline::fitHorizontal('Top Band', 3, 720)
            ->italic()
            ->asGoToAction(),
    )
    ->text('Chapter 2', $headline)
    ->text(
        'This bookmark uses a GoTo action with a FitH destination. Many viewers will open this page aligned to the requested top value.',
        $body,
    )
    ->newPage()
    ->addOutline(
        Outline::fitRectangle('Focus Box', 4, 60, 240, 300, 520)
            ->withColor(Color::hex('#b45309'))
            ->bold()
            ->italic(),
    )
    ->text('Appendix', $headline)
    ->text(
        'The last bookmark uses FitR and requests a tighter visible rectangle inside the page instead of a full-page or single-coordinate destination.',
        $body,
    )
    ->writeToFile($outputDirectory . '/outlines-advanced.pdf');
