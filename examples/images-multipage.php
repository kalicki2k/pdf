<?php

declare(strict_types=1);

use Kalle\Pdf\Document\Document;
use Kalle\Pdf\Image\Image;
use Kalle\Pdf\Layout\Geometry\Position;
use Kalle\Pdf\Layout\Geometry\Rect;
use Kalle\Pdf\Layout\Page\PageSize;
use Kalle\Pdf\Layout\Page\Units;
use Kalle\Pdf\Layout\Text\Input\ParagraphOptions;
use Kalle\Pdf\Layout\Text\Input\TextOptions;
use Kalle\Pdf\Page\Content\ImageOptions;
use Kalle\Pdf\Page\Content\Style\PanelStyle;
use Kalle\Pdf\Profile\Profile;
use Kalle\Pdf\Style\Color;
use Kalle\Pdf\Style\Opacity;

require __DIR__ . '/../vendor/autoload.php';

$outputDir = __DIR__ . '/../var/examples';

if (!is_dir($outputDir) && !mkdir($outputDir, 0777, true) && !is_dir($outputDir)) {
    throw new RuntimeException(sprintf('Unable to create output directory "%s".', $outputDir));
}

$startedAt = microtime(true);
$jpgBackground = Image::fromFile(__DIR__ . '/../assets/images/examples/multipage-cover.jpg');
$jpgPhoto = Image::fromFile(__DIR__ . '/../assets/images/examples/multipage-photo.jpg');
$pngOverlay = Image::fromFile(__DIR__ . '/../assets/images/examples/multipage-overlay.png');

$document = new Document(
    profile: Profile::standard(1.7),
    title: 'Multipage JPG and PNG example',
    author: 'kalle/pdf',
    subject: 'Example document with background image, JPG and PNG rendering',
    language: 'de-DE',
    creator: 'Example Script',
    creatorTool: 'examples/images-multipage.php',
);

$document
    ->registerFont('Helvetica')
    ->registerFont('Helvetica-Bold')
    ->registerFont('Helvetica-Oblique')
    ->addKeyword('images')
    ->addKeyword('jpg')
    ->addKeyword('png')
    ->addKeyword('background');

$cover = $document->addPage(PageSize::A4());
$cover->addImage($jpgBackground, new Position(0, 0), Units::mm(210), Units::mm(297));
$cover->addRectangle(
    new Rect(Units::mm(16), Units::mm(180), Units::mm(178), Units::mm(84)),
    strokeWidth: null,
    strokeColor: null,
    fillColor: Color::rgb(12, 18, 28),
    opacity: Opacity::both(0.72),
);
$cover->addText(
    'Mehrseitiges Bild-Beispiel',
    new Position(Units::mm(24), Units::mm(246)),
    'Helvetica-Bold',
    24,
    new TextOptions(color: Color::rgb(255, 255, 255)),
);
$cover->addText(
    'Seite 1 nutzt ein JPG als vollflaechiges Hintergrundbild.',
    new Position(Units::mm(24), Units::mm(233)),
    'Helvetica',
    12,
    new TextOptions(color: Color::rgb(255, 255, 255)),
);
$cover->createTextFrame(
    new Position(Units::mm(24), Units::mm(220)),
    Units::mm(150),
)
    ->addParagraph(
        'Das Beispiel zeigt drei typische Bildpfade: ein JPG als Cover-Hintergrund, ein JPG als inhaltlicher Bildblock und ein PNG mit Transparenz als Overlay ueber Text und Formen. Damit kann man die oeffentliche Image::fromFile-API direkt als Referenz verwenden.',
        'Helvetica',
        11,
        new ParagraphOptions(
            lineHeight: Units::mm(6),
            color: Color::rgb(236, 242, 248),
        ),
    );

