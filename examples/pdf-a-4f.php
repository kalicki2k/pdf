<?php

declare(strict_types=1);

use Kalle\Pdf\Document;
use Kalle\Pdf\Internal\Layout\Geometry\Position;
use Kalle\Pdf\Internal\Layout\Page\PageSize;
use Kalle\Pdf\Internal\Layout\Page\Units;
use Kalle\Pdf\Internal\Layout\Text\Input\ParagraphOptions;
use Kalle\Pdf\Internal\Layout\Text\Input\TextOptions;
use Kalle\Pdf\Internal\Style\Color;
use Kalle\Pdf\Profile;

require __DIR__ . '/../vendor/autoload.php';

$outputDir = __DIR__ . '/../var/examples';

if (!is_dir($outputDir) && !mkdir($outputDir, 0777, true) && !is_dir($outputDir)) {
    throw new RuntimeException(sprintf('Unable to create output directory "%s".', $outputDir));
}

$startedAt = microtime(true);

$document = new Document(
    profile: Profile::pdfA4f(),
    title: 'PDF/A-4f Beispiel',
    author: 'kalle/pdf',
    subject: 'Minimales PDF/A-4f Beispiel',
    language: 'de-DE',
    creator: 'Example Script',
    creatorTool: 'examples/pdf-a-4f.php',
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
    ->addKeyword('PDF/A-4f')
    ->addKeyword('Archivierung')
    ->registerFont('NotoSans-Regular')
    ->addAttachment(
        'invoice-data.xml',
        "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<invoice><number>2026-0003</number><total currency=\"EUR\">238.00</total></invoice>\n",
        'Machine-readable invoice source',
        'application/xml',
    );

$page = $document->addPage(PageSize::A4());

$page->addText(
    'PDF/A-4f Beispiel',
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
        'Dieses Dokument nutzt das Profil PDF/A-4f. Im Unterschied zu PDF/A-4 wird hier eine eingebettete Datei mit AFRelationship als Associated File mitgefuehrt.',
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

$targetPath = $outputDir . '/pdf-a-4f_' . date('Y-m-d-H-i-s') . '.pdf';
$document->writeToFile($targetPath);

printf(
    "Erstellt in %.3f Sekunden: %s\n",
    microtime(true) - $startedAt,
    $targetPath,
);
