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

$headerOptions = TextOptions::make(
    x: $margin->left,
    y: PageSize::A4()->height() - $margin->top,
    fontSize: 11,
    lineHeight: 13,
    fontName: StandardFont::HELVETICA_BOLD->value,
    color: $accentColor,
);
$footerOptions = TextOptions::make(
    x: $margin->left,
    y: $margin->bottom - 2,
    width: PageSize::A4()->width() - $margin->left - $margin->right,
    fontSize: 10,
    lineHeight: 12,
    fontName: StandardFont::HELVETICA->value,
    color: $mutedColor,
    align: TextAlign::RIGHT,
);

$builder = DefaultDocumentBuilder::make()
    ->title('Header and Footer Example')
    ->author('Kalle PDF')
    ->subject('Demonstrates document-wide page decoration callbacks in pdf2')
    ->language('en-US')
    ->creator('examples/header-footer.php')
    ->creatorTool('pdf2')
    ->pageSize(PageSize::A4())
    ->margin($margin)
    ->header(static function (PageDecorationContext $page, int $pageNumber) use ($headerOptions): void {
        $page->text('Quarterly Operations Report', $headerOptions);
        $page->text('Section ' . $pageNumber, TextOptions::make(
            x: $page->page()->contentArea()->right - 90,
            y: $page->page()->contentArea()->top,
            width: 90,
            fontSize: 10,
            lineHeight: 12,
            color: Color::hex('#475569'),
            align: TextAlign::RIGHT,
        ));
    })
    ->footer(static function (PageDecorationContext $page, int $pageNumber) use ($footerOptions): void {
        $page->text('Page ' . $pageNumber, $footerOptions);
    })
    ->text('Header / Footer Callbacks', TextOptions::make(
        fontSize: 24,
        lineHeight: 28,
        spacingAfter: 8,
        fontName: StandardFont::HELVETICA_BOLD->value,
        color: $titleColor,
    ))
    ->text(
        'This example registers a document-wide header and footer callback. Both are applied to every generated page, including explicit new pages and pages created through overflow.',
        TextOptions::make(
            fontSize: 11,
            lineHeight: 16,
            spacingAfter: 12,
            color: $bodyColor,
        ),
    );

$sections = [
    'Summary' => 'The report starts with a regular content page. The header is rendered before the body, while the footer is appended after the normal page content.',
    'Automatic Overflow' => str_repeat(
        'A longer flowing paragraph forces pdf2 to continue onto a following page while keeping the same header and footer callbacks active. ',
        18,
    ),
    'Explicit Page Break' => 'The last section uses newPage() explicitly. The document-level callbacks still run because they are attached to the final page list rather than only to the current flow state.',
];

$firstSection = true;

foreach ($sections as $title => $body) {
    if (!$firstSection) {
        $builder = $builder->newPage();
    }

    $builder = $builder
        ->text($title, TextOptions::make(
            fontSize: 16,
            lineHeight: 20,
            spacingBefore: 10,
            spacingAfter: 6,
            fontName: StandardFont::HELVETICA_BOLD->value,
            color: $accentColor,
        ))
        ->text($body, TextOptions::make(
            fontSize: 11,
            lineHeight: 16,
            spacingAfter: 10,
            color: $bodyColor,
        ));

    $firstSection = false;
}

$builder->writeToFile($outputDirectory . '/header-footer.pdf');
