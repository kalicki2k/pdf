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
    title: 'Table Caption Example',
    author: 'kalle/pdf',
    subject: 'Example for table captions and semantic table headers',
    language: 'en-US',
    creator: 'Example Script',
    creatorTool: 'examples/table-caption.php',
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
    ->addKeyword('pdf-ua')
    ->registerFont('NotoSans-Regular')
    ->registerFont('NotoSans-Bold');

$page = $document->addPage(PageSize::A4());

$page->addText(
    'Table Caption Example',
    new Position(Units::mm(20), Units::mm(275)),
    'NotoSans-Bold',
    18,
    new TextOptions(
        structureTag: StructureTag::Heading1,
        color: Color::rgb(25, 45, 90),
    ),
);

$page->createTextFrame(
    new Position(Units::mm(20), Units::mm(252)),
    Units::mm(170),
    Units::mm(35),
)
    ->addParagraph(
        'This example shows the new table caption support on the public API. The table uses a semantic caption, a top-left header cell with scope Both and row header cells for the first column.',
        'NotoSans-Regular',
        11,
        new ParagraphOptions(
            structureTag: StructureTag::Paragraph,
            lineHeight: Units::mm(6),
            spacingAfter: Units::mm(4),
        ),
    );

$page->createTable(
    new Position(Units::mm(20), Units::mm(212)),
    Units::mm(170),
    [
        Units::mm(38),
        Units::mm(44),
        Units::mm(44),
        Units::mm(44),
    ],
)
    ->font('NotoSans-Regular', 10)
    ->caption(new TableCaption(
        'Service quality by region',
        fontName: 'NotoSans-Bold',
        size: 12,
        color: Color::rgb(35, 55, 100),
        spacingAfter: Units::mm(3),
    ))
    ->style(new TableStyle(
        padding: TablePadding::symmetric(Units::mm(2.5), Units::mm(1.8)),
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
    ])
    ->addRow([
        new TableCell('North', headerScope: TableHeaderScope::Row),
        '98 %',
        '96 %',
        '97 %',
    ])
    ->addRow([
        new TableCell('South', headerScope: TableHeaderScope::Row),
        '95 %',
        '94 %',
        '96 %',
    ])
    ->addRow([
        new TableCell('West', headerScope: TableHeaderScope::Row),
        '99 %',
        '97 %',
        '98 %',
    ]);

$targetPath = $outputDir . '/table-caption_' . date('Y-m-d-H-i-s') . '.pdf';
$document->writeToFile($targetPath);

printf(
    "Erstellt in %.3f Sekunden: %s\n",
    microtime(true) - $startedAt,
    $targetPath,
);
