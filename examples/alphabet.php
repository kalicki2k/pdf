<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Kalle\Pdf\Color\Color;
use Kalle\Pdf\Document\DocumentBuilder;
use Kalle\Pdf\Drawing\Units;
use Kalle\Pdf\Font\StandardFont;
use Kalle\Pdf\Page\Margin;
use Kalle\Pdf\Page\PageOptions;
use Kalle\Pdf\Page\PageSize;
use Kalle\Pdf\Pdf;
use Kalle\Pdf\Text\TextOptions;

$outputDirectory = __DIR__ . '/../var/examples';

if (!is_dir($outputDirectory) && !mkdir($outputDirectory, 0777, true) && !is_dir($outputDirectory)) {
    throw new RuntimeException('Unable to create example output directory.');
}

$headlineColor = Color::hex('#0f172a');
$sectionColor = Color::hex('#1d4ed8');
$copyColor = Color::hex('#334155');
$mutedColor = Color::hex('#64748b');
$accentColor = Color::hex('#0f766e');
$pageTwoBackground = Color::hex('#f8fafc');
$pageMargin = Margin::all(Units::mm(20));

$latinUpper = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ ÄÖÜ';
$latinLower = 'abcdefghijklmnopqrstuvwxyz äöüß';
$numeralsAndPunctuation = '0123456789  €$£¥§%&@#*+-=!?()[]{}<>/\\.,:;\'"';
$sampleSentence = 'Sphinx of black quartz, judge my vow. Falsches Üben von Xylophonmusik quält jeden größeren Zwerg.';

$fonts = [
    StandardFont::HELVETICA->value,
    StandardFont::HELVETICA_BOLD->value,
    StandardFont::HELVETICA_BOLD_OBLIQUE->value,
    StandardFont::HELVETICA_OBLIQUE->value,
    StandardFont::TIMES_ROMAN->value,
    StandardFont::TIMES_BOLD->value,
    StandardFont::TIMES_BOLD_ITALIC->value,
    StandardFont::TIMES_ITALIC->value,
    StandardFont::COURIER->value,
    StandardFont::COURIER_BOLD->value,
    StandardFont::COURIER_BOLD_OBLIQUE->value,
    StandardFont::COURIER_OBLIQUE->value,
];

$document = Pdf::document()
    ->title('Font Specimen Sheet')
    ->author('Kalle PDF')
    ->subject('Professional specimen page for PDF standard fonts')
    ->creator('examples/alphabet.php')
    ->creatorTool('pdf2')
    ->margin($pageMargin)
    ->pageSize(PageSize::A4());

$placeText = static function (
    DocumentBuilder $builder,
    string $text,
    ?float $xMm,
    float $yMm,
    float $fontSize,
    string $fontName,
    ?Color $color = null,
): DocumentBuilder {
    return $builder->text($text, new TextOptions(
        x: $xMm === null ? null : Units::mm($xMm),
        y: Units::mm($yMm),
        fontSize: $fontSize,
        fontName: $fontName,
        color: $color,
    ));
};

$document = $placeText(
    $document,
    'PDF Standard Font Specimen',
    null,
    281,
    24,
    StandardFont::HELVETICA_BOLD->value,
    $headlineColor,
);
$document = $placeText(
    $document,
    'Referenzseite für Schriftschnitt, Zeichenumfang und Lesegrößen',
    null,
    272,
    10,
    StandardFont::HELVETICA->value,
    $mutedColor,
);
$document = $placeText(
    $document,
    'Familienübersicht',
    null,
    258,
    12,
    StandardFont::HELVETICA_BOLD->value,
    $sectionColor,
);
$document = $placeText(
    $document,
    'Diese Seiten zeigen alle Standard-PDF-Fonts mit einem konsistenten Mustertext innerhalb eines 20-mm-Satzspiegels.',
    null,
    251,
    9,
    StandardFont::HELVETICA->value,
    $copyColor,
);

$firstPageFonts = array_slice($fonts, 0, 6);
$secondPageFonts = array_slice($fonts, 6);
$startY = 237.0;
$rowStep = 27.0;

foreach ($firstPageFonts as $index => $fontName) {
    $baseline = $startY - ($index * $rowStep);

    $document = $placeText(
        $document,
        $fontName,
        null,
        $baseline + 8,
        11,
        StandardFont::HELVETICA_BOLD->value,
        $accentColor,
    );
    $document = $placeText(
        $document,
        $sampleSentence,
        null,
        $baseline,
        13,
        $fontName,
        $headlineColor,
    );
    $document = $placeText(
        $document,
        $latinUpper,
        null,
        $baseline - 7,
        9,
        $fontName,
        $copyColor,
    );
    $document = $placeText(
        $document,
        $latinLower . '    ' . $numeralsAndPunctuation,
        null,
        $baseline - 13,
        8.5,
        $fontName,
        $mutedColor,
    );
}

$document = $document->newPage(new PageOptions(
    pageSize: PageSize::A4(),
    margin: $pageMargin,
    backgroundColor: $pageTwoBackground,
));

