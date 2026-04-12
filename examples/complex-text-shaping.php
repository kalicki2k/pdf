<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Kalle\Pdf\Color\Color;
use Kalle\Pdf\Drawing\Units;
use Kalle\Pdf\Font\EmbeddedFontSource;
use Kalle\Pdf\Font\StandardFont;
use Kalle\Pdf\Page\Margin;
use Kalle\Pdf\Page\PageSize;
use Kalle\Pdf\Pdf;
use Kalle\Pdf\Text\TextOptions;

$outputDirectory = __DIR__ . '/../var/examples';

if (!is_dir($outputDirectory) && !mkdir($outputDirectory, 0777, true) && !is_dir($outputDirectory)) {
    throw new RuntimeException('Unable to create example output directory.');
}

$fontPath = __DIR__ . '/../assets/fonts/poppins/static/Poppins-Regular.ttf';

if (!is_file($fontPath)) {
    throw new RuntimeException(sprintf('Complex shaping example font not found: %s', $fontPath));
}

$fontSource = EmbeddedFontSource::fromPath($fontPath);
$margin = Margin::all(Units::mm(20));
$headlineColor = Color::hex('#0f172a');
$sectionColor = Color::hex('#1d4ed8');
$bodyColor = Color::hex('#334155');
$sampleColor = Color::hex('#111827');
$mutedColor = Color::hex('#64748b');

Pdf::document()
    ->title('Complex Text Shaping Example')
    ->author('Kalle PDF')
    ->subject('Embedded font shaping example with Latin and Devanagari samples')
    ->creator('examples/complex-text-shaping.php')
    ->creatorTool('pdf2')
    ->pageSize(PageSize::A4())
    ->margin($margin)
    ->text('Complex Text Shaping', new TextOptions(
        fontName: StandardFont::HELVETICA_BOLD->value,
        fontSize: 24,
        lineHeight: 28,
        spacingAfter: 8,
        color: $headlineColor,
    ))
    ->text('This example uses assets/fonts/poppins/static/Poppins-Regular.ttf as an embedded TrueType font and exercises the current text-shaping pipeline without any external shaping engine.', new TextOptions(
        fontSize: 11,
        lineHeight: 15,
        spacingAfter: 8,
        color: $bodyColor,
    ))
    ->text('What this page is useful for', new TextOptions(
        fontName: StandardFont::HELVETICA_BOLD->value,
        fontSize: 13,
        lineHeight: 18,
        spacingAfter: 4,
        color: $sectionColor,
    ))
    ->text('1. Verify that a real asset font is embedded. 2. Inspect shaping-sensitive Latin words. 3. Inspect Devanagari cluster ordering, reph-like behavior and stacked marks. 4. Use qpdf or visual review against later shaping changes.', new TextOptions(
        fontSize: 11,
        lineHeight: 15,
        spacingAfter: 12,
        color: $bodyColor,
    ))
    ->text('Latin embedded-font samples', new TextOptions(
        fontName: StandardFont::HELVETICA_BOLD->value,
        fontSize: 13,
        lineHeight: 18,
        spacingAfter: 4,
        color: $sectionColor,
    ))
    ->text('office official affinity efficient first-office raffle offline fi ffi ffl', new TextOptions(
        embeddedFont: $fontSource,
        fontSize: 18,
        lineHeight: 24,
        spacingAfter: 6,
        color: $sampleColor,
    ))
    ->text('The exact ligature behavior depends on the font tables present in the embedded font. This line is useful as a broad regression sample for Latin GSUB and spacing changes.', new TextOptions(
        fontSize: 10,
        lineHeight: 14,
        spacingAfter: 12,
        color: $mutedColor,
    ))
    ->text('Devanagari shaping samples', new TextOptions(
        fontName: StandardFont::HELVETICA_BOLD->value,
        fontSize: 13,
        lineHeight: 18,
        spacingAfter: 4,
        color: $sectionColor,
    ))
    ->text('कि  किं  किं़  र्कि  स्त्कि', new TextOptions(
        embeddedFont: $fontSource,
        fontSize: 24,
        lineHeight: 32,
        spacingAfter: 6,
        color: $sampleColor,
    ))
    ->text('These clusters intentionally hit the current Indic path: pre-base matra, same-cluster mark, stacked mark, reph-style leading ra-virama, and a pref-like multi-half-form cluster.', new TextOptions(
        fontSize: 10,
        lineHeight: 14,
        spacingAfter: 12,
        color: $mutedColor,
    ))
    ->text('One sample per line', new TextOptions(
        fontName: StandardFont::HELVETICA_BOLD->value,
        fontSize: 13,
        lineHeight: 18,
        spacingAfter: 4,
        color: $sectionColor,
    ))
    ->text('कि', new TextOptions(
        embeddedFont: $fontSource,
        fontSize: 28,
        lineHeight: 34,
        spacingAfter: 4,
        color: $sampleColor,
    ))
    ->text('किं', new TextOptions(
        embeddedFont: $fontSource,
        fontSize: 28,
        lineHeight: 34,
        spacingAfter: 4,
        color: $sampleColor,
    ))
    ->text('किं़', new TextOptions(
        embeddedFont: $fontSource,
        fontSize: 28,
        lineHeight: 34,
        spacingAfter: 4,
        color: $sampleColor,
    ))
    ->text('र्कि', new TextOptions(
        embeddedFont: $fontSource,
        fontSize: 28,
        lineHeight: 34,
        spacingAfter: 4,
        color: $sampleColor,
    ))
    ->text('स्त्कि', new TextOptions(
        embeddedFont: $fontSource,
        fontSize: 28,
        lineHeight: 34,
        spacingAfter: 8,
        color: $sampleColor,
    ))
    ->text('When the shaping implementation changes, this page should make regressions visible quickly: wrong cluster order, missing pre-base movement, lost marks, broken reph handling or changed vertical mark placement.', new TextOptions(
        fontSize: 10,
        lineHeight: 14,
        color: $mutedColor,
    ))
    ->writeToFile($outputDirectory . '/complex-text-shaping.pdf');
