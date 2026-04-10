<?php

declare(strict_types=1);

use Kalle\Pdf\Document;
use Kalle\Pdf\Layout\PageSize;
use Kalle\Pdf\Layout\Position;
use Kalle\Pdf\Layout\Units;
use Kalle\Pdf\Profile;
use Kalle\Pdf\Style\Color;
use Kalle\Pdf\Text\ParagraphOptions;
use Kalle\Pdf\Text\TextOptions;

require __DIR__ . '/../vendor/autoload.php';

$outputDir = __DIR__ . '/../var/examples';

if (!is_dir($outputDir) && !mkdir($outputDir, 0777, true) && !is_dir($outputDir)) {
    throw new RuntimeException(sprintf('Unable to create output directory "%s".', $outputDir));
}

$startedAt = microtime(true);

$document = new Document(
    profile: Profile::pdfA4(),
    title: 'PDF/A-4 Beispiel',
    author: 'kalle/pdf',
    subject: 'Minimales PDF/A-4 Beispiel',
    language: 'de-DE',
    creator: 'Example Script',
    creatorTool: 'examples/pdf-a-4.php',
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
    ->addKeyword('PDF/A-4')
    ->addKeyword('Archivierung')
    ->registerFont('NotoSans-Regular');

$page = $document->addPage(PageSize::A4());

$page->addText(
    'PDF/A-4 Beispiel',
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
        'Dieses Dokument nutzt das Profil PDF/A-4 auf Basis von PDF 2.0. Im aktuellen Pfad bleibt das Beispiel bewusst klein und konzentriert sich auf den validierten Kern mit XMP, Header, OutputIntent und eingebettetem Font.',
        'NotoSans-Regular',
        11,
        new ParagraphOptions(
            lineHeight: Units::mm(6),
            spacingAfter: Units::mm(5),
        ),
    )
    ->addParagraph(
        'Klassische Info-Metadaten im Trailer werden fuer den PDF/A-4-Pfad nicht geschrieben.',
        'NotoSans-Regular',
        10,
        new ParagraphOptions(
            lineHeight: Units::mm(5),
        ),
    );

$targetPath = $outputDir . '/pdf-a-4_' . date('Y-m-d-H-i-s') . '.pdf';
$document->writeToFile($targetPath);

printf(
    "Erstellt in %.3f Sekunden: %s\n",
    microtime(true) - $startedAt,
    $targetPath,
);
