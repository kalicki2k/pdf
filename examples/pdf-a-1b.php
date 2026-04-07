<?php

declare(strict_types=1);

use Kalle\Pdf\Document;
use Kalle\Pdf\Document\Geometry\Position;
use Kalle\Pdf\Document\Text\ParagraphOptions;
use Kalle\Pdf\Document\Text\TextOptions;
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
    profile: Profile::pdfA1b(),
    title: 'PDF/A-1b Beispiel',
    author: 'kalle/pdf',
    subject: 'Minimales PDF/A-1b Beispiel',
    language: 'de-DE',
    creator: 'Example Script',
    creatorTool: 'examples/pdf-a-1b.php',
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
    ->addKeyword('PDF/A')
    ->addKeyword('PDF/A-1b')
    ->addKeyword('Archivierung')
    ->registerFont('NotoSans-Regular');

$page = $document->addPage(PageSize::A4());

$page->addText(
    'PDF/A-1b Beispiel',
    new Position(Units::mm(20), Units::mm(270)),
    'NotoSans-Regular',
    18,
    new TextOptions(color: Color::rgb(20, 40, 90)),
);

$page->createTextFrame(
    new Position(Units::mm(20), Units::mm(245)),
    Units::mm(170),
    Units::mm(20),
)
    ->addParagraph(
        'Dieses Dokument nutzt einen kleinen PDF/A-1b-Pfad mit PDF-1.4-Basis, OutputIntent und eingebettetem Font.',
        'NotoSans-Regular',
        11,
        new ParagraphOptions(
            lineHeight: Units::mm(6),
            spacingAfter: Units::mm(5),
        ),
    )
    ->addParagraph(
        'Der Pfad bleibt absichtlich schlicht und verzichtet auf spaetere PDF-Funktionen, die fuer PDF/A-1 unguenstig waeren.',
        'NotoSans-Regular',
        10,
        new ParagraphOptions(
            lineHeight: Units::mm(5),
        ),
    );

$targetPath = $outputDir . '/pdf-a-1b_' . date('Y-m-d-H-i-s') . '.pdf';
file_put_contents($targetPath, $document->render());

printf(
    "Erstellt in %.3f Sekunden: %s\n",
    microtime(true) - $startedAt,
    $targetPath,
);
