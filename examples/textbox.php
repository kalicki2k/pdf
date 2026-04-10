<?php

declare(strict_types=1);

use Kalle\Pdf\Document;
use Kalle\Pdf\Layout\Geometry\Insets;
use Kalle\Pdf\Layout\Geometry\Position;
use Kalle\Pdf\Layout\Geometry\Rect;
use Kalle\Pdf\Layout\Page\PageSize;
use Kalle\Pdf\Layout\Page\Units;
use Kalle\Pdf\Layout\Text\Input\TextBoxOptions;
use Kalle\Pdf\Layout\Text\Input\TextOptions;
use Kalle\Pdf\Layout\Text\Input\TextSegment;
use Kalle\Pdf\Layout\Value\HorizontalAlign;
use Kalle\Pdf\Layout\Value\TextOverflow;
use Kalle\Pdf\Layout\Value\VerticalAlign;
use Kalle\Pdf\Page;
use Kalle\Pdf\Page\Link\LinkTarget;
use Kalle\Pdf\Profile;
use Kalle\Pdf\Style\Color;

require __DIR__ . '/../vendor/autoload.php';

$outputDir = __DIR__ . '/../var/examples';

if (!is_dir($outputDir) && !mkdir($outputDir, 0777, true) && !is_dir($outputDir)) {
    throw new RuntimeException(sprintf('Unable to create output directory "%s".', $outputDir));
}

$document = new Document(
    profile: Profile::standard(1.4),
    title: 'TextBox test',
    fontConfig: [
        [
            'baseFont' => 'NotoSans-Regular',
            'path' => __DIR__ . '/../assets/fonts/NotoSans-Regular.ttf',
            'unicode' => true,
            'subtype' => 'CIDFontType2',
            'encoding' => 'Identity-H',
        ],
    ],
)
    ->registerFont('Helvetica')
    ->registerFont('Helvetica-Bold')
    ->registerFont('Helvetica-Oblique')
    ->registerFont('Helvetica-BoldOblique')
    ->registerFont('Times-Roman')
    ->registerFont('Times-Bold')
    ->registerFont('Times-Italic')
    ->registerFont('Times-BoldItalic')
    ->registerFont('Courier')
    ->registerFont('Courier-Bold')
    ->registerFont('Courier-Oblique')
    ->registerFont('Courier-BoldOblique')
    ->registerFont('Symbol', encoding: 'SymbolEncoding')
    ->registerFont('ZapfDingbats', encoding: 'ZapfDingbatsEncoding')
    ->registerFont('NotoSans-Regular')
    ->addKeyword('textbox')
    ->addKeyword('layout')
    ->addKeyword('manual-test');

$boxes = [
    [
        'title' => 'Top align',
        'box' => new Rect(Units::mm(20), Units::mm(225), Units::mm(50), Units::mm(40)),
        'text' => "Line 1\nLine 2\nLine 3",
        'options' => new TextBoxOptions(
            lineHeight: Units::mm(5),
            verticalAlign: VerticalAlign::TOP,
            padding: Insets::all(Units::mm(3)),
        ),
    ],
    [
        'title' => 'Middle align',
        'box' => new Rect(Units::mm(80), Units::mm(225), Units::mm(50), Units::mm(40)),
        'text' => "Line 1\nLine 2",
        'options' => new TextBoxOptions(
            lineHeight: Units::mm(5),
            verticalAlign: VerticalAlign::MIDDLE,
            padding: Insets::all(Units::mm(3)),
        ),
    ],
    [
        'title' => 'Bottom align',
        'box' => new Rect(Units::mm(140), Units::mm(225), Units::mm(50), Units::mm(40)),
        'text' => "Line 1\nLine 2",
        'options' => new TextBoxOptions(
            lineHeight: Units::mm(5),
            verticalAlign: VerticalAlign::BOTTOM,
            padding: Insets::all(Units::mm(3)),
        ),
    ],
    [
        'title' => 'Center text',
        'box' => new Rect(Units::mm(20), Units::mm(175), Units::mm(50), Units::mm(35)),
        'text' => "Centered\nparagraph",
        'options' => new TextBoxOptions(
            lineHeight: Units::mm(5),
            align: HorizontalAlign::CENTER,
            verticalAlign: VerticalAlign::MIDDLE,
            padding: Insets::all(Units::mm(3)),
        ),
    ],
    [
        'title' => 'Right text',
        'box' => new Rect(Units::mm(80), Units::mm(175), Units::mm(50), Units::mm(35)),
        'text' => "Right aligned\ntext block",
        'options' => new TextBoxOptions(
            lineHeight: Units::mm(5),
            align: HorizontalAlign::RIGHT,
            padding: Insets::all(Units::mm(3)),
        ),
    ],
    [
        'title' => 'Ellipsis',
        'box' => new Rect(Units::mm(140), Units::mm(175), Units::mm(50), Units::mm(35)),
        'text' => 'This text is intentionally too long for this small box and should end with an ellipsis.',
        'options' => new TextBoxOptions(
            lineHeight: Units::mm(5),
            maxLines: 2,
            overflow: TextOverflow::ELLIPSIS,
            padding: Insets::all(Units::mm(3)),
        ),
    ],
    [
        'title' => 'Rich text',
        'box' => new Rect(Units::mm(20), Units::mm(120), Units::mm(80), Units::mm(40)),
        'text' => [
            TextSegment::plain('Status: '),
            TextSegment::colored('paid', Color::rgb(0, 140, 60)),
            TextSegment::plain("\nLink: "),
            TextSegment::link('example.com', LinkTarget::externalUrl('https://example.com')),
            TextSegment::plain("\n"),
            TextSegment::bold('Bold'),
            TextSegment::plain(' / '),
            TextSegment::italic('Italic'),
            TextSegment::plain(' / '),
            TextSegment::underlined('Underlined'),
        ],
        'options' => new TextBoxOptions(
            lineHeight: Units::mm(5),
            padding: Insets::all(Units::mm(3)),
        ),
    ],
    [
        'title' => 'Tight box',
        'box' => new Rect(Units::mm(110), Units::mm(120), Units::mm(80), Units::mm(18)),
        'text' => 'One very small box with too much text to inspect clipping behaviour.',
        'options' => new TextBoxOptions(
            lineHeight: Units::mm(4),
            verticalAlign: VerticalAlign::MIDDLE,
            maxLines: 1,
            overflow: TextOverflow::ELLIPSIS,
            padding: Insets::all(Units::mm(2)),
        ),
    ],
    [
        'title' => 'Justify text',
        'box' => new Rect(Units::mm(20), Units::mm(70), Units::mm(50), Units::mm(32)),
        'text' => 'Justified copy should stretch the first line but keep the last line natural.',
        'options' => new TextBoxOptions(
            lineHeight: Units::mm(5),
            align: HorizontalAlign::JUSTIFY,
            maxLines: 3,
            overflow: TextOverflow::ELLIPSIS,
            padding: Insets::all(Units::mm(3)),
        ),
    ],
    [
        'title' => 'Uneven padding',
        'box' => new Rect(Units::mm(80), Units::mm(70), Units::mm(50), Units::mm(32)),
        'text' => "Top left\nPadding test",
        'options' => new TextBoxOptions(
            lineHeight: Units::mm(5),
            verticalAlign: VerticalAlign::TOP,
            padding: new Insets(
                Units::mm(1),
                Units::mm(6),
                Units::mm(5),
                Units::mm(2),
            ),
        ),
    ],
    [
        'title' => 'Styled ellipsis',
        'box' => new Rect(Units::mm(140), Units::mm(70), Units::mm(50), Units::mm(32)),
        'text' => [
            TextSegment::plain('Mode: '),
            TextSegment::bold('bold bold bold bold'),
            TextSegment::plain(' / '),
            TextSegment::italic('italic italic italic'),
        ],
        'options' => new TextBoxOptions(
            lineHeight: Units::mm(5),
            maxLines: 2,
            overflow: TextOverflow::ELLIPSIS,
            padding: Insets::all(Units::mm(3)),
        ),
    ],
];

