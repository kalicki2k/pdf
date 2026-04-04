<?php

declare(strict_types=1);

use Kalle\Pdf\Document\Document;

require 'vendor/autoload.php';

const PAGE_WIDTH = 210.0;
const PAGE_HEIGHT = 297.0;
const MARGIN_LEFT = 15.0;
const MARGIN_RIGHT = 15.0;
const MARGIN_TOP = 16.0;
const MARGIN_BOTTOM = 16.0;
const CONTENT_WIDTH = PAGE_WIDTH - MARGIN_LEFT - MARGIN_RIGHT;
const CONTENT_HEIGHT = PAGE_HEIGHT - MARGIN_TOP - MARGIN_BOTTOM;
const TEST_FONT = 'NotoSansCJKsc-Regular';

/**
 * Konservative Breitenannahme ohne echte Font-Metriken:
 * lieber etwas frueher umbrechen als rechts aus A4 laufen.
 */
function estimateCharWidth(int $fontSize): float
{
    return max(2.4, $fontSize * 0.52);
}

function lineAdvance(int $fontSize, float $lineHeight = 1.20): float
{
    return max($fontSize * $lineHeight * 0.50, $fontSize * 0.62);
}

function pageY(float $cursorY): float
{
    return PAGE_HEIGHT - $cursorY;
}

/**
 * @return list<string>
 */
function wrapText(string $text, int $fontSize, float $maxWidth): array
{
    $maxChars = max(8, (int) floor($maxWidth / estimateCharWidth($fontSize)));
    $paragraphs = preg_split("/\R{2,}/u", trim($text)) ?: [];
    $lines = [];

    foreach ($paragraphs as $paragraph) {
        $words = preg_split('/\s+/u', trim($paragraph)) ?: [];
        $currentLine = '';

        foreach ($words as $word) {
            if ($word === '') {
                continue;
            }

            $candidate = $currentLine === '' ? $word : $currentLine . ' ' . $word;

            if (mb_strlen($candidate) <= $maxChars) {
                $currentLine = $candidate;
                continue;
            }

            if ($currentLine !== '') {
                $lines[] = $currentLine;
            }

            while (mb_strlen($word) > $maxChars) {
                $sliceLength = max(1, $maxChars - 1);
                $lines[] = mb_substr($word, 0, $sliceLength) . '-';
                $word = mb_substr($word, $sliceLength);
            }

            $currentLine = $word;
        }

        if ($currentLine !== '') {
            $lines[] = $currentLine;
        }

        $lines[] = '';
    }

    if ($lines !== [] && end($lines) === '') {
        array_pop($lines);
    }

    return $lines;
}

/**
 * Blockhoehe fuer Seitenumbruch und Schutz vor Ueberlagerung.
 *
 * @param list<string> $lines
 */
function blockHeight(array $lines, int $fontSize, float $lineHeight = 1.20, float $spaceAfter = 0.0): float
{
    $advance = lineAdvance($fontSize, $lineHeight);
    $height = 0.0;

    foreach ($lines as $line) {
        $height += $line === '' ? $advance * 0.65 : $advance;
    }

    return $height + $spaceAfter;
}

$document = new Document(
    version: 1.4,
    title: 'TextElement A4 Testseite',
    author: 'Kalle',
    subject: 'Technische Testseite fuer Font, Umbruch, Positionierung und Seitenumbrueche',
    language: 'de-DE',
);

$document->addKeyword('text')
    ->addKeyword('test')
    ->addKeyword('a4')
    ->addKeyword('layout')
    ->addFont('global');

$currentPage = $document->addPage(PAGE_WIDTH, PAGE_HEIGHT);
$cursorY = MARGIN_TOP;

$newPage = static function () use ($document, &$currentPage, &$cursorY): void {
    $currentPage = $document->addPage(PAGE_WIDTH, PAGE_HEIGHT);
    $cursorY = MARGIN_TOP;
};

$ensureSpace = static function (float $requiredHeight) use (&$cursorY, $newPage): void {
    // Seitenumbruch vor dem Rendern eines Blocks: nie in den unteren Rand laufen.
    if ($cursorY + $requiredHeight > MARGIN_TOP + CONTENT_HEIGHT) {
        $newPage();
    }
};

