<?php

declare(strict_types=1);

use Kalle\Pdf\Document;
use Kalle\Pdf\Image;
use Kalle\Pdf\Internal\Layout\Geometry\Position;
use Kalle\Pdf\Internal\Layout\Geometry\Rect;
use Kalle\Pdf\Internal\Layout\Page\PageSize;
use Kalle\Pdf\Internal\Layout\Page\Units;
use Kalle\Pdf\Internal\Page\Content\ImageOptions;
use Kalle\Pdf\Internal\Page\Link\LinkTarget;
use Kalle\Pdf\Internal\TaggedPdf\StructureTag;
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
    profile: Profile::pdfUa1(),
    title: 'PDF/UA-1 Example',
    author: 'kalle/pdf',
    subject: 'Accessible PDF/UA-1 example',
    language: 'en-US',
    creator: 'Example Script',
    creatorTool: 'examples/pdf-ua-1.php',
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
    ->addKeyword('PDF/UA')
    ->addKeyword('Accessibility')
    ->registerFont('NotoSans-Regular')
    ->registerFont('NotoSans-Bold');

$page = $document->addPage(PageSize::A4());

$page->addText(
    'PDF/UA-1 Example',
    new Position(Units::mm(20), Units::mm(275)),
    'NotoSans-Bold',
    18,
    new TextOptions(
        structureTag: StructureTag::Heading1,
        color: Color::rgb(20, 40, 90),
    ),
);

$page->createTextFrame(
    new Position(Units::mm(20), Units::mm(250)),
    Units::mm(170),
    Units::mm(40),
)
    ->addParagraph(
        'This example stays on the currently supported PDF/UA-1 path with tagged text, an accessible figure, a tagged text link and a standalone link annotation with an explicit accessible name.',
        'NotoSans-Regular',
        11,
        new ParagraphOptions(
            structureTag: StructureTag::Paragraph,
            lineHeight: Units::mm(6),
            spacingAfter: Units::mm(5),
        ),
    );

$page->addText(
    'Visit the project website for more details.',
    new Position(Units::mm(20), Units::mm(205)),
    'NotoSans-Regular',
    11,
    new TextOptions(
        structureTag: StructureTag::Paragraph,
        link: LinkTarget::externalUrl('https://github.com/kalicki2k/pdf'),
    ),
);

$page->addImage(
    new Image(1, 1, 'DeviceGray', 'FlateDecode', "\x00"),
    new Position(Units::mm(20), Units::mm(185)),
    Units::mm(15),
    Units::mm(15),
    new ImageOptions(
        structureTag: StructureTag::Figure,
        altText: 'Small decorative example image',
    ),
);

$page->addLink(
    new Rect(Units::mm(50), Units::mm(185), Units::mm(50), Units::mm(12)),
    'https://example.com/contact',
    'Open contact information',
);

$page->addTextField(
    'contact_name',
    new Rect(Units::mm(20), Units::mm(155), Units::mm(60), Units::mm(12)),
    'Ada',
    'NotoSans-Regular',
    11,
    accessibleName: 'Contact name',
);
$page->addCheckbox('accept_terms', new Position(Units::mm(20), Units::mm(140)), Units::mm(4), true, 'Accept terms');
$page->addPushButton(
    'send_form',
    'Send',
    new Rect(Units::mm(20), Units::mm(125), Units::mm(28), Units::mm(10)),
    'NotoSans-Regular',
    10,
    accessibleName: 'Send form',
);

$targetPath = $outputDir . '/pdf-ua-1_' . date('Y-m-d-H-i-s') . '.pdf';
$document->writeToFile($targetPath);

printf(
    "Erstellt in %.3f Sekunden: %s\n",
    microtime(true) - $startedAt,
    $targetPath,
);
