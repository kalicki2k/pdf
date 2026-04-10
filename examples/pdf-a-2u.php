<?php

declare(strict_types=1);

use Kalle\Pdf\Document;
use Kalle\Pdf\Internal\Layout\Geometry\Position;
use Kalle\Pdf\Internal\Layout\Page\PageSize;
use Kalle\Pdf\Internal\Layout\Page\Units;
use Kalle\Pdf\Profile;
use Kalle\Pdf\Style\Color;
use Kalle\Pdf\Text\ParagraphOptions;
use Kalle\Pdf\Text\TextOptions;
use Kalle\Pdf\Text\TextSegment;

require __DIR__ . '/../vendor/autoload.php';

$outputDir = __DIR__ . '/../var/examples';

if (!is_dir($outputDir) && !mkdir($outputDir, 0777, true) && !is_dir($outputDir)) {
    throw new RuntimeException(sprintf('Unable to create output directory "%s".', $outputDir));
}

$startedAt = microtime(true);

$document = new Document(
    profile: Profile::pdfA2u(),
    title: 'PDF/A-2u Beispiel',
    author: 'kalle/pdf',
    subject: 'Minimales PDF/A-2u Beispiel',
    language: 'de-DE',
    creator: 'Example Script',
    creatorTool: 'examples/pdf-a-2u.php',
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
)
    ->addKeyword('PDF/A')
    ->addKeyword('PDF/A-2u')
    ->addKeyword('Archivierung')
    ->registerFont('NotoSans-Regular')
    ->registerFont('NotoSans-Bold');

$page = $document->addPage(PageSize::A4());

$page->addText(
    'PDF/A-2u Beispiel',
    new Position(Units::mm(20), Units::mm(270)),
    'NotoSans-Bold',
    18,
    new TextOptions(color: Color::rgb(20, 40, 90)),
);

$page->addText(
    'Status: eingebettete Unicode-Fonts, XMP-Kennung und OutputIntent aktiv.',
    new Position(Units::mm(20), Units::mm(260)),
    'NotoSans-Regular',
    10,
);

$page->createTextFrame(
    new Position(Units::mm(20), Units::mm(245)),
    Units::mm(170),
    Units::mm(20),
)
    ->addParagraph(
        [
            TextSegment::plain('Dieses Dokument verwendet das Profil '),
            TextSegment::bold('PDF/A-2u'),
            TextSegment::plain(' und bleibt absichtlich einfach. Es nutzt nur Funktionen, die im aktuellen Stand bereits für den PDF/A-Pfad abgesichert sind.'),
        ],
        'NotoSans-Regular',
        11,
        new ParagraphOptions(
            lineHeight: Units::mm(6),
            spacingAfter: Units::mm(5),
        ),
    )
    ->addParagraph(
        "Geeignet fuer den Start sind vor allem eingebettete Unicode-Schriften, klare Metadaten und reproduzierbares Farbmanagement.\nVerbotene oder noch nicht freigegebene Features wie Verschluesselung, Attachments, Layer und Formulare bleiben hier bewusst ungenutzt.",
        'NotoSans-Regular',
        10,
        new ParagraphOptions(
            lineHeight: Units::mm(5),
        ),
    );

$targetPath = $outputDir . '/pdf-a-2u_' . date('Y-m-d-H-i-s') . '.pdf';
$document->writeToFile($targetPath);

printf(
    "Erstellt in %.3f Sekunden: %s\n",
    microtime(true) - $startedAt,
    $targetPath,
);
