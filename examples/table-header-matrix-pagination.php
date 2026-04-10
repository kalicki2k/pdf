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
use Kalle\Pdf\Internal\Style\Color;
use Kalle\Pdf\Internal\TaggedPdf\StructureTag;
use Kalle\Pdf\Profile;
use Kalle\Pdf\Text\ParagraphOptions;
use Kalle\Pdf\Text\TextOptions;

require __DIR__ . '/../vendor/autoload.php';

$outputDir = __DIR__ . '/../var/examples';

if (!is_dir($outputDir) && !mkdir($outputDir, 0777, true) && !is_dir($outputDir)) {
    throw new RuntimeException(sprintf('Unable to create output directory "%s".', $outputDir));
}

$startedAt = microtime(true);

$document = new Document(
    profile: Profile::pdfUa1(),
    title: 'Multipage Table Header Matrix Example',
    author: 'kalle/pdf',
    subject: 'Multipage table example with caption, grouped column headers and row headers',
    language: 'en-US',
    creator: 'Example Script',
    creatorTool: 'examples/table-header-matrix-pagination.php',
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
    ->addKeyword('header-matrix')
    ->addKeyword('caption')
    ->addKeyword('pdf-ua')
    ->registerFont('NotoSans-Regular')
    ->registerFont('NotoSans-Bold');

$page = $document->addPage(PageSize::custom(Units::mm(160), Units::mm(150)));

$page->addText(
    'Multipage Table Header Matrix',
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
        'This example keeps a caption on the first page while two header rows repeat. The top row groups columns, the second row names the metrics, and the first body column stays tagged as row headers.',
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
        Units::mm(24),
        Units::mm(28),
        Units::mm(28),
        Units::mm(28),
        Units::mm(28),
    ],
    Units::mm(10),
);

$table
    ->font('NotoSans-Regular', 9)
    ->caption(new TableCaption(
        'Regional service review matrix',
        fontName: 'NotoSans-Bold',
        size: 11,
        color: Color::rgb(30, 55, 100),
        spacingAfter: Units::mm(2),
    ))
    ->style(new TableStyle(
        padding: TablePadding::symmetric(Units::mm(1.8), Units::mm(1.2)),
        border: TableBorder::all(color: Color::gray(0.75)),
    ))
    ->headerStyle(new HeaderStyle(
        fillColor: Color::rgb(232, 238, 250),
        textColor: Color::gray(0.15),
    ))
    ->addHeaderRow([
        new TableCell('Region', rowspan: 2, headerScope: TableHeaderScope::Both),
        new TableCell('Service quality', colspan: 2, headerScope: TableHeaderScope::Column),
        new TableCell('Follow-up', colspan: 2, headerScope: TableHeaderScope::Column),
    ])
    ->addHeaderRow([
        'Availability',
        'Response time',
        'Escalations',
        'Resolved',
    ]);

foreach ([
    ['North', '98 %', '1.2 h', '2', '18'],
    ['South', '94 %', '1.8 h', '4', '14'],
    ['West', '99 %', '0.9 h', '1', '20'],
    ['East', '96 %', '1.4 h', '3', '16'],
    ['Central', '93 %', '2.1 h', '5', '12'],
    ['Coastal', '97 %', '1.0 h', '2', '19'],
    ['Mountain', '95 %', '1.5 h', '3', '15'],
    ['Metro', '99 %', '0.8 h', '1', '21'],
    ['Rural', '92 %', '2.3 h', '6', '11'],
    ['Delta', '96 %', '1.3 h', '2', '17'],
    ['Harbor', '98 %', '1.1 h', '1', '20'],
    ['Valley', '94 %', '1.9 h', '4', '13'],
] as [$region, $availability, $responseTime, $escalations, $resolved]) {
    $table->addRow([
        new TableCell($region, headerScope: TableHeaderScope::Row),
        $availability,
        $responseTime,
        $escalations,
        $resolved,
    ]);
}

$targetPath = $outputDir . '/table-header-matrix-pagination_' . date('Y-m-d-H-i-s') . '.pdf';
$document->writeToFile($targetPath);

printf(
    "Erstellt in %.3f Sekunden: %s\n",
    microtime(true) - $startedAt,
    $targetPath,
);
