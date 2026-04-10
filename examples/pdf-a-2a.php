<?php

declare(strict_types=1);

use Kalle\Pdf\Document;
use Kalle\Pdf\Image;
use Kalle\Pdf\Internal\Layout\Geometry\Position;
use Kalle\Pdf\Internal\Layout\Page\PageSize;
use Kalle\Pdf\Internal\Layout\Page\Units;
use Kalle\Pdf\Internal\Layout\Text\Input\ListOptions;
use Kalle\Pdf\Internal\Layout\Text\Input\ParagraphOptions;
use Kalle\Pdf\Internal\Layout\Text\Input\TextOptions;
use Kalle\Pdf\Internal\Page\Content\ImageOptions;
use Kalle\Pdf\Page;
use Kalle\Pdf\Profile;
use Kalle\Pdf\Style\Color;
use Kalle\Pdf\TaggedPdf\StructureTag;

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
    ->registerFont('NotoSans-Bold')
    ->addHeader(static function (Page $page, int $pageNumber): void {
        $page->addText("Archivkopf $pageNumber", new Position(Units::mm(20), Units::mm(287)), 'NotoSans-Regular', 9);
    })
    ->addFooter(static function (Page $page, int $pageNumber): void {
        $page->addText("Seite $pageNumber", new Position(Units::mm(20), Units::mm(12)), 'NotoSans-Regular', 9);
    });

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

$table = $page->createTable(
    new Position(Units::mm(20), Units::mm(190)),
    Units::mm(170),
    [Units::mm(85), Units::mm(85)],
);

$table
    ->font('NotoSans-Regular', 10)
    ->addHeaderRow(['Merkmal', 'Status'])
    ->addRow(['Strukturierte Ueberschrift', 'H1'])
    ->addRow(['Strukturierte Liste', 'L / LI / Lbl / LBody'])
    ->addRow(['Strukturierte Tabelle', 'Table / TR / TH / TD']);

$page->addImage(
    new Image(1, 1, 'DeviceGray', 'FlateDecode', "\x00"),
    new Position(Units::mm(160), Units::mm(255)),
    Units::mm(10),
    Units::mm(10),
    new ImageOptions(
        structureTag: StructureTag::Figure,
        altText: 'Kleines Beispielbild',
    ),
);

$targetPath = $outputDir . '/pdf-a-2a_' . date('Y-m-d-H-i-s') . '.pdf';
$document->writeToFile($targetPath);

printf(
    "Erstellt in %.3f Sekunden: %s\n",
    microtime(true) - $startedAt,
    $targetPath,
);
