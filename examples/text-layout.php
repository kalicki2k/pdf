<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Kalle\Pdf\Color\Color;
use Kalle\Pdf\Drawing\Units;
use Kalle\Pdf\Font\StandardFont;
use Kalle\Pdf\Page\Margin;
use Kalle\Pdf\Page\PageSize;
use Kalle\Pdf\Pdf;
use Kalle\Pdf\Text\TextAlign;
use Kalle\Pdf\Text\TextOptions;

$outputDirectory = __DIR__ . '/../var/examples';

if (!is_dir($outputDirectory) && !mkdir($outputDirectory, 0777, true) && !is_dir($outputDirectory)) {
    throw new RuntimeException('Unable to create example output directory.');
}

$margin = Margin::all(Units::mm(20));
$headlineColor = Color::hex('#0f172a');
$sectionColor = Color::hex('#1d4ed8');
$bodyColor = Color::hex('#334155');
$mutedColor = Color::hex('#64748b');

$sample = 'The quick brown fox jumps over the lazy dog. Sphinx of black quartz, judge my vow.';

Pdf::document()
    ->title('Text Layout Example')
    ->author('Kalle PDF')
    ->subject('Demonstrates text alignment and block layout options')
    ->creator('examples/text-layout.php')
    ->creatorTool('pdf2')
    ->pageSize(PageSize::A4())
    ->margin($margin)
    ->paragraph('Text Layout Options', new TextOptions(
        fontSize: 24,
        lineHeight: 28,
        spacingAfter: 8,
        fontName: StandardFont::HELVETICA_BOLD->value,
        color: $headlineColor,
    ))
    ->paragraph('This page demonstrates align, width, maxWidth, spacingBefore, spacingAfter, firstLineIndent, and hangingIndent.', new TextOptions(
        fontSize: 10,
        lineHeight: 14,
        spacingAfter: 16,
        color: $mutedColor,
    ))
    ->paragraph('Left / width', new TextOptions(
        fontSize: 12,
        lineHeight: 16,
        spacingAfter: 4,
        fontName: StandardFont::HELVETICA_BOLD->value,
        color: $sectionColor,
    ))
    ->paragraph($sample, new TextOptions(
        width: Units::mm(80),
        fontSize: 11,
        lineHeight: 14,
        spacingAfter: 10,
        color: $bodyColor,
    ))
    ->paragraph('Center / width', new TextOptions(
        fontSize: 12,
        lineHeight: 16,
        spacingAfter: 4,
        fontName: StandardFont::HELVETICA_BOLD->value,
        color: $sectionColor,
    ))
    ->paragraph($sample, new TextOptions(
        width: Units::mm(110),
        fontSize: 11,
        lineHeight: 14,
        spacingAfter: 10,
        color: $bodyColor,
        align: TextAlign::CENTER,
    ))
    ->paragraph('Right / maxWidth', new TextOptions(
        fontSize: 12,
        lineHeight: 16,
        spacingAfter: 4,
        fontName: StandardFont::HELVETICA_BOLD->value,
        color: $sectionColor,
    ))
    ->paragraph($sample, new TextOptions(
        maxWidth: Units::mm(90),
        fontSize: 11,
        lineHeight: 14,
        spacingAfter: 10,
        color: $bodyColor,
        align: TextAlign::RIGHT,
    ))
    ->paragraph('Justify / width', new TextOptions(
        fontSize: 12,
        lineHeight: 16,
        spacingAfter: 4,
        fontName: StandardFont::HELVETICA_BOLD->value,
        color: $sectionColor,
    ))
    ->paragraph($sample, new TextOptions(
        width: Units::mm(120),
        fontSize: 10,
        lineHeight: 13,
        spacingAfter: 10,
        fontName: StandardFont::COURIER->value,
        color: $bodyColor,
        align: TextAlign::JUSTIFY,
    ))
    ->paragraph('Spacing Before / After', new TextOptions(
        fontSize: 12,
        lineHeight: 16,
        spacingBefore: 8,
        spacingAfter: 4,
        fontName: StandardFont::HELVETICA_BOLD->value,
        color: $sectionColor,
    ))
    ->paragraph('This paragraph starts after spacingBefore and leaves space for the next block via spacingAfter.', new TextOptions(
        width: Units::mm(110),
        fontSize: 11,
        lineHeight: 14,
        spacingAfter: 12,
        color: $bodyColor,
    ))
    ->paragraph('First Line Indent', new TextOptions(
        fontSize: 12,
        lineHeight: 16,
        spacingAfter: 4,
        fontName: StandardFont::HELVETICA_BOLD->value,
        color: $sectionColor,
    ))
    ->paragraph($sample, new TextOptions(
        width: Units::mm(120),
        fontSize: 11,
        lineHeight: 14,
        spacingAfter: 10,
        color: $bodyColor,
        firstLineIndent: Units::mm(12),
    ))
    ->paragraph('Hanging Indent', new TextOptions(
        fontSize: 12,
        lineHeight: 16,
        spacingAfter: 4,
        fontName: StandardFont::HELVETICA_BOLD->value,
        color: $sectionColor,
    ))
    ->paragraph($sample, new TextOptions(
        width: Units::mm(120),
        fontSize: 11,
        lineHeight: 14,
        color: $bodyColor,
        hangingIndent: Units::mm(12),
    ))
    ->writeToFile($outputDirectory . '/text-layout.pdf');
