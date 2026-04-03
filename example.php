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
    ->addFont('Helvetica', 'Type1', 'StandardEncoding')
    ->addFont('Times-Roman', 'Type1', 'StandardEncoding');

$coverPage = $document->addPage();
$coverPage
    ->addText('Kalle PDF Demo', 20, 265, 'Helvetica', 24, 'H1')
    ->addText('Aktueller Stand der Library', 20, 250, 'Helvetica', 12, 'P')
    ->addText('Dieses Dokument zeigt, was im Moment bereits funktioniert:', 20, 230, 'Helvetica', 9, 'P')
    ->addText('- mehrere Seiten', 24, 214, 'Helvetica', 9, 'P')
    ->addText('- verschiedene registrierte Fonts', 24, 204, 'Helvetica', 9, 'P')
    ->addText('- einfache Metadaten wie Titel, Autor und Keywords', 24, 194, 'Helvetica', 9, 'P')
    ->addText('- strukturierte Text-Tags wie H1 und P', 24, 184, 'Helvetica', 9, 'P')
    ->addText('Naechster Ausbauschritt waere z. B. Bilder, Linien oder Tabellen.', 20, 164, 'Times-Roman', 11, 'P');

$secondPage = $document->addPage();
$secondPage
    ->addText('Seite 2', 20, 265, 'Helvetica', 18, 'H1')
    ->addText('Fonts im direkten Vergleich', 20, 245, 'Helvetica', 10, 'P')
    ->addText('Helvetica: klar und technisch.', 20, 225, 'Helvetica', 11, 'P')
    ->addText('Times-Roman: klassischer und etwas formeller.', 20, 210, 'Times-Roman', 12, 'P')
    ->addText('Alle Inhalte werden aktuell als Text-Elemente auf die Seite gesetzt.', 20, 185, 'Helvetica', 9, 'P')
    ->addText('Damit eignet sich das Beispiel gut als Ausgangspunkt fuer weitere PDF-Features.', 20, 175, 'Helvetica', 9, 'P');

$pdfContent = $document->render();
$outputPath = 'output_' . (new DateTime())->format('Y-m-d-H-i-s') . '.pdf';

file_put_contents($outputPath, $pdfContent);
