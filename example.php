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
);

$document->addKeyword('demo')
    ->addKeyword('pdf')
    ->addKeyword('tagged')
    ->addFont('sans')
    ->addFont('serif')
    ->addFont('global');

$firstPage = $document->addPage(\Kalle\Pdf\Document\PageSize::A4());
$firstPage
    ->addText('A4 Testseite (Text)', 20, 265, 'NotoSans-Regular', 16, 'H1')
    ->addText(
        'A4 Hochformat | Nutzbare Breite: 170 mm | Margins: links 20 mm, rechts 20 mm, oben 20 mm, unten 20 mm',
        20,
        245,
        'NotoSans-Regular',
        4,
        'H1'
    );

$secondPage = $document->addPage();
$secondPageFrame = $secondPage->textFrame(20, 265, 170);
$secondPageFrame
    ->heading('A4 Testseite (Text)', 'NotoSans-Regular', 16, 'H1')
    ->paragraph(
        'A4 Hochformat | Nutzbare Breite: 170 mm | Margins: links 20 mm, rechts 20 mm, oben 20 mm, unten 20 mm',
        'NotoSans-Regular',
        4
    );


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
//$secondPage = $document->addPage();
//$secondFrame = $secondPage->textFrame(20, 265, 110);
//$secondFrame
//    ->heading('Seite 2', 'NotoSans-Regular', 18, 'H1')
//    ->paragraph('Fonts im direkten Vergleich', 'NotoSans-Regular', 10)
//    ->paragraph('Sans: klar und technisch.', 'NotoSans-Regular', 11)
//    ->paragraph('Serif: klassischer und etwas formeller.', 'NotoSerif-Regular', 12)
//    ->paragraph('Alle Inhalte werden aktuell als Text-Elemente auf die Seite gesetzt.', 'NotoSans-Regular', 9)
//    ->paragraph('Damit eignet sich das Beispiel gut als Ausgangspunkt fuer weitere PDF-Features.', 'NotoSans-Regular', 9);
//
//$unicodePage = $document->addPage();
//$unicodePage
//    ->addText('Unicode Font Demo', 20, 265, 'NotoSans-Regular', 18, 'H1')
//    ->addText('Die naechste Zeile verwendet den registrierten UnicodeFont:', 20, 245, 'NotoSans-Regular', 10, 'P')
//    ->addText('漢字とカタカナ', 20, 225, 'NotoSansCJKsc-Regular', 14, 'P')
//    ->addText('Noch ein Beispiel: Привет мир', 20, 205, 'NotoSansCJKsc-Regular', 12, 'P')
//    ->addText('Und gemischt: PDF 1.4 - 你好 - مرحبا', 20, 185, 'NotoSansCJKsc-Regular', 12, 'P');

$pdfContent = $document->render();
$outputPath = 'output_' . new DateTime()->format('Y-m-d-H-i-s') . '.pdf';

file_put_contents($outputPath, $pdfContent);