$drawLines = static function (
    array $lines,
    int $fontSize,
    string $tag = 'P',
    float $x = MARGIN_LEFT,
    float $lineHeight = 1.20,
    float $spaceAfter = 0.0
) use (&$currentPage, &$cursorY, $ensureSpace): void {
    $requiredHeight = blockHeight($lines, $fontSize, $lineHeight, $spaceAfter);
    $ensureSpace($requiredHeight);

    $advance = lineAdvance($fontSize, $lineHeight);

    foreach ($lines as $line) {
        if ($line === '') {
            $cursorY += $advance * 0.65;
            continue;
        }

        $currentPage->addText($line, $x, pageY($cursorY), TEST_FONT, $fontSize, $tag);
        $cursorY += $advance;
    }

    $cursorY += $spaceAfter;
};

$drawParagraph = static function (
    string $text,
    int $fontSize,
    string $tag = 'P',
    float $width = CONTENT_WIDTH,
    float $x = MARGIN_LEFT,
    float $lineHeight = 1.20,
    float $spaceAfter = 0.0
) use ($drawLines): void {
    $drawLines(wrapText($text, $fontSize, $width), $fontSize, $tag, $x, $lineHeight, $spaceAfter);
};

$drawSection = static function (string $title) use ($drawLines): void {
    $drawLines([$title], 11, 'H2', MARGIN_LEFT, 1.0, 3.0);
};

// Seitenbreite und nutzbare Breite sind explizit definiert und werden fuer alle Bloecke verwendet.
$drawLines(['TextElement Testseite - Schrift: ' . TEST_FONT], 16, 'H1', MARGIN_LEFT, 1.0, 3.0);
$drawParagraph(
    'A4 Hochformat | Nutzbare Breite: '
    . number_format(CONTENT_WIDTH, 1, '.', '')
    . ' | Nutzbare Hoehe: '
    . number_format(CONTENT_HEIGHT, 1, '.', '')
    . ' | Margins: links '
    . number_format(MARGIN_LEFT, 1, '.', '')
    . ', rechts '
    . number_format(MARGIN_RIGHT, 1, '.', '')
    . ', oben '
    . number_format(MARGIN_TOP, 1, '.', '')
    . ', unten '
    . number_format(MARGIN_BOTTOM, 1, '.', ''),
    8,
    'P',
    CONTENT_WIDTH,
    MARGIN_LEFT,
    1.15,
    5.0,
);

$drawSection('1. Zeichentests');
$drawParagraph('Grossbuchstaben: ABCDEFGHIJKLMNOPQRSTUVWXYZ', 8, 'P', CONTENT_WIDTH, MARGIN_LEFT, 1.15, 1.0);
$drawParagraph('Kleinbuchstaben: abcdefghijklmnopqrstuvwxyz', 8, 'P', CONTENT_WIDTH, MARGIN_LEFT, 1.15, 1.0);
$drawParagraph('Zahlen: 0123456789', 8, 'P', CONTENT_WIDTH, MARGIN_LEFT, 1.15, 1.0);
$drawParagraph("Sonderzeichen: !?.,:;+-*/=@#&%()[]{}<>_'\"“”„`", 8, 'P', CONTENT_WIDTH, MARGIN_LEFT, 1.15, 1.0);
$drawParagraph('Umlaute: Ä Ö Ü ä ö ü ß', 8, 'P', CONTENT_WIDTH, MARGIN_LEFT, 1.15, 5.0);

$drawSection('2. Beispieltexte');
$drawParagraph('„Franz jagt im komplett verwahrlosten Taxi quer durch Bayern.“', 9, 'P', CONTENT_WIDTH, MARGIN_LEFT, 1.20, 1.5);
$drawParagraph('„Falsches Üben von Xylophonmusik quält jeden größeren Zwerg.“', 9, 'P', CONTENT_WIDTH, MARGIN_LEFT, 1.20, 5.0);

$drawSection('3. Schriftgroessen');
foreach ([10, 12, 14, 16] as $size) {
    $drawParagraph(
        'Schriftgroesse ' . $size . ': TextElement Groessenvergleich 012345 ÄÖÜ äöü ß',
        $size,
        'P',
        CONTENT_WIDTH,
        MARGIN_LEFT,
        1.15,
        2.5,
    );
}

foreach ([24, 32] as $size) {
    // Grosse Schriftproben erhalten eigene Bloecke mit zusaetzlichem Abstand und Seitenumbruchschutz.
    $drawParagraph(
        'Schriftgroesse ' . $size . ': TextElement Groessenvergleich 012345 ÄÖÜ äöü ß',
        $size,
        'P',
        CONTENT_WIDTH,
        MARGIN_LEFT,
        1.10,
        6.0,
    );
}