$document = $placeText(
    $document,
    'Weitere Standardschnitte',
    null,
    281,
    20,
    StandardFont::HELVETICA_BOLD->value,
    $headlineColor,
);
$document = $placeText(
    $document,
    'Die restlichen Kernfonts der Standard-14-Familie mit identischem Mustertext im selben Seitenrahmen.',
    null,
    272,
    10,
    StandardFont::HELVETICA->value,
    $mutedColor,
);

foreach ($secondPageFonts as $index => $fontName) {
    $baseline = 257.0 - ($index * $rowStep);

    $document = $placeText(
        $document,
        $fontName,
        null,
        $baseline + 8,
        11,
        StandardFont::HELVETICA_BOLD->value,
        $accentColor,
    );
    $document = $placeText(
        $document,
        $sampleSentence,
        null,
        $baseline,
        13,
        $fontName,
        $headlineColor,
    );
    $document = $placeText(
        $document,
        $latinUpper,
        null,
        $baseline - 7,
        9,
        $fontName,
        $copyColor,
    );
    $document = $placeText(
        $document,
        $latinLower . '    ' . $numeralsAndPunctuation,
        null,
        $baseline - 13,
        8.5,
        $fontName,
        $mutedColor,
    );
}

$document = $document->newPage(new PageOptions(
    pageSize: PageSize::A4(),
    margin: $pageMargin,
));

$document = $placeText(
    $document,
    'Größen, Laufweite und Sonderzeichen',
    null,
    281,
    20,
    StandardFont::HELVETICA_BOLD->value,
    $headlineColor,
);
$document = $placeText(
    $document,
    'Punktgrößen',
    null,
    266,
    12,
    StandardFont::HELVETICA_BOLD->value,
    $sectionColor,
);

$sizeRows = [
    ['6 pt', 6.0, 254.0],
    ['8 pt', 8.0, 245.0],
    ['10 pt', 10.0, 234.0],
    ['12 pt', 12.0, 221.0],
    ['18 pt', 18.0, 205.0],
    ['28 pt', 28.0, 183.0],
];

foreach ($sizeRows as [$label, $size, $y]) {
    $document = $placeText(
        $document,
        $label,
        null,
        $y,
        9,
        StandardFont::HELVETICA_BOLD->value,
        $accentColor,
    );
    $document = $placeText(
        $document,
        'Hamburgefons 12345 ÄÖÜ / Sphinx of black quartz',
        40,
        $y,
        $size,
        StandardFont::TIMES_ROMAN->value,
        $headlineColor,
    );
}

$document = $placeText(
    $document,
    'Monospace-Vergleich',
    null,
    156,
    12,
    StandardFont::HELVETICA_BOLD->value,
    $sectionColor,
);
$document = $placeText(
    $document,
    'Courier eignet sich für tabellarische Ausrichtung und technische Ausgaben.',
    null,
    149,
    9,
    StandardFont::HELVETICA->value,
    $copyColor,
);
$document = $placeText(
    $document,
    'SKU-2048    17 pcs    EUR 129.90    OK',
    null,
    139,
    11,
    StandardFont::COURIER->value,
    $headlineColor,
);
$document = $placeText(
    $document,
    'SKU-2048    17 pcs    EUR 129.90    OK',
    null,
    132,
    11,
    StandardFont::COURIER_BOLD->value,
    $copyColor,
);

$document = $placeText(
    $document,
    'Symbol-Fonts',
    null,
    117,
    12,
    StandardFont::HELVETICA_BOLD->value,
    $sectionColor,
);
$document = $placeText(
    $document,
    'Symbol und ZapfDingbats werden separat gezeigt, da ihr Zeichenvorrat nicht dem lateinischen Alphabet entspricht.',
    null,
    110,
    9,
    StandardFont::HELVETICA->value,
    $copyColor,
);
$document = $placeText(
    $document,
    'ΑΒΓΔΕ ΦΓΩ αβγδε φγω ←↑→↓ ⇔ ⇒ ∞ ± ≤ ≥ × ∂ ≈ ∑ ∫',
    null,
    100,
    12,
    StandardFont::SYMBOL->value,
    $headlineColor,
);
$document = $placeText(
    $document,
    '✁ ✂ ✃ ✄ ☎ ✈ ✉ ✓ ✔ ✕ ✖ ★ ✿ ❤ ❥ ① ② ③ ④ ⑤ ➛ ➜ ➝ ➞ ➟ ➠ ➡',
    null,
    90,
    12,
    StandardFont::ZAPF_DINGBATS->value,
    $headlineColor,
);

$document = $placeText(
    $document,
    'Hinweis: Die Musterseiten arbeiten ausschließlich mit PDF-Standardfonts und orientieren sich an einem 20-mm-Seitenrand.',
    null,
    24,
    8.5,
    StandardFont::HELVETICA->value,
    $mutedColor,
);

$document->save($outputDirectory . '/alphabet.pdf');
