<?php

declare(strict_types=1);

use Kalle\Pdf\Document;
use Kalle\Pdf\Feature\Text\ParagraphOptions;
use Kalle\Pdf\Feature\Text\TextOptions;
use Kalle\Pdf\Geometry\Position;
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
    profile: Profile::pdfA3b(),
    title: 'PDF/A-3b Beispiel',
    author: 'kalle/pdf',
    subject: 'Minimales PDF/A-3b Beispiel',
    language: 'de-DE',
    creator: 'Example Script',
    creatorTool: 'examples/pdf-a-3b.php',
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
    ->addKeyword('PDF/A-3b')
    ->addKeyword('Archivierung')
    ->registerFont('NotoSans-Regular')
    ->addAttachment(
        'invoice-data.xml',
        "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<invoice><number>2026-0001</number><total currency=\"EUR\">119.00</total></invoice>\n",
        'Machine-readable invoice source',
        'application/xml',
    );

$page = $document->addPage(PageSize::A4());

$page->addText(
    'PDF/A-3b Beispiel',
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
        'Dieses Dokument nutzt das Profil PDF/A-3b. Im Unterschied zu PDF/A-2x darf hier eine eingebettete Datei mitgefuehrt werden, wenn sie sauber als Associated File modelliert ist.',
        'NotoSans-Regular',
        11,
        new ParagraphOptions(
            lineHeight: Units::mm(6),
            spacingAfter: Units::mm(5),
        ),
    )
    ->addParagraph(
        'Im Beispiel ist eine XML-Datei als maschinenlesbare Quelldatei eingebettet.',
        'NotoSans-Regular',
        10,
        new ParagraphOptions(
            lineHeight: Units::mm(5),
        ),
    );

$targetPath = $outputDir . '/pdf-a-3b_' . date('Y-m-d-H-i-s') . '.pdf';
$document->writeToFile($targetPath);

printf(
    "Erstellt in %.3f Sekunden: %s\n",
    microtime(true) - $startedAt,
    $targetPath,
);
