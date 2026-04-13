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
    ->text('PDF Standard Font Specimen', TextOptions::make(
        fontSize: 24,
        lineHeight: 28,
        spacingAfter: 8,
        fontName: StandardFont::HELVETICA_BOLD->value,
        color: $headlineColor,
    ))
    ->text('Referenzseite für Schriftschnitt, Zeichenumfang und Lesegrößen', TextOptions::make(
        fontSize: 10,
        lineHeight: 14,
        spacingAfter: 18,
        fontName: StandardFont::HELVETICA->value,
        color: $mutedColor,
    ))
    ->text('Familienübersicht', TextOptions::make(
        fontSize: 12,
        lineHeight: 16,
        spacingAfter: 6,
        fontName: StandardFont::HELVETICA_BOLD->value,
        color: $sectionColor,
    ))
    ->text('Diese Seiten zeigen alle Standard-PDF-Fonts mit einem konsistenten Mustertext innerhalb eines 20-mm-Satzspiegels.', TextOptions::make(
        fontSize: 9,
        lineHeight: 13,
        spacingAfter: 12,
        fontName: StandardFont::HELVETICA->value,
        color: $copyColor,
    ));

foreach ($firstPageFonts as $fontName) {
    $document = $document
        ->text($fontName, TextOptions::make(
            fontSize: 11,
            lineHeight: 14,
            spacingAfter: 4,
            fontName: StandardFont::HELVETICA_BOLD->value,
            color: $accentColor,
        ))
        ->text($sampleSentence, TextOptions::make(
            fontSize: 13,
            lineHeight: 16,
            spacingAfter: 4,
            fontName: $fontName,
            color: $headlineColor,
        ))
        ->text($latinUpper, TextOptions::make(
            fontSize: 9,
            lineHeight: 12,
            spacingAfter: 2,
            fontName: $fontName,
            color: $copyColor,
        ))
        ->text($latinLower . '    ' . $numeralsAndPunctuation, TextOptions::make(
            fontSize: 8.5,
            lineHeight: 11,
            spacingAfter: 10,
            fontName: $fontName,
            color: $mutedColor,
        ));
}

$document = $document
    ->newPage(new PageOptions(
        backgroundColor: $pageTwoBackground,
    ))
    ->text('Weitere Standardschnitte', TextOptions::make(
        fontSize: 20,
        lineHeight: 24,
        spacingAfter: 8,
        fontName: StandardFont::HELVETICA_BOLD->value,
        color: $headlineColor,
    ))
    ->text('Die restlichen Kernfonts der Standard-14-Familie mit identischem Mustertext im selben Seitenrahmen.', TextOptions::make(
        fontSize: 10,
        lineHeight: 14,
        spacingAfter: 14,
        fontName: StandardFont::HELVETICA->value,
        color: $mutedColor,
    ));

foreach ($secondPageFonts as $fontName) {
    $document = $document
        ->text($fontName, TextOptions::make(
            fontSize: 11,
            lineHeight: 14,
            spacingAfter: 4,
            fontName: StandardFont::HELVETICA_BOLD->value,
            color: $accentColor,
        ))
        ->text($sampleSentence, TextOptions::make(
            fontSize: 13,
            lineHeight: 16,
            spacingAfter: 4,
            fontName: $fontName,
            color: $headlineColor,
        ))
        ->text($latinUpper, TextOptions::make(
            fontSize: 9,
            lineHeight: 12,
            spacingAfter: 2,
            fontName: $fontName,
            color: $copyColor,
        ))
        ->text($latinLower . '    ' . $numeralsAndPunctuation, TextOptions::make(
            fontSize: 8.5,
            lineHeight: 11,
            spacingAfter: 10,
            fontName: $fontName,
            color: $mutedColor,
        ));
}

$document = $document
    ->newPage()
    ->text('Größen, Laufweite und Sonderzeichen', TextOptions::make(
        fontSize: 20,
        lineHeight: 24,
        spacingAfter: 8,
        fontName: StandardFont::HELVETICA_BOLD->value,
        color: $headlineColor,
    ))
    ->text('Punktgrößen', TextOptions::make(
        fontSize: 12,
        lineHeight: 16,
        spacingAfter: 6,
        fontName: StandardFont::HELVETICA_BOLD->value,
        color: $sectionColor,
    ));

foreach ([6, 8, 10, 12, 18, 28] as $size) {
    $document = $document->text('' . $size . ' pt  Hamburgefons 12345 ÄÖÜ / Sphinx of black quartz', TextOptions::make(
        fontSize: (float) $size,
        lineHeight: max(($size * 1.35), $size + 6),
        spacingAfter: 4,
        fontName: StandardFont::TIMES_ROMAN->value,
        color: $headlineColor,
    ));
}

$document
    ->text('Monospace-Vergleich', TextOptions::make(
        fontSize: 12,
        lineHeight: 16,
        spacingAfter: 6,
        fontName: StandardFont::HELVETICA_BOLD->value,
        color: $sectionColor,
    ))
    ->text('Courier eignet sich für tabellarische Ausrichtung und technische Ausgaben.', TextOptions::make(
        fontSize: 9,
        lineHeight: 13,
        spacingAfter: 6,
        fontName: StandardFont::HELVETICA->value,
        color: $copyColor,
    ))
    ->text('SKU-2048    17 pcs    EUR 129.90    OK', TextOptions::make(
        fontSize: 11,
        lineHeight: 14,
        spacingAfter: 2,
        fontName: StandardFont::COURIER->value,
        color: $headlineColor,
    ))
    ->text('SKU-2048    17 pcs    EUR 129.90    OK', TextOptions::make(
        fontSize: 11,
        lineHeight: 14,
        spacingAfter: 10,
        fontName: StandardFont::COURIER_BOLD->value,
        color: $copyColor,
    ))
    ->text('Symbol-Fonts', TextOptions::make(
        fontSize: 12,
        lineHeight: 16,
        spacingAfter: 6,
        fontName: StandardFont::HELVETICA_BOLD->value,
        color: $sectionColor,
    ))
    ->text('Symbol und ZapfDingbats werden separat gezeigt, da ihr Zeichenvorrat nicht dem lateinischen Alphabet entspricht.', TextOptions::make(
        fontSize: 9,
        lineHeight: 13,
        spacingAfter: 6,
        fontName: StandardFont::HELVETICA->value,
        color: $copyColor,
    ))
    ->text('ΑΒΓΔΕ ΦΓΩ αβγδε φγω ←↑→↓ ⇔ ⇒ ∞ ± ≤ ≥ × ∂ ≈ ∑ ∫', TextOptions::make(
        fontSize: 12,
        lineHeight: 16,
        spacingAfter: 6,
        fontName: StandardFont::SYMBOL->value,
        color: $headlineColor,
    ))
    ->text('✁ ✂ ✃ ✄ ☎ ✈ ✉ ✓ ✔ ✕ ✖ ★ ✿ ❤ ❥ ① ② ③ ④ ⑤ ➛ ➜ ➝ ➞ ➟ ➠ ➡', TextOptions::make(
        fontSize: 12,
        lineHeight: 16,
        spacingAfter: 12,
        fontName: StandardFont::ZAPF_DINGBATS->value,
        color: $headlineColor,
    ))
    ->text('Hinweis: Die Musterseiten arbeiten ausschließlich mit PDF-Standardfonts und orientieren sich an einem 20-mm-Seitenrand.', TextOptions::make(
        fontSize: 8.5,
        lineHeight: 12,
        fontName: StandardFont::HELVETICA->value,
        color: $mutedColor,
    ))
    ->writeToFile($outputDirectory . '/alphabet.pdf');
