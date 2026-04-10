<?php

declare(strict_types=1);

use Kalle\Pdf\Document;
use Kalle\Pdf\Internal\Layout\Geometry\Position;
use Kalle\Pdf\Internal\Layout\Page\PageSize;
use Kalle\Pdf\Internal\Layout\Page\Units;
use Kalle\Pdf\Internal\Layout\Table\Definition\TableCaption;
use Kalle\Pdf\Internal\Layout\Table\Definition\TableCell;
use Kalle\Pdf\Internal\Layout\Table\Definition\TableHeaderScope;
use Kalle\Pdf\Internal\Layout\Table\Style\HeaderStyle;
use Kalle\Pdf\Internal\Layout\Table\Style\TableBorder;
use Kalle\Pdf\Internal\Layout\Table\Style\TablePadding;
use Kalle\Pdf\Internal\Layout\Table\Style\TableStyle;
use Kalle\Pdf\Internal\Layout\Text\Input\ParagraphOptions;
use Kalle\Pdf\Internal\Layout\Text\Input\TextOptions;
use Kalle\Pdf\Internal\Style\Color;
use Kalle\Pdf\Internal\TaggedPdf\StructureTag;
use Kalle\Pdf\Profile;

require __DIR__ . '/../vendor/autoload.php';

$outputDir = __DIR__ . '/../var/examples';

if (!is_dir($outputDir) && !mkdir($outputDir, 0777, true) && !is_dir($outputDir)) {
    throw new RuntimeException(sprintf('Unable to create output directory "%s".', $outputDir));
}

$startedAt = microtime(true);

$document = new Document(
    profile: Profile::pdfUa1(),
    title: 'Multipage Table Caption Example',
    author: 'kalle/pdf',
    subject: 'Multipage table example with caption, repeated header row and row headers',
    language: 'en-US',
    creator: 'Example Script',
    creatorTool: 'examples/table-caption-pagination.php',
    fontConfig: [
        [
            'baseFont' => 'NotoSans-Regular',
            'path' => __DIR__ . '/../assets/fonts/NotoSans-Regular.ttf',
            'unicode' => true,
            'subtype' => 'CIDFontType2',
            'encoding' => 'Identity-H',
        ],
        [
            'baseFont' => 'NotoSans-Bold',
            'path' => __DIR__ . '/../assets/fonts/NotoSans-Bold.ttf',
            'unicode' => true,
            'subtype' => 'CIDFontType2',
            'encoding' => 'Identity-H',
        ],
    ],
);

$document
    ->addKeyword('table')
    ->addKeyword('caption')
    ->addKeyword('pagination')
    ->addKeyword('pdf-ua')
    ->registerFont('NotoSans-Regular')
    ->registerFont('NotoSans-Bold');

$page = $document->addPage(PageSize::custom(Units::mm(160), Units::mm(150)));

$page->addText(
    'Multipage Table Caption Example',
    new Position(Units::mm(12), Units::mm(138)),
    'NotoSans-Bold',
    16,
    new TextOptions(
        structureTag: StructureTag::Heading1,
        color: Color::rgb(25, 45, 90),
    ),
);

$page->createTextFrame(
    new Position(Units::mm(12), Units::mm(126)),
    Units::mm(136),
    Units::mm(22),
)
    ->addParagraph(
        'This example keeps the caption on the first page while the header row repeats and the first body column remains tagged as row headers across later pages.',
        'NotoSans-Regular',
        10,
        new ParagraphOptions(
            structureTag: StructureTag::Paragraph,
            lineHeight: Units::mm(5),
            spacingAfter: Units::mm(3),
        ),
    );

$table = $page->createTable(
    new Position(Units::mm(12), Units::mm(102)),
    Units::mm(136),
    [
        Units::mm(34),
        Units::mm(34),
        Units::mm(34),
        Units::mm(34),
    ],
    Units::mm(10),
);

$table
    ->font('NotoSans-Regular', 9)
    ->caption(new TableCaption(
        'Regional service quality by quarter',
        fontName: 'NotoSans-Bold',
        size: 11,
        color: Color::rgb(30, 55, 100),
        spacingAfter: Units::mm(2),
    ))
    ->style(new TableStyle(
        padding: TablePadding::symmetric(Units::mm(2), Units::mm(1.4)),
        border: TableBorder::all(color: Color::gray(0.75)),
    ))
    ->headerStyle(new HeaderStyle(
        fillColor: Color::rgb(232, 238, 250),
        textColor: Color::gray(0.15),
    ))
    ->addHeaderRow([
        new TableCell('Region', headerScope: TableHeaderScope::Both),
        'January',
        'February',
        'March',
    ]);

$rows = [
    ['North', '98 %', '97 %', '99 %'],
    ['South', '94 %', '95 %', '96 %'],
    ['West', '99 %', '98 %', '97 %'],
    ['East', '96 %', '97 %', '95 %'],
    ['Central', '93 %', '94 %', '95 %'],
    ['Coastal', '97 %', '96 %', '98 %'],
    ['Mountain', '95 %', '94 %', '96 %'],
    ['Metro', '99 %', '99 %', '98 %'],
    ['Rural', '92 %', '93 %', '94 %'],
    ['Delta', '96 %', '95 %', '97 %'],
    ['Harbor', '98 %', '97 %', '99 %'],
    ['Valley', '94 %', '95 %', '95 %'],
    ['North', '98 %', '97 %', '99 %'],
    ['South', '94 %', '95 %', '96 %'],
    ['West', '99 %', '98 %', '97 %'],
    ['East', '96 %', '97 %', '95 %'],
    ['Central', '93 %', '94 %', '95 %'],
    ['Coastal', '97 %', '96 %', '98 %'],
    ['Mountain', '95 %', '94 %', '96 %'],
    ['Metro', '99 %', '99 %', '98 %'],
    ['Rural', '92 %', '93 %', '94 %'],
    ['Delta', '96 %', '95 %', '97 %'],
    ['Harbor', '98 %', '97 %', '99 %'],
    ['Valley', '94 %', '95 %', '95 %'],
];

foreach ($rows as [$region, $january, $february, $march]) {
    $table->addRow([
        new TableCell($region, headerScope: TableHeaderScope::Row),
        $january,
        $february,
        $march,
    ]);
}

$targetPath = $outputDir . '/table-caption-pagination_' . date('Y-m-d-H-i-s') . '.pdf';
$document->writeToFile($targetPath);

printf(
    "Erstellt in %.3f Sekunden: %s\n",
    microtime(true) - $startedAt,
    $targetPath,
);
