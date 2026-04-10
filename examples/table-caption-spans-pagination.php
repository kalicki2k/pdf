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
    title: 'Multipage Table Span Example',
    author: 'kalle/pdf',
    subject: 'Multipage table example with caption, repeated header row, row headers, rowspan and colspan',
    language: 'en-US',
    creator: 'Example Script',
    creatorTool: 'examples/table-caption-spans-pagination.php',
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
    ->addKeyword('rowspan')
    ->addKeyword('colspan')
    ->addKeyword('pdf-ua')
    ->registerFont('NotoSans-Regular')
    ->registerFont('NotoSans-Bold');

$page = $document->addPage(PageSize::custom(Units::mm(160), Units::mm(150)));

$page->addText(
    'Multipage Table Span Example',
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
        'This example keeps the caption on page one while the header row repeats. Each region is a row header group with rowspan, and each group ends with a summary row using colspan.',
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
        Units::mm(24),
        Units::mm(29.333),
        Units::mm(29.333),
        Units::mm(29.334),
    ],
    Units::mm(10),
);

$table
    ->font('NotoSans-Regular', 9)
    ->caption(new TableCaption(
        'Regional service quality and follow-up metrics',
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
        new TableCell('Region', headerScope: TableHeaderScope::Both),
        'Metric',
        'January',
        'February',
        'March',
    ]);

foreach ([
    ['North', '98 %', '97 %', '99 %', '1.2 h', '1.1 h', '1.0 h'],
    ['South', '94 %', '95 %', '96 %', '1.8 h', '1.6 h', '1.5 h'],
    ['West', '99 %', '98 %', '97 %', '0.9 h', '1.0 h', '1.1 h'],
    ['East', '96 %', '97 %', '95 %', '1.4 h', '1.3 h', '1.4 h'],
    ['Central', '93 %', '94 %', '95 %', '2.1 h', '1.9 h', '1.8 h'],
    ['Coastal', '97 %', '96 %', '98 %', '1.0 h', '1.1 h', '1.0 h'],
] as [$region, $janAvailability, $febAvailability, $marAvailability, $janResponse, $febResponse, $marResponse]) {
    $table->addRow([
        new TableCell($region, rowspan: 2, headerScope: TableHeaderScope::Row),
        'Availability',
        $janAvailability,
        $febAvailability,
        $marAvailability,
    ]);
    $table->addRow([
        'Response time',
        $janResponse,
        $febResponse,
        $marResponse,
    ]);
    $table->addRow([
        new TableCell($region . ' summary', headerScope: TableHeaderScope::Row),
        new TableCell(
            'Stable service quality with a dedicated follow-up note across all reported months.',
            colspan: 4,
        ),
    ]);
}

$targetPath = $outputDir . '/table-caption-spans-pagination_' . date('Y-m-d-H-i-s') . '.pdf';
$document->writeToFile($targetPath);

printf(
    "Erstellt in %.3f Sekunden: %s\n",
    microtime(true) - $startedAt,
    $targetPath,
);