$page2 = $document->addPage(PageSize::A4());
$page2->addText(
    'Seite 2: JPG im Inhaltslayout',
    new Position(Units::mm(18), Units::mm(280)),
    'Helvetica-Bold',
    18,
    new TextOptions(color: Color::rgb(26, 42, 68)),
);
$page2->addText(
    'Kleineres JPG mit Textspalte und Infokasten',
    new Position(Units::mm(18), Units::mm(270)),
    'Helvetica',
    11,
    new TextOptions(color: Color::rgb(92, 106, 126)),
);
$page2->addImage($jpgPhoto, new Position(Units::mm(18), Units::mm(160)), Units::mm(174), Units::mm(98));
$page2->addPanel(
    body: 'Das JPG wird hier nicht als Hintergrund benutzt, sondern als normaler Bildblock innerhalb des Seitenlayouts. Darunter folgt Fliesstext mit einer klaren Trennung zwischen Bildflaeche und Textspalte.',
    x: Units::mm(124),
    y: Units::mm(118),
    width: Units::mm(68),
    height: Units::mm(32),
    title: 'JPG-Block',
    bodyFont: 'Helvetica',
    style: new PanelStyle(
        fillColor: Color::rgb(245, 247, 250),
        titleColor: Color::rgb(26, 42, 68),
        bodyColor: Color::rgb(58, 68, 82),
        borderColor: Color::rgb(210, 218, 228),
        borderWidth: 0.8,
    ),
    titleFont: 'Helvetica-Bold',
);
$page2->createTextFrame(
    new Position(Units::mm(18), Units::mm(140)),
    Units::mm(96),
)
    ->addParagraph(
        'Viele Dokumente brauchen genau diesen Stil: ein starkes Key Visual oben, danach ein Textblock mit kurzer Einordnung. Das Beispiel laesst sich leicht auf Produktseiten, Exposes oder Reports uebertragen.',
        'Helvetica',
        11,
        new ParagraphOptions(
            lineHeight: Units::mm(6),
            spacingAfter: Units::mm(5),
            color: Color::rgb(48, 54, 64),
        ),
    )
    ->addParagraph(
        'Wichtig ist hier nur die normale Kombination aus Image::fromFile(...), addImage(...) und anschliessendem TextFrame. Mehr braucht es fuer den typischen JPG-Use-Case nicht.',
        'Helvetica',
        11,
        new ParagraphOptions(
            lineHeight: Units::mm(6),
            color: Color::rgb(48, 54, 64),
        ),
    );

$page3 = $document->addPage(PageSize::A4());
$page3->addRectangle(
    new Rect(0, 0, Units::mm(210), Units::mm(297)),
    strokeWidth: null,
    strokeColor: null,
    fillColor: Color::rgb(247, 241, 232),
);
$page3->addText(
    'Seite 3: PNG mit Transparenz',
    new Position(Units::mm(18), Units::mm(280)),
    'Helvetica-Bold',
    18,
    new TextOptions(color: Color::rgb(72, 46, 22)),
);
$page3->addText(
    'Das PNG liegt transparent ueber Text und Farbflächen.',
    new Position(Units::mm(18), Units::mm(270)),
    'Helvetica',
    11,
    new TextOptions(color: Color::rgb(120, 92, 62)),
);
$page3->addRectangle(
    new Rect(Units::mm(18), Units::mm(152), Units::mm(174), Units::mm(86)),
    strokeWidth: null,
    strokeColor: null,
    fillColor: Color::rgb(255, 255, 255),
);
$page3->addImage(
    $pngOverlay,
    new Position(Units::mm(82), Units::mm(92)),
    Units::mm(116),
    Units::mm(116),
    new ImageOptions(),
);
$page3->createTextFrame(
    new Position(Units::mm(24), Units::mm(236)),
    Units::mm(84),
)
    ->addParagraph(
        'Hier wird bewusst ein PNG mit Alpha-Kanal verwendet. Dadurch bleibt die darunterliegende Flaeche sichtbar und das Overlay kann dekorativ ueber Text und Formen liegen.',
        'Helvetica',
        11,
        new ParagraphOptions(
            lineHeight: Units::mm(6),
            spacingAfter: Units::mm(5),
            color: Color::rgb(74, 58, 41),
        ),
    )
    ->addParagraph(
        'Praktisch ist das fuer Markenmuster, Freisteller, Wasserzeichen, Sticker oder UI-Mockups mit transparenten Bereichen.',
        'Helvetica',
        11,
        new ParagraphOptions(
            lineHeight: Units::mm(6),
            color: Color::rgb(74, 58, 41),
        ),
    );
$page3->addPanel(
    body: 'JPG fuer fotolastige Flaechen. PNG fuer transparente Overlays.',
    x: Units::mm(24),
    y: Units::mm(118),
    width: Units::mm(70),
    height: Units::mm(24),
    title: 'Faustregel',
    bodyFont: 'Helvetica',
    style: new PanelStyle(
        fillColor: Color::rgb(248, 235, 209),
        titleColor: Color::rgb(92, 58, 24),
        bodyColor: Color::rgb(92, 58, 24),
        borderColor: Color::rgb(224, 191, 143),
        borderWidth: 0.8,
    ),
    titleFont: 'Helvetica-Bold',
);
$page3->addImage($jpgPhoto, new Position(Units::mm(24), Units::mm(48)), Units::mm(56), Units::mm(32));
$page3->addText(
    'Unten links liegt das JPG noch einmal klein als Kontrast zum transparenten PNG.',
    new Position(Units::mm(88), Units::mm(68)),
    'Helvetica-Oblique',
    10,
    new TextOptions(color: Color::rgb(120, 92, 62)),
);

$targetPath = $outputDir . '/images-multipage_' . date('Y-m-d-H-i-s') . '.pdf';
$document->writeToFile($targetPath);

printf(
    "Erstellt in %.3f Sekunden: %s\n",
    microtime(true) - $startedAt,
    $targetPath,
);
