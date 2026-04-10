<?php

declare(strict_types=1);

use Kalle\Pdf\Document;
use Kalle\Pdf\Feature\Table\Style\HeaderStyle;
use Kalle\Pdf\Feature\Table\Style\TableBorder;
use Kalle\Pdf\Feature\Table\Style\TablePadding;
use Kalle\Pdf\Feature\Table\Style\TableStyle;
use Kalle\Pdf\Feature\Table\TableCaption;
use Kalle\Pdf\Feature\Table\TableCell;
use Kalle\Pdf\Feature\Table\TableHeaderScope;
use Kalle\Pdf\Feature\Text\ParagraphOptions;
use Kalle\Pdf\Feature\Text\TextOptions;
use Kalle\Pdf\Geometry\Position;
use Kalle\Pdf\Graphics\Color;
use Kalle\Pdf\Layout\PageSize;
use Kalle\Pdf\Layout\Units;
use Kalle\Pdf\Profile;
use Kalle\Pdf\Structure\StructureTag;

require __DIR__ . '/../vendor/autoload.php';

$outputDir = __DIR__ . '/../var/examples';

if (!is_dir($outputDir) && !mkdir($outputDir, 0777, true) && !is_dir($outputDir)) {
    throw new RuntimeException(sprintf('Unable to create output directory "%s".', $outputDir));
}

$startedAt = microtime(true);

$document = new Document(
    profile: Profile::pdfUa1(),
    title: 'Narrow Column Table Example',
    author: 'kalle/pdf',
    subject: 'Compact table example with empty cells and hard unbreakable tokens',
    language: 'en-US',
    creator: 'Example Script',
    creatorTool: 'examples/table-narrow-columns.php',
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
    ->addKeyword('narrow-columns')
    ->addKeyword('empty-cells')
    ->addKeyword('unbreakable-tokens')
    ->addKeyword('pdf-ua')
    ->registerFont('NotoSans-Regular')
    ->registerFont('NotoSans-Bold');

$page = $document->addPage(PageSize::custom(Units::mm(120), Units::mm(140)));

$page->addText(
    'Narrow Column Stress Table',
    new Position(Units::mm(10), Units::mm(128)),
    'NotoSans-Bold',
    15,
    new TextOptions(
        structureTag: StructureTag::Heading1,
        color: Color::rgb(25, 45, 90),
    ),
);

$page->createTextFrame(
    new Position(Units::mm(10), Units::mm(116)),
    Units::mm(100),
    Units::mm(16),
)
    ->addParagraph(
        'This example stresses compact columns, empty cells and long hard tokens in one tagged table.',
        'NotoSans-Regular',
        9,
        new ParagraphOptions(
            structureTag: StructureTag::Paragraph,
            lineHeight: Units::mm(4.5),
            spacingAfter: Units::mm(2),
        ),
    );

$table = $page->createTable(
    new Position(Units::mm(10), Units::mm(96)),
    Units::mm(100),
    [
        Units::mm(12),
        Units::mm(14),
        Units::mm(18),
        Units::mm(14),
        Units::mm(42),
    ],
    Units::mm(7),
);

$table
    ->font('NotoSans-Regular', 8)
    ->caption(new TableCaption(
        'Compact issue constraint log',
        fontName: 'NotoSans-Bold',
        size: 10,
        color: Color::rgb(30, 55, 100),
        spacingAfter: Units::mm(2),
    ))
    ->style(new TableStyle(
        padding: TablePadding::symmetric(Units::mm(1.4), Units::mm(1.0)),
        border: TableBorder::all(color: Color::gray(0.75)),
    ))
    ->headerStyle(new HeaderStyle(
        fillColor: Color::rgb(232, 238, 250),
        textColor: Color::gray(0.15),
    ))
    ->addHeaderRow([
        'Area',
        'Queue',
        'Constraint token',
        'Owner',
        'Action',
    ]);

foreach ([
    ['North', '', 'INC2026ALPHAOMEGA0004711', 'Ops', 'Escalate owner handover and capture the aftercare notes before Friday.'],
    ['South', 'Review', '', '', 'Keep the branch note open until the external approval arrives.'],
    ['West', 'Backlog', 'REGIONALHANDOVERALPHA2026040801', 'Team', 'Consolidate duplicated notes and publish the shortened exception list.'],
    ['East', '', 'SUPPLIERCHAINBLOCKER202604081245', 'Vendor', 'Align the contingency owners and archive the obsolete workaround memo.'],
    ['Central', 'Stable', '', 'Office', ''],
    ['Coastal', 'Watch', 'REMOTESITEFOLLOWUPTOKEN20260408Z', '', 'Refresh the fallback roster and confirm the weather escalation path.'],
] as [$area, $queue, $constraintToken, $owner, $action]) {
    $table->addRow([
        new TableCell($area, headerScope: TableHeaderScope::Row),
        $queue,
        $constraintToken,
        $owner,
        $action,
    ]);
}

$targetPath = $outputDir . '/table-narrow-columns_' . date('Y-m-d-H-i-s') . '.pdf';
$document->writeToFile($targetPath);

printf(
    "Erstellt in %.3f Sekunden: %s\n",
    microtime(true) - $startedAt,
    $targetPath,
);
