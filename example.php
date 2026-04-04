<?php

declare(strict_types=1);

use Kalle\Pdf\Document\Document;

require 'vendor/autoload.php';

$document = new Document(
    version: 1.4,
    title: 'Kalle PDF Demo',
    author: 'Kalle',
    subject: 'Beispiel fuer Text, Metadaten und mehrere Seiten',
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
    ->addFont('NotoSerif-Regular')
    ->addFont('NotoSansMono-Regular')
    ->addFont('NotoSansCJKsc-Regular');

$sansPage = $document->addPage(\Kalle\Pdf\Document\PageSize::A4());
$sansPage->textFrame(20, 265, 170)
    ->heading('Noto Sans', 'NotoSans-Regular', 16, 'H1')
    ->paragraph('Das ist ein Test fuer NotoSans-Regular.', 'NotoSans-Regular', 12, 'P');

$serifPage = $document->addPage(\Kalle\Pdf\Document\PageSize::A4());
$serifPage->textFrame(20, 265, 170)
    ->heading('Noto Serif', 'NotoSerif-Regular', 16, 'H1')
    ->paragraph('Das ist ein Test fuer NotoSerif-Regular.', 'NotoSerif-Regular', 12, 'P');

$monoPage = $document->addPage(\Kalle\Pdf\Document\PageSize::A4());
$monoPage->textFrame(20, 265, 170)
    ->heading('Noto Sans Mono', 'NotoSansMono-Regular', 16, 'H1')
    ->paragraph('Das ist ein Test fuer NotoSansMono-Regular.', 'NotoSansMono-Regular', 12, 'P');

$cjkPage = $document->addPage(\Kalle\Pdf\Document\PageSize::A4());
$cjkPage->textFrame(20, 265, 170)
    ->heading('Noto Sans CJK', 'NotoSansCJKsc-Regular', 16, 'H1')
    ->paragraph('漢字とカタカナ', 'NotoSansCJKsc-Regular', 14, 'P');

$standardPage = $document->addPage(\Kalle\Pdf\Document\PageSize::A4());
$standardPage->textFrame(20, 265, 170)
    ->heading('Helvetica', 'Helvetica', 16, 'H1')
    ->paragraph('Das ist ein Test fuer die PDF-Standardfont Helvetica.', 'Helvetica', 12, 'P');

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