$drawSection('4. Gezielte TextElement-Tests');
$drawParagraph('Kurze Einzeile: Positionstest bei fixer X/Y-Koordinate innerhalb der sicheren Inhaltsflaeche.', 9, 'P', CONTENT_WIDTH, MARGIN_LEFT, 1.20, 2.0);
$drawParagraph(
    'Langer Textblock mit bewusst konservativem Umbruch. Diese Zeilen sollen zeigen, ob deine TextElement-Klasse '
    . 'den gesetzten Font sauber rendert, Baselines stabil bleiben und am rechten Rand nichts abgeschnitten wird.',
    9,
    'P',
    CONTENT_WIDTH,
    MARGIN_LEFT,
    1.28,
    3.0,
);
$drawParagraph(
    'Absatz 1: Mehrere Absätze helfen beim Erkennen von ungewollten Verschiebungen zwischen einzelnen Text-Elementen.'
    . "\n\n"
    . 'Absatz 2: Wenn Zeilenabstände, Glyphenpositionen oder Sonderzeichen fehlerhaft sind, sollte das hier direkt sichtbar werden.'
    . "\n\n"
    . 'Absatz 3: Der Block wird als echter Flow-Absatz verarbeitet und vor dem Rendern auf Seitenhoehe geprueft.',
    9,
    'P',
    CONTENT_WIDTH,
    MARGIN_LEFT,
    1.30,
    3.0,
);
$drawParagraph('Line-Height 1.00: kompakt | Baseline-Abstand pruefen.', 8, 'P', CONTENT_WIDTH, MARGIN_LEFT, 1.00, 1.5);
$drawParagraph('Line-Height 1.35: etwas luftiger | keine Kollision mit dem naechsten Block.', 8, 'P', CONTENT_WIDTH, MARGIN_LEFT, 1.35, 1.5);
$drawParagraph('Line-Height 1.70: deutlich hoeher | Ueberlappungen muessen ausbleiben.', 8, 'P', CONTENT_WIDTH, MARGIN_LEFT, 1.70, 5.0);

$drawSection('5. Breitentests');
$drawParagraph(
    'Breitentest nahe am Maximum: Diese Zeilen nutzen fast die komplette sichere Inhaltsbreite aus, damit du sofort erkennst, '
    . 'ob rechts etwas ueber den A4-Rand hinausragt oder zu frueh umbricht.',
    9,
    'P',
    CONTENT_WIDTH,
    MARGIN_LEFT,
    1.24,
    2.0,
);
$drawParagraph(
    'Fliesstext fuer Rand- und Umbruchkontrolle: Ein PDF-Test ist nur dann hilfreich, wenn er reproduzierbar dieselbe '
    . 'Breite nutzt. Deshalb wird hier explizit mit linker und rechter Sicherheitsreserve gearbeitet, statt den Text bis zum '
    . 'physischen Seitenrand laufen zu lassen. Wenn ein Block nicht mehr in die Resthoehe passt, wird er auf der naechsten Seite begonnen.',
    8,
    'P',
    CONTENT_WIDTH,
    MARGIN_LEFT,
    1.30,
    2.0,
);
$drawParagraph(
    'Technischer String: TextElement::render()/wrap-width=180/line-height=1.35/font=' . TEST_FONT
    . '/payload=ÄÖÜäöüß-0123456789/overflow-check=true',
    8,
    'P',
    CONTENT_WIDTH,
    MARGIN_LEFT,
    1.20,
    5.0,
);

$drawSection('6. Positionsanker');
$anchorWidth = CONTENT_WIDTH / 2 - 4.0;
$drawParagraph('Linke sichere Kante: Text beginnt exakt am linken Content-Rand.', 8, 'P', $anchorWidth, MARGIN_LEFT, 1.15, 0.0);
$rightAnchorLines = wrapText('Rechte sichere Zone: Dieser Block endet innerhalb des rechten Content-Rands.', 8, $anchorWidth);
$drawLines($rightAnchorLines, 8, 'P', MARGIN_LEFT + CONTENT_WIDTH - $anchorWidth, 1.15, 0.0);
$cursorY += 5.0;

$drawParagraph(
    'Untere Sicherheitszone: Inhalte duerfen den unteren Margin nicht verletzen. Falls der Platz nicht reicht, muss vorher ein Seitenumbruch passieren.',
    8,
    'P',
    CONTENT_WIDTH,
    MARGIN_LEFT,
    1.20,
    0.0,
);

$pdfContent = $document->render();
$outputPath = 'output_' . (new DateTime())->format('Y-m-d-H-i-s') . '.pdf';

file_put_contents($outputPath, $pdfContent);
