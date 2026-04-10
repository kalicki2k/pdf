<?php

declare(strict_types=1);

use Kalle\Pdf\Document;
use Kalle\Pdf\Layout\Geometry\Position;
use Kalle\Pdf\Layout\Page\PageSize;
use Kalle\Pdf\Layout\Page\Units;
use Kalle\Pdf\Layout\Text\Input\ParagraphOptions;
use Kalle\Pdf\Layout\Text\Input\TextOptions;
use Kalle\Pdf\Profile;
use Kalle\Pdf\Style\Color;

require __DIR__ . '/../vendor/autoload.php';

$outputDir = __DIR__ . '/../var/examples';

if (!is_dir($outputDir) && !mkdir($outputDir, 0777, true) && !is_dir($outputDir)) {
    throw new RuntimeException(sprintf('Unable to create output directory "%s".', $outputDir));
}

$startedAt = microtime(true);

$document = new Document(
    profile: Profile::pdfA4e(),
    title: 'PDF/A-4e Beispiel',
    author: 'kalle/pdf',
    subject: 'Minimales PDF/A-4e Beispiel',
    language: 'de-DE',
    creator: 'Example Script',
    creatorTool: 'examples/pdf-a-4e.php',
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
    ->addKeyword('PDF/A-4e')
    ->addKeyword('Archivierung')
    ->registerFont('NotoSans-Regular');

$page = $document->addPage(PageSize::A4());

$page->addText(
    'PDF/A-4e Beispiel',
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
        'Dieses Dokument nutzt das Profil PDF/A-4e. Fuer den aktuellen Minimalpfad ist kein spezieller Engineering-Inhalt noetig, um den formalen Profilpfad sauber zu validieren.',
        'NotoSans-Regular',
        11,
        new ParagraphOptions(
            lineHeight: Units::mm(6),
            spacingAfter: Units::mm(5),
        ),
    )
    ->addParagraph(
        'Das Beispiel prueft vor allem Header, XMP, PDF-2.0-Ausgabe und den schlanken PDF/A-4-Unterbau.',
        'NotoSans-Regular',
        10,
        new ParagraphOptions(
            lineHeight: Units::mm(5),
        ),
    );

$targetPath = $outputDir . '/pdf-a-4e_' . date('Y-m-d-H-i-s') . '.pdf';
$document->writeToFile($targetPath);

printf(
    "Erstellt in %.3f Sekunden: %s\n",
    microtime(true) - $startedAt,
    $targetPath,
);
