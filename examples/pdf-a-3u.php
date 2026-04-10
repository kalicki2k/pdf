<?php

declare(strict_types=1);

use Kalle\Pdf\Document\Document;
use Kalle\Pdf\Layout\Geometry\Position;
use Kalle\Pdf\Layout\Page\PageSize;
use Kalle\Pdf\Layout\Page\Units;
use Kalle\Pdf\Layout\Text\Input\ParagraphOptions;
use Kalle\Pdf\Layout\Text\Input\TextOptions;
use Kalle\Pdf\Profile\Profile;
use Kalle\Pdf\Style\Color;

require __DIR__ . '/../vendor/autoload.php';

$outputDir = __DIR__ . '/../var/examples';

if (!is_dir($outputDir) && !mkdir($outputDir, 0777, true) && !is_dir($outputDir)) {
    throw new RuntimeException(sprintf('Unable to create output directory "%s".', $outputDir));
}

$startedAt = microtime(true);

$document = new Document(
    profile: Profile::pdfA3u(),
    title: 'PDF/A-3u Beispiel',
    author: 'kalle/pdf',
    subject: 'Minimales PDF/A-3u Beispiel',
    language: 'de-DE',
    creator: 'Example Script',
    creatorTool: 'examples/pdf-a-3u.php',
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
    ->addKeyword('PDF/A-3u')
    ->addKeyword('Archivierung')
    ->registerFont('NotoSans-Regular')
    ->addAttachment(
        'invoice-data.xml',
        "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<invoice><number>2026-0002</number><customer>Mueller GmbH</customer></invoice>\n",
        'Machine-readable invoice source',
        'application/xml',
    );

$page = $document->addPage(PageSize::A4());

$page->addText(
    'PDF/A-3u Beispiel',
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
        'Dieses Dokument nutzt das Profil PDF/A-3u. Es kombiniert den bisherigen Unicode-Font-Pfad mit einer eingebetteten XML-Datei als Associated File.',
        'NotoSans-Regular',
        11,
        new ParagraphOptions(
            lineHeight: Units::mm(6),
            spacingAfter: Units::mm(5),
        ),
    )
    ->addParagraph(
        'Damit steht fuer PDF/A-3x jetzt ein kleiner, validierter Beispielpfad fuer B und U bereit.',
        'NotoSans-Regular',
        10,
        new ParagraphOptions(
            lineHeight: Units::mm(5),
        ),
    );

$targetPath = $outputDir . '/pdf-a-3u_' . date('Y-m-d-H-i-s') . '.pdf';
$document->writeToFile($targetPath);

printf(
    "Erstellt in %.3f Sekunden: %s\n",
    microtime(true) - $startedAt,
    $targetPath,
);
