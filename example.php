<?php

declare(strict_types=1);

use Kalle\Pdf\Document\Document;
use Kalle\Pdf\Document\PageSize;
use Kalle\Pdf\Document\TextAlign;
use Kalle\Pdf\Document\TextOverflow;
use Kalle\Pdf\Document\TextSegment;
use Kalle\Pdf\Document\Units;
use Kalle\Pdf\Graphics\Color;
use Kalle\Pdf\Graphics\Opacity;

require 'vendor/autoload.php';

$document = new Document(
    version: 1.4,
    title: 'Kalle PDF Demo',
    author: 'Kalle',
    subject: 'Beispiel für Text, Metadaten und mehrere Seiten',
    language: 'de-DE',
    fontConfig: [
        [
            'baseFont' => 'NotoSans-Regular',
            'path' => 'assets/fonts/NotoSans-Regular.ttf',
            'unicode' => true,
            'subtype' => 'CIDFontType2',
            'encoding' => 'Identity-H',
        ],
        [
            'baseFont' => 'NotoSans-Bold',
            'path' => 'assets/fonts/NotoSans-Bold.ttf',
            'unicode' => true,
            'subtype' => 'CIDFontType2',
            'encoding' => 'Identity-H',
        ],
        [
            'baseFont' => 'NotoSans-Italic',
            'path' => 'assets/fonts/NotoSans-Italic.ttf',
            'unicode' => true,
            'subtype' => 'CIDFontType2',
            'encoding' => 'Identity-H',
        ],
        [
            'baseFont' => 'NotoSans-BoldItalic',
            'path' => 'assets/fonts/NotoSans-BoldItalic.ttf',
            'unicode' => true,
            'subtype' => 'CIDFontType2',
            'encoding' => 'Identity-H',
        ],
        [
            'baseFont' => 'NotoSerif-Regular',
            'path' => 'assets/fonts/NotoSerif-Regular.ttf',
            'unicode' => true,
            'subtype' => 'CIDFontType2',
            'encoding' => 'Identity-H',
        ],
        [
            'baseFont' => 'NotoSansMono-Regular',
            'path' => 'assets/fonts/NotoSansMono-Regular.ttf',
            'unicode' => true,
            'subtype' => 'CIDFontType2',
            'encoding' => 'Identity-H',
        ],
        [
            'baseFont' => 'NotoSansCJKsc-Regular',
            'path' => 'assets/fonts/NotoSansCJKsc-Regular.otf',
            'unicode' => true,
            'subtype' => 'CIDFontType0',
            'encoding' => 'Identity-H',
        ],
    ],
);

$document->addKeyword('demo')
    ->addKeyword('pdf')
    ->addKeyword('tagged')
    ->addFont('Helvetica')
    ->addFont('NotoSans-Regular')
    ->addFont('NotoSans-Bold')
    ->addFont('NotoSans-Italic')
    ->addFont('NotoSans-BoldItalic')
    ->addFont('NotoSerif-Regular')
    ->addFont('NotoSansMono-Regular')
    ->addFont('NotoSansCJKsc-Regular');

