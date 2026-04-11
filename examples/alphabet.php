<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Kalle\Pdf\Color\Color;
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

$margin = Margin::all(Units::mm(20));
$headlineColor = Color::hex('#0f172a');
$sectionColor = Color::hex('#1d4ed8');
$copyColor = Color::hex('#334155');
$mutedColor = Color::hex('#64748b');
$accentColor = Color::hex('#0f766e');
$pageTwoBackground = Color::hex('#f8fafc');

$latinUpper = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ ÄÖÜ';
$latinLower = 'abcdefghijklmnopqrstuvwxyz äöüß';
$numeralsAndPunctuation = '0123456789  €$£¥§%&@#*+-=!?()[]{}<>/\\.,:;\'"';
$sampleSentence = 'Sphinx of black quartz, judge my vow. Falsches Üben von Xylophonmusik quält jeden größeren Zwerg.';

$firstPageFonts = [
    StandardFont::HELVETICA->value,
    StandardFont::HELVETICA_BOLD->value,
    StandardFont::HELVETICA_BOLD_OBLIQUE->value,
    StandardFont::HELVETICA_OBLIQUE->value,
    StandardFont::TIMES_ROMAN->value,
    StandardFont::TIMES_BOLD->value,
];

$secondPageFonts = [
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
    ->pageSize(PageSize::A4())
    ->margin($margin)
    ->paragraph('PDF Standard Font Specimen', new TextOptions(
        fontSize: 24,
        lineHeight: 28,
        spacingAfter: 8,
        fontName: StandardFont::HELVETICA_BOLD->value,
        color: $headlineColor,
    ))
    ->paragraph('Referenzseite für Schriftschnitt, Zeichenumfang und Lesegrößen', new TextOptions(
        fontSize: 10,
        lineHeight: 14,
        spacingAfter: 18,
        fontName: StandardFont::HELVETICA->value,
        color: $mutedColor,
    ))
    ->paragraph('Familienübersicht', new TextOptions(
        fontSize: 12,
        lineHeight: 16,
        spacingAfter: 6,
        fontName: StandardFont::HELVETICA_BOLD->value,
        color: $sectionColor,
    ))
    ->paragraph('Diese Seiten zeigen alle Standard-PDF-Fonts mit einem konsistenten Mustertext innerhalb eines 20-mm-Satzspiegels.', new TextOptions(
        fontSize: 9,
        lineHeight: 13,
        spacingAfter: 12,
        fontName: StandardFont::HELVETICA->value,
        color: $copyColor,
    ));

foreach ($firstPageFonts as $fontName) {
    $document = $document
        ->paragraph($fontName, new TextOptions(
            fontSize: 11,
            lineHeight: 14,
            spacingAfter: 4,
            fontName: StandardFont::HELVETICA_BOLD->value,
            color: $accentColor,
        ))
        ->paragraph($sampleSentence, new TextOptions(
            fontSize: 13,
            lineHeight: 16,
            spacingAfter: 4,
            fontName: $fontName,
            color: $headlineColor,
        ))
        ->paragraph($latinUpper, new TextOptions(
            fontSize: 9,
            lineHeight: 12,
            spacingAfter: 2,
            fontName: $fontName,
            color: $copyColor,
        ))
        ->paragraph($latinLower . '    ' . $numeralsAndPunctuation, new TextOptions(
            fontSize: 8.5,
            lineHeight: 11,
            spacingAfter: 10,
            fontName: $fontName,
            color: $mutedColor,
        ));
}

$document = $document
    ->newPage(new PageOptions(
        pageSize: PageSize::A4(),
        margin: $margin,
        backgroundColor: $pageTwoBackground,
    ))
    ->paragraph('Weitere Standardschnitte', new TextOptions(
        fontSize: 20,
        lineHeight: 24,
        spacingAfter: 8,
        fontName: StandardFont::HELVETICA_BOLD->value,
        color: $headlineColor,
    ))
    ->paragraph('Die restlichen Kernfonts der Standard-14-Familie mit identischem Mustertext im selben Seitenrahmen.', new TextOptions(
        fontSize: 10,
        lineHeight: 14,
        spacingAfter: 14,
        fontName: StandardFont::HELVETICA->value,
        color: $mutedColor,
    ));

foreach ($secondPageFonts as $fontName) {
    $document = $document
        ->paragraph($fontName, new TextOptions(
            fontSize: 11,
            lineHeight: 14,
            spacingAfter: 4,
            fontName: StandardFont::HELVETICA_BOLD->value,
            color: $accentColor,
        ))
        ->paragraph($sampleSentence, new TextOptions(
            fontSize: 13,
            lineHeight: 16,
            spacingAfter: 4,
            fontName: $fontName,
            color: $headlineColor,
        ))
        ->paragraph($latinUpper, new TextOptions(
            fontSize: 9,
            lineHeight: 12,
            spacingAfter: 2,
            fontName: $fontName,
            color: $copyColor,
        ))
        ->paragraph($latinLower . '    ' . $numeralsAndPunctuation, new TextOptions(
            fontSize: 8.5,
            lineHeight: 11,
            spacingAfter: 10,
            fontName: $fontName,
            color: $mutedColor,
        ));
}

$document = $document
    ->newPage(new PageOptions(
        pageSize: PageSize::A4(),
        margin: $margin,
    ))
    ->paragraph('Größen, Laufweite und Sonderzeichen', new TextOptions(
        fontSize: 20,
        lineHeight: 24,
        spacingAfter: 8,
        fontName: StandardFont::HELVETICA_BOLD->value,
        color: $headlineColor,
    ))
    ->paragraph('Punktgrößen', new TextOptions(
        fontSize: 12,
        lineHeight: 16,
        spacingAfter: 6,
        fontName: StandardFont::HELVETICA_BOLD->value,
        color: $sectionColor,
    ));

foreach ([6, 8, 10, 12, 18, 28] as $size) {
    $document = $document->paragraph('' . $size . ' pt  Hamburgefons 12345 ÄÖÜ / Sphinx of black quartz', new TextOptions(
        fontSize: (float) $size,
        lineHeight: max(($size * 1.35), $size + 6),
        spacingAfter: 4,
        fontName: StandardFont::TIMES_ROMAN->value,
        color: $headlineColor,
    ));
}

$document = $document
    ->paragraph('Monospace-Vergleich', new TextOptions(
        fontSize: 12,
        lineHeight: 16,
        spacingAfter: 6,
        fontName: StandardFont::HELVETICA_BOLD->value,
        color: $sectionColor,
    ))
    ->paragraph('Courier eignet sich für tabellarische Ausrichtung und technische Ausgaben.', new TextOptions(
        fontSize: 9,
        lineHeight: 13,
        spacingAfter: 6,
        fontName: StandardFont::HELVETICA->value,
        color: $copyColor,
    ))
    ->paragraph('SKU-2048    17 pcs    EUR 129.90    OK', new TextOptions(
        fontSize: 11,
        lineHeight: 14,
        spacingAfter: 2,
        fontName: StandardFont::COURIER->value,
        color: $headlineColor,
    ))
    ->paragraph('SKU-2048    17 pcs    EUR 129.90    OK', new TextOptions(
        fontSize: 11,
        lineHeight: 14,
        spacingAfter: 10,
        fontName: StandardFont::COURIER_BOLD->value,
        color: $copyColor,
    ))
    ->paragraph('Symbol-Fonts', new TextOptions(
        fontSize: 12,
        lineHeight: 16,
        spacingAfter: 6,
        fontName: StandardFont::HELVETICA_BOLD->value,
        color: $sectionColor,
    ))
    ->paragraph('Symbol und ZapfDingbats werden separat gezeigt, da ihr Zeichenvorrat nicht dem lateinischen Alphabet entspricht.', new TextOptions(
        fontSize: 9,
        lineHeight: 13,
        spacingAfter: 6,
        fontName: StandardFont::HELVETICA->value,
        color: $copyColor,
    ))
    ->paragraph('ΑΒΓΔΕ ΦΓΩ αβγδε φγω ←↑→↓ ⇔ ⇒ ∞ ± ≤ ≥ × ∂ ≈ ∑ ∫', new TextOptions(
        fontSize: 12,
        lineHeight: 16,
        spacingAfter: 6,
        fontName: StandardFont::SYMBOL->value,
        color: $headlineColor,
    ))
    ->paragraph('✁ ✂ ✃ ✄ ☎ ✈ ✉ ✓ ✔ ✕ ✖ ★ ✿ ❤ ❥ ① ② ③ ④ ⑤ ➛ ➜ ➝ ➞ ➟ ➠ ➡', new TextOptions(
        fontSize: 12,
        lineHeight: 16,
        spacingAfter: 12,
        fontName: StandardFont::ZAPF_DINGBATS->value,
        color: $headlineColor,
    ))
    ->text('Hinweis: Die Musterseiten arbeiten ausschließlich mit PDF-Standardfonts und orientieren sich an einem 20-mm-Seitenrand.', new TextOptions(
        fontSize: 8.5,
        lineHeight: 12,
        fontName: StandardFont::HELVETICA->value,
        color: $mutedColor,
    ))
    ->writeToFile($outputDirectory . '/alphabet.pdf');