$renderPage = static function (
    Page $page,
    string $contentFontName,
    string $headline,
    string $subline,
) use ($boxes): void {
    $page->addText(
        $headline,
        new Position(Units::mm(20), Units::mm(285)),
        'Helvetica',
        16,
        new TextOptions(color: Color::rgb(20, 20, 20)),
    );

    $page->addText(
        $subline,
        new Position(Units::mm(20), Units::mm(278)),
        'Helvetica',
        10,
        new TextOptions(color: Color::gray(0.35)),
    );

    foreach ($boxes as $definition) {
        $box = $definition['box'];
        $options = $definition['options'];
        $contentLeft = $box->x + $options->padding->left;
        $contentRight = $box->x + $box->width - $options->padding->right;
        $contentBottom = $box->y + $options->padding->bottom;
        $contentTop = $box->y + $box->height - $options->padding->top;

        $page->addRectangle(
            $box,
            0.8,
            Color::gray(0.55),
            Color::gray(0.97),
        );

        $page->addLine(
            new Position($contentLeft, $contentBottom),
            new Position($contentLeft, $contentTop),
            0.35,
            Color::rgb(0, 120, 255),
        );

        $page->addLine(
            new Position($contentRight, $contentBottom),
            new Position($contentRight, $contentTop),
            0.35,
            Color::rgb(220, 60, 60),
        );

        $page->addText(
            $definition['title'],
            new Position($box->x, $box->y + $box->height + Units::mm(3)),
            'Helvetica',
            9,
            new TextOptions(color: Color::gray(0.25)),
        );

        $page->addTextBox(
            text: $definition['text'],
            box: $box,
            fontName: $contentFontName,
            size: 10,
            options: $options,
        );
    }
};

$standardFontPage = $document->addPage(PageSize::A4());
$renderPage(
    $standardFontPage,
    'Helvetica',
    'TextBox manual layout test - Helvetica',
    'Blue line = content left, red line = content right.',
);

$standardFonts = [
    'Helvetica',
    'Helvetica-Bold',
    'Helvetica-Oblique',
    'Helvetica-BoldOblique',
    'Times-Roman',
    'Times-Bold',
    'Times-Italic',
    'Times-BoldItalic',
    'Courier',
    'Courier-Bold',
    'Courier-Oblique',
    'Courier-BoldOblique',
    'Symbol',
    'ZapfDingbats',
];

foreach ($standardFonts as $standardFont) {
    $page = $document->addPage(PageSize::A4());

    $renderPage(
        $page,
        $standardFont,
        'TextBox manual layout test - ' . $standardFont,
        'Same boxes with PDF standard font metrics.',
    );
}

$embeddedFontPage = $document->addPage(PageSize::A4());
$renderPage(
    $embeddedFontPage,
    'NotoSans-Regular',
    'TextBox manual layout test - NotoSans-Regular',
    'Same boxes with embedded font metrics for direct comparison.',
);

$outputPath = $outputDir . '/test-textbox.pdf';
$document->writeToFile($outputPath);

printf('Generated %s%s', $outputPath, PHP_EOL);