$sansPage = $document->addPage(PageSize::A4());
$sansPage->textFrame(Units::mm(20), Units::mm(265), Units::mm(170), Units::mm(20))
    ->heading('Noto Sans', 'NotoSans-Regular', 16, 'H1')
    ->paragraph(
        implode(PHP_EOL, ['abcdefghijklmnopqrstuvwxyz', 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', '0123456789.:,;()*!?\'@#<>$%&^+-=~']),
        'NotoSans-Regular',
        12,
        'P',
    )->paragraph(
        implode(PHP_EOL, ['abcdefghijklmnopqrstuvwxyz', 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', '0123456789.:,;()*!?\'@#<>$%&^+-=~']),
        'NotoSans-Regular',
        12,
        'P',
        align: TextAlign::CENTER,
    )->paragraph(
        implode(PHP_EOL, ['abcdefghijklmnopqrstuvwxyz', 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', '0123456789.:,;()*!?\'@#<>$%&^+-=~']),
        'NotoSans-Regular',
        12,
        'P',
        align: TextAlign::RIGHT,
    )->paragraph(
        implode(' ', [
            'abcde',
            'fghij',
            'klmno',
            'pqrst',
            'uvwxyz',
            'ABCDE',
            'FGHIJ',
            'KLMNO',
            'PQRST',
            'UVWXYZ',
            '01234',
            '56789',
            '.:,;()',
            '*!?\'@#',
            '<>$%&',
            '^+-=~',
            'abcde',
            'fghij',
            'klmno',
            'pqrst',
            'uvwxyz',
            'ABCDE',
            'FGHIJ',
            'KLMNO',
            'PQRST',
            'UVWXYZ',
        ]),
        'NotoSans-Regular',
        12,
        'P',
        align: TextAlign::JUSTIFY,
    )->paragraph(
        [
            new TextSegment('CLIP: ', Color::rgb(200, 30, 30), bold: true),
            new TextSegment('abcde fghij klmno pqrst uvwxyz ABCDE FGHIJ KLMNO PQRST UVWXYZ 01234 56789 .:,;() *!?"@# <>$%& ^+-=~ abcde fghij klmno pqrst uvwxyz ABCDE FGHIJ KLMNO PQRST UVWXYZ 01234 56789 .:,;() *!?"@# <>$%& ^+-=~ abcde fghij klmno pqrst uvwxyz'),
        ],
        'NotoSans-Regular',
        12,
        'P',
        maxLines: 2,
    )->paragraph(
        [
            new TextSegment('ELLIPSIS: ', Color::rgb(200, 30, 30), bold: true),
            new TextSegment('abcde fghij klmno pqrst uvwxyz ABCDE FGHIJ KLMNO PQRST UVWXYZ 01234 56789 .:,;() *!?"@# <>$%& ^+-=~ abcde fghij klmno pqrst uvwxyz ABCDE FGHIJ KLMNO PQRST UVWXYZ 01234 56789 .:,;() *!?"@# <>$%& ^+-=~ abcde fghij klmno pqrst uvwxyz'),
        ],
        'NotoSans-Regular',
        12,
        'P',
        maxLines: 2,
        overflow: TextOverflow::ELLIPSIS,
    )->paragraph(
        implode(PHP_EOL, ['abcdefghijklmnopqrstuvwxyz', 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', '0123456789.:,;()*!?\'@#<>$%&^+-=~']),
        'NotoSans-Regular',
        12,
        'P',
        color: Color::rgb(0, 0, 255),
    )->paragraph(
        implode(PHP_EOL, ['abcdefghijklmnopqrstuvwxyz', 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', '0123456789.:,;()*!?\'@#<>$%&^+-=~']),
        'NotoSans-Regular',
        12,
        'P',
        opacity: Opacity::fill(0.5),
    )->paragraph(
        implode(PHP_EOL, ['abcdefghijklmnopqrstuvwxyz', 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', '0123456789.:,;()*!?\'@#<>$%&^+-=~']),
        'NotoSans-Regular',
        12,
        'P',
    )->paragraph(
        [
            new TextSegment('Achtung:', Color::rgb(255, 0, 0), bold: true, underline: true),
            new TextSegment(
                implode(PHP_EOL, ['abcdefghijklmnopqrstuvwxyz', 'ABCDEFGHIJKLMNOPQRSTUVWXYZ']),
                italic: true
            ),
            new TextSegment(PHP_EOL . '0123456789.:,;()*!?\'@#<>$%&^+-=~', strikethrough: true),
        ],
        'NotoSans-Regular',
        12,
        'P',
    );

$serifPage = $document->addPage(PageSize::A4());
$serifPage->textFrame(Units::mm(20), Units::mm(265), Units::mm(170), Units::mm(20))
    ->heading('Noto Serif', 'NotoSerif-Regular', 16, 'H1')
    ->paragraph(
        'abcdefghijklmnopqrstuvwxyz ABCDEFGHIJKLMNOPQRSTUVWXYZ 0123456789.:,;()*!?\'@#<>$%&^+-=~',
        'NotoSerif-Regular',
        12,
        'P',
    );

