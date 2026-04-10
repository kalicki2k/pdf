<?php

declare(strict_types=1);

use Kalle\Pdf\Document;
use Kalle\Pdf\Geometry\Position;
use Kalle\Pdf\Graphics\Color;
use Kalle\Pdf\Layout\PageSize;
use Kalle\Pdf\Layout\Units;
use Kalle\Pdf\Profile;
use Kalle\Pdf\Structure\StructureTag;
use Kalle\Pdf\Table\Style\HeaderStyle;
use Kalle\Pdf\Table\Style\TableBorder;
use Kalle\Pdf\Table\Style\TablePadding;
use Kalle\Pdf\Table\Style\TableStyle;
use Kalle\Pdf\Table\TableCaption;
use Kalle\Pdf\Table\TableCell;
use Kalle\Pdf\Table\TableHeaderScope;
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
    title: 'Header Matrix Break Example',
    author: 'kalle/pdf',
    subject: 'Multipage header matrix example with long content and aggressive page breaks',
    language: 'en-US',
    creator: 'Example Script',
    creatorTool: 'examples/table-header-matrix-breaks.php',
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
    ->addKeyword('pagination')
    ->addKeyword('long-content')
    ->addKeyword('pdf-ua')
    ->registerFont('NotoSans-Regular')
    ->registerFont('NotoSans-Bold');

$page = $document->addPage(PageSize::custom(Units::mm(160), Units::mm(140)));

$page->addText(
    'Header Matrix With Break Pressure',
    new Position(Units::mm(12), Units::mm(128)),
    'NotoSans-Bold',
    16,
    new TextOptions(
        structureTag: StructureTag::Heading1,
        color: Color::rgb(25, 45, 90),
    ),
);

$page->createTextFrame(
    new Position(Units::mm(12), Units::mm(116)),
    Units::mm(136),
    Units::mm(18),
)
    ->addParagraph(
        'This example keeps a caption and two repeated header rows stable while long cell content forces larger row heights and earlier page breaks.',
        'NotoSans-Regular',
        10,
        new ParagraphOptions(
            structureTag: StructureTag::Paragraph,
            lineHeight: Units::mm(5),
            spacingAfter: Units::mm(2),
        ),
    );

$table = $page->createTable(
    new Position(Units::mm(12), Units::mm(96)),
    Units::mm(136),
    [
        Units::mm(20),
        Units::mm(20),
        Units::mm(30),
        Units::mm(30),
        Units::mm(36),
    ],
    Units::mm(8),
);

$table
    ->font('NotoSans-Regular', 9)
    ->caption(new TableCaption(
        'Regional issue review matrix',
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
        new TableCell('Operations', colspan: 2, headerScope: TableHeaderScope::Column),
        new TableCell('Follow-up', colspan: 2, headerScope: TableHeaderScope::Column),
    ])
    ->addHeaderRow([
        'Status',
        'Assessment',
        'Owner',
        'Next step',
    ]);

foreach ([
    [
        'North',
        'Stable',
        'A longer assessment note confirms that availability stayed high while two edge locations still need manual monitoring during the morning handover.',
        'Regional ops lead',
        'Confirm the revised handover checklist, collect one additional day of evidence and send the final note back to the service desk lead.',
    ],
    [
        'South',
        'Review',
        'The service recovered after a routing issue, but the branch rollout still needs another validation round before the region can close the incident.',
        'Field coordination',
        'Review the remaining branch exceptions, update the communication pack and schedule the final rollback window with the network team.',
    ],
    [
        'West',
        'Stable',
        'The quarterly review is positive, although one escalation requires a written explanation because the response-time target was missed on a single weekend.',
        'Incident manager',
        'Attach the retrospective summary, publish the corrected service note and validate the revised escalation rota with the on-call team.',
    ],
    [
        'East',
        'Watch',
        'The region meets the main targets, but repeated staffing changes created inconsistent follow-up notes and an incomplete ownership handover.',
        'Regional support',
        'Document the ownership changes, align the handover template and review the open actions with the regional support lead.',
    ],
] as [$region, $status, $assessment, $owner, $nextStep]) {
    $table->addRow([
        new TableCell($region, headerScope: TableHeaderScope::Row),
        $status,
        $assessment,
        $owner,
        $nextStep,
    ]);
}

$targetPath = $outputDir . '/table-header-matrix-breaks_' . date('Y-m-d-H-i-s') . '.pdf';
$document->writeToFile($targetPath);

printf(
    "Erstellt in %.3f Sekunden: %s\n",
    microtime(true) - $startedAt,
    $targetPath,
);
