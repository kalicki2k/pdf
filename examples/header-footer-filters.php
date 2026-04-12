<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Kalle\Pdf\Color\Color;
use Kalle\Pdf\Document\DefaultDocumentBuilder;
use Kalle\Pdf\Document\PageDecorationContext;
use Kalle\Pdf\Drawing\Units;
use Kalle\Pdf\Font\StandardFont;
use Kalle\Pdf\Page\Margin;
use Kalle\Pdf\Page\PageSize;
use Kalle\Pdf\Text\TextAlign;
use Kalle\Pdf\Text\TextOptions;

$outputDirectory = __DIR__ . '/../var/examples';

if (!is_dir($outputDirectory) && !mkdir($outputDirectory, 0777, true) && !is_dir($outputDirectory)) {
    throw new RuntimeException('Unable to create example output directory.');
}

$margin = Margin::all(Units::mm(18));
$titleColor = Color::hex('#0f172a');
$accentColor = Color::hex('#1d4ed8');
$bodyColor = Color::hex('#334155');
$mutedColor = Color::hex('#64748b');
$warningColor = Color::hex('#b45309');

$builder = DefaultDocumentBuilder::make()
    ->title('Filtered Header and Footer Example')
    ->author('Kalle PDF')
    ->subject('Demonstrates pageNumbers(), headerOn() and footerOn() in pdf2')
    ->language('en-US')
    ->creator('examples/header-footer-filters.php')
    ->creatorTool('pdf2')
    ->pageSize(PageSize::A4())
    ->margin($margin)
    ->pageNumbers(
        new TextOptions(
            x: $margin->left,
            y: $margin->bottom - 2,
            width: PageSize::A4()->width() - $margin->left - $margin->right,
            fontSize: 10,
            lineHeight: 12,
            fontName: StandardFont::HELVETICA->value,
            color: $mutedColor,
            align: TextAlign::RIGHT,
        ),
        'Page {{page}} of {{pages}}',
    )
    ->headerOn(
        static fn (PageDecorationContext $page): bool => !$page->isFirstPage(),
        static function (PageDecorationContext $page): void {
            $page->text('Operations Manual', new TextOptions(
                x: $page->page()->contentArea()->left,
                y: $page->page()->contentArea()->top,
                fontSize: 11,
                lineHeight: 13,
                fontName: StandardFont::HELVETICA_BOLD->value,
                color: Color::hex('#1d4ed8'),
            ));
        },
    )
    ->footerOn(
        static fn (PageDecorationContext $page): bool => $page->pageNumber() % 2 === 0,
        static function (PageDecorationContext $page): void {
            $page->text('Internal review copy', new TextOptions(
                x: $page->page()->contentArea()->left,
                y: $page->page()->contentArea()->bottom + 10,
                fontSize: 9,
                lineHeight: 11,
                fontName: StandardFont::HELVETICA_BOLD->value,
                color: Color::hex('#b45309'),
            ));
        },
    )
    ->text('Predicate-Based Page Decoration', new TextOptions(
        fontSize: 24,
        lineHeight: 28,
        spacingAfter: 8,
        fontName: StandardFont::HELVETICA_BOLD->value,
        color: $titleColor,
    ))
    ->text(
        'This example combines the pageNumbers() convenience helper with predicate-based headerOn() and footerOn() callbacks. The header is skipped on the cover page, while a small footer notice is shown only on even pages.',
        new TextOptions(
            fontSize: 11,
            lineHeight: 16,
            spacingAfter: 12,
            color: $bodyColor,
        ),
    )
    ->text('Cover Page', new TextOptions(
        fontSize: 16,
        lineHeight: 20,
        spacingAfter: 6,
        fontName: StandardFont::HELVETICA_BOLD->value,
        color: $warningColor,
    ))
    ->text(
        'The first page keeps only the generated page number. The filtered header starts on the next page to leave the opening layout clean.',
        new TextOptions(
            fontSize: 11,
            lineHeight: 16,
            spacingAfter: 10,
            color: $bodyColor,
        ),
    );

$sections = [
    'Operating Principles' => 'Page two introduces the regular running header. The predicate receives the PageDecorationContext, so the check can use pageNumber(), isFirstPage() or isLastPage() without duplicating total-page logic.',
    'Review Notes' => 'Because the page number helper already knows the total page count, it is a better fit than rebuilding page numbering inside a manual footer callback. The filtered footer still adds a second, independent decoration layer on even pages only.',
    'Appendix' => str_repeat(
        'Longer appendix content is useful here because it shows that the helpers continue to work across multiple generated pages without extra state management in user code. ',
        14,
    ),
];

foreach ($sections as $title => $body) {
    $builder = $builder
        ->newPage()
        ->text($title, new TextOptions(
            fontSize: 16,
            lineHeight: 20,
            spacingBefore: 10,
            spacingAfter: 6,
            fontName: StandardFont::HELVETICA_BOLD->value,
            color: $accentColor,
        ))
        ->text($body, new TextOptions(
            fontSize: 11,
            lineHeight: 16,
            spacingAfter: 10,
            color: $bodyColor,
        ));
}

$builder->writeToFile($outputDirectory . '/header-footer-filters.pdf');