$monoPage = $document->addPage(PageSize::A4());
$monoPage->textFrame(Units::mm(20), Units::mm(265), Units::mm(170), Units::mm(20))
    ->heading('Noto Sans Mono', 'NotoSansMono-Regular', 16, 'H1')
    ->paragraph(
        'abcdefghijklmnopqrstuvwxyz ABCDEFGHIJKLMNOPQRSTUVWXYZ 0123456789.:,;()*!?\'@#<>$%&^+-=~',
        'NotoSansMono-Regular',
        12,
        'P',
    );

$cjkPage = $document->addPage(PageSize::A4());
$cjkPage->textFrame(Units::mm(20), Units::mm(265), Units::mm(170), Units::mm(20))
    ->heading('Noto Sans CJK', 'NotoSansCJKsc-Regular', 16, 'H1')
    ->paragraph(
        '漢字とカタカナ',
        'NotoSansCJKsc-Regular',
        14,
        'P',
    );

$standardPage = $document->addPage(PageSize::A4());
$standardPage->textFrame(Units::mm(20), Units::mm(265), Units::mm(170), Units::mm(20))
    ->heading('Helvetica', 'Helvetica', 16, 'H1')
    ->paragraph(
        'abcdefghijklmnopqrstuvwxyz ABCDEFGHIJKLMNOPQRSTUVWXYZ 0123456789.:,;()*!?\'@#<>$%&^+-=~',
        'Helvetica',
        12,
        'P',
    );

//$coverPage = $document->addPage(\Kalle\Pdf\Document\PageSize::A4());
//$coverFrame = $coverPage->textFrame(20, 265, 170);
//$coverFrame
//    ->heading('Kalle PDF Demo', 'NotoSans-Regular', 24, 'H1')
//    ->paragraph('Aktueller Stand der Library', 'NotoSans-Regular', 12)
//    ->paragraph('Dieses Dokument zeigt, was im Moment bereits funktioniert:', 'NotoSans-Regular', 9)
//    ->paragraph('- mehrere Seiten', 'NotoSans-Regular', 9)
//    ->paragraph('- verschiedene registrierte Fonts', 'NotoSans-Regular', 9)
//    ->paragraph('- einfache Metadaten wie Titel, Autor und Keywords', 'NotoSans-Regular', 9)
//    ->paragraph('- strukturierte Text-Tags wie H1 und P', 'NotoSans-Regular', 9)
//    ->spacer(4)
//    ->paragraph('Naechster Ausbauschritt waere z. B. Bilder, Linien oder Tabellen.', 'NotoSerif-Regular', 11);
//
//$comparisonPage = $document->addPage(\Kalle\Pdf\Document\PageSize::A4());
//$comparisonFrame = $comparisonPage->textFrame(20, 265, 110);
//$comparisonFrame
//    ->heading('Seite 2', 'NotoSans-Regular', 18, 'H1')
//    ->paragraph('Fonts im direkten Vergleich', 'NotoSans-Regular', 10)
//    ->paragraph('Sans: klar und technisch.', 'NotoSans-Regular', 11)
//    ->paragraph('Serif: klassischer und etwas formeller.', 'NotoSerif-Regular', 12)
//    ->paragraph('Alle Inhalte werden aktuell als Text-Elemente auf die Seite gesetzt.', 'NotoSans-Regular', 9)
//    ->paragraph('Damit eignet sich das Beispiel gut als Ausgangspunkt fuer weitere PDF-Features.', 'NotoSans-Regular', 9);
//
//$unicodePage = $document->addPage(\Kalle\Pdf\Document\PageSize::A4());
//$unicodePage
//    ->addText('Unicode Font Demo', 20, 265, 'NotoSans-Regular', 18, 'H1')
//    ->addText('Die naechste Zeile verwendet den registrierten UnicodeFont:', 20, 245, 'NotoSans-Regular', 10, 'P')
//    ->addText('漢字とカタカナ', 20, 225, 'NotoSansCJKsc-Regular', 14, 'P')
//    ->addText('Noch ein Beispiel: Привет мир', 20, 205, 'NotoSansCJKsc-Regular', 12, 'P')
//    ->addText('Und gemischt: PDF 1.4 - 你好 - مرحبا', 20, 185, 'NotoSansCJKsc-Regular', 12, 'P');

$pdfContent = $document->render();
$outputPath = 'output_' . new DateTime()->format('Y-m-d-H-i-s') . '.pdf';

file_put_contents($outputPath, $pdfContent);
