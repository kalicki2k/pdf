<?php

declare(strict_types=1);

use Kalle\Pdf\Document;
use Kalle\Pdf\Document\Geometry\Position;
use Kalle\Pdf\Feature\Table\Style\HeaderStyle;
use Kalle\Pdf\Feature\Table\Style\TableBorder;
use Kalle\Pdf\Feature\Table\Style\TablePadding;
use Kalle\Pdf\Feature\Table\Style\TableStyle;
use Kalle\Pdf\Feature\Table\TableCaption;
use Kalle\Pdf\Feature\Table\TableCell;
use Kalle\Pdf\Feature\Table\TableHeaderScope;
use Kalle\Pdf\Feature\Text\ParagraphOptions;
use Kalle\Pdf\Feature\Text\StructureTag;
use Kalle\Pdf\Feature\Text\TextOptions;
use Kalle\Pdf\Graphics\Color;
use Kalle\Pdf\Layout\PageSize;
use Kalle\Pdf\Layout\Units;
use Kalle\Pdf\Profile;

require __DIR__ . '/../vendor/autoload.php';

$outputDir = __DIR__ . '/../var/examples';

if (!is_dir($outputDir) && !mkdir($outputDir, 0777, true) && !is_dir($outputDir)) {
    throw new RuntimeException(sprintf('Unable to create output directory "%s".', $outputDir));
}

$startedAt = microtime(true);

$document = new Document(
    profile: Profile::pdfUa1(),
    title: 'Table Span Break Example',
    author: 'kalle/pdf',
    subject: 'Multipage table example with rowspan and colspan groups under break pressure',
    language: 'en-US',
    creator: 'Example Script',
    creatorTool: 'examples/table-span-breaks.php',
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
    ->addKeyword('rowspan')
    ->addKeyword('colspan')
    ->addKeyword('pagination')
    ->addKeyword('pdf-ua')
    ->registerFont('NotoSans-Regular')
    ->registerFont('NotoSans-Bold');

$page = $document->addPage(PageSize::custom(Units::mm(160), Units::mm(180)));

$page->addText(
    'Span Groups Under Break Pressure',
    new Position(Units::mm(12), Units::mm(168)),
    'NotoSans-Bold',
    16,
    new TextOptions(
        structureTag: StructureTag::Heading1,
        color: Color::rgb(25, 45, 90),
    ),
);

$page->createTextFrame(
    new Position(Units::mm(12), Units::mm(156)),
    Units::mm(136),
    Units::mm(18),
)
    ->addParagraph(
        'This example combines repeated headers, rowspan groups, colspan summaries and longer monthly notes over multiple pages.',
        'NotoSans-Regular',
        10,
        new ParagraphOptions(
            structureTag: StructureTag::Paragraph,
            lineHeight: Units::mm(5),
            spacingAfter: Units::mm(2),
        ),
    );

$table = $page->createTable(
    new Position(Units::mm(12), Units::mm(136)),
    Units::mm(136),
    [
        Units::mm(16),
        Units::mm(18),
        Units::mm(34),
        Units::mm(34),
        Units::mm(34),
    ],
    Units::mm(8),
);

$table
    ->font('NotoSans-Regular', 8)
    ->caption(new TableCaption(
        'Regional monthly service span review',
        fontName: 'NotoSans-Bold',
        size: 10,
        color: Color::rgb(30, 55, 100),
        spacingAfter: Units::mm(2),
    ))
    ->style(new TableStyle(
        padding: TablePadding::symmetric(Units::mm(1.6), Units::mm(1.1)),
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
    [
        'North',
        'Availability review',
        '98 %',
        '97 %',
        '99 %',
        'Follow-up action',
        '1.2 h',
        '1.1 h',
        '1.0 h',
        'North summary',
        'North remains stable overall, but the reconciled figures, closeout note and owner handover still need to remain tied to one evidence set.',
    ],
    [
        'South',
        'Availability review',
        '94 %',
        '95 %',
        '96 %',
        'Follow-up action',
        '1.8 h',
        '1.6 h',
        '1.5 h',
        'South summary',
        'South is close to completion, but the rollout history, remote branch notes and final acknowledgements still need one consistent summary.',
    ],
] as [
    $region,
    $firstMetric,
    $janFirst,
    $febFirst,
    $marFirst,
    $secondMetric,
    $janSecond,
    $febSecond,
    $marSecond,
    $summaryLabel,
    $summaryText,
]) {
    $table->addRow([
        new TableCell($region, rowspan: 2, headerScope: TableHeaderScope::Row),
        $firstMetric,
        $janFirst,
        $febFirst,
        $marFirst,
    ]);
    $table->addRow([
        $secondMetric,
        $janSecond,
        $febSecond,
        $marSecond,
    ]);
    $table->addRow([
        new TableCell($summaryLabel, headerScope: TableHeaderScope::Row),
        new TableCell($summaryText, colspan: 4),
    ]);
}

$targetPath = $outputDir . '/table-span-breaks_' . date('Y-m-d-H-i-s') . '.pdf';
$document->writeToFile($targetPath);

printf(
    "Erstellt in %.3f Sekunden: %s\n",
    microtime(true) - $startedAt,
    $targetPath,
);
