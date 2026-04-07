<?php

declare(strict_types=1);

use Kalle\Pdf\Document;
use Kalle\Pdf\Document\Geometry\Position;
use Kalle\Pdf\Document\Text\ListOptions;
use Kalle\Pdf\Document\Text\ParagraphOptions;
use Kalle\Pdf\Document\Text\StructureTag;
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
    profile: Profile::pdfA2a(),
    title: 'PDF/A-2a Beispiel',
    author: 'kalle/pdf',
    subject: 'Minimales PDF/A-2a Beispiel',
    language: 'de-DE',
    creator: 'Example Script',
    creatorTool: 'examples/pdf-a-2a.php',
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
    ->addKeyword('PDF/A-2a')
    ->addKeyword('Tagged PDF')
    ->registerFont('NotoSans-Regular')
    ->registerFont('NotoSans-Bold');

$page = $document->addPage(PageSize::A4());

$page->addText(
    'Logisch strukturierter Einstieg',
    new Position(Units::mm(20), Units::mm(280)),
    'NotoSans-Bold',
    10,
    new TextOptions(color: Color::rgb(70, 70, 70)),
);

$frame = $page->createTextFrame(
    new Position(Units::mm(20), Units::mm(262)),
    Units::mm(170),
    Units::mm(190),
);

$frame
    ->addHeading(
        'PDF/A-2a Beispiel',
        'NotoSans-Bold',
        18,
        new ParagraphOptions(
            structureTag: StructureTag::Heading1,
            spacingAfter: Units::mm(6),
        ),
    )
    ->addParagraph(
        'Dieses Dokument nutzt einen kleinen Tagged-PDF-Pfad mit Ueberschrift, Absatz und Liste. '
        . 'Der Inhalt bleibt absichtlich einfach, damit veraPDF echte Strukturfehler direkt sichtbar macht.',
        'NotoSans-Regular',
        11,
        new ParagraphOptions(
            structureTag: StructureTag::Paragraph,
            lineHeight: Units::mm(6),
            spacingAfter: Units::mm(6),
        ),
    )
    ->addBulletList(
        [
            'Ueberschrift mit H1-Struktur.',
            'Absatz mit P-Struktur.',
            'Liste mit L, LI, Lbl und LBody.',
        ],
        'NotoSans-Regular',
        10,
        options: new ListOptions(
            structureTag: StructureTag::List,
            lineHeight: Units::mm(5),
            spacingAfter: Units::mm(5),
            itemSpacing: Units::mm(3),
        ),
    );

$targetPath = $outputDir . '/pdf-a-2a_' . date('Y-m-d-H-i-s') . '.pdf';
file_put_contents($targetPath, $document->render());

printf(
    "Erstellt in %.3f Sekunden: %s\n",
    microtime(true) - $startedAt,
    $targetPath,
);
