<?php

declare(strict_types=1);

use Kalle\Pdf\Core\Document;

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

$coverPage = $document->addPage();
$coverPage
    ->addText('Kalle PDF Demo', 20, 265, 'NotoSans-Regular', 24, 'H1')
    ->addText('Aktueller Stand der Library', 20, 250, 'NotoSans-Regular', 12, 'P')
    ->addText('Dieses Dokument zeigt, was im Moment bereits funktioniert:', 20, 230, 'NotoSans-Regular', 9, 'P')
    ->addText('- mehrere Seiten', 24, 214, 'NotoSans-Regular', 9, 'P')
    ->addText('- verschiedene registrierte Fonts', 24, 204, 'NotoSans-Regular', 9, 'P')
    ->addText('- einfache Metadaten wie Titel, Autor und Keywords', 24, 194, 'NotoSans-Regular', 9, 'P')
    ->addText('- strukturierte Text-Tags wie H1 und P', 24, 184, 'NotoSans-Regular', 9, 'P')
    ->addText('Naechster Ausbauschritt waere z. B. Bilder, Linien oder Tabellen.', 20, 164, 'NotoSerif-Regular', 11, 'P');

$secondPage = $document->addPage();
$secondPage
    ->addText('Seite 2', 20, 265, 'NotoSans-Regular', 18, 'H1')
    ->addText('Fonts im direkten Vergleich', 20, 245, 'NotoSans-Regular', 10, 'P')
    ->addText('Sans: klar und technisch.', 20, 225, 'NotoSans-Regular', 11, 'P')
    ->addText('Serif: klassischer und etwas formeller.', 20, 210, 'NotoSerif-Regular', 12, 'P')
    ->addText('Alle Inhalte werden aktuell als Text-Elemente auf die Seite gesetzt.', 20, 185, 'NotoSans-Regular', 9, 'P')
    ->addText('Damit eignet sich das Beispiel gut als Ausgangspunkt fuer weitere PDF-Features.', 20, 175, 'NotoSans-Regular', 9, 'P');

$unicodePage = $document->addPage();
$unicodePage
    ->addText('Unicode Font Demo', 20, 265, 'NotoSans-Regular', 18, 'H1')
    ->addText('Die naechste Zeile verwendet den registrierten UnicodeFont:', 20, 245, 'NotoSans-Regular', 10, 'P')
    ->addText('漢字とカタカナ', 20, 225, 'NotoSansCJKsc-Regular', 14, 'P')
    ->addText('Noch ein Beispiel: Привет мир', 20, 205, 'NotoSansCJKsc-Regular', 12, 'P')
    ->addText('Und gemischt: PDF 1.4 - 你好 - مرحبا', 20, 185, 'NotoSansCJKsc-Regular', 12, 'P');

$pdfContent = $document->render();
$outputPath = 'output_' . (new DateTime())->format('Y-m-d-H-i-s') . '.pdf';

file_put_contents($outputPath, $pdfContent);
