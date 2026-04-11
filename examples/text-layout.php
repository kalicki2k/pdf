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
        fontName: StandardFont::HELVETICA_BOLD->value,
        fontSize: 24,
        lineHeight: 28,
        spacingAfter: 8,
        color: $headlineColor,
    ))
    ->paragraph('This page demonstrates align, width, maxWidth, spacingBefore, spacingAfter, firstLineIndent, and hangingIndent.', new TextOptions(
        fontSize: 10,
        lineHeight: 14,
        spacingAfter: 16,
        color: $mutedColor,
    ))
    ->paragraph('Left / width', new TextOptions(
        fontName: StandardFont::HELVETICA_BOLD->value,
        fontSize: 12,
        lineHeight: 16,
        spacingAfter: 4,
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
        fontName: StandardFont::HELVETICA_BOLD->value,
        fontSize: 12,
        lineHeight: 16,
        spacingAfter: 4,
        color: $sectionColor,
    ))
    ->paragraph($sample, new TextOptions(
        width: Units::mm(110),
        align: TextAlign::CENTER,
        fontSize: 11,
        lineHeight: 14,
        spacingAfter: 10,
        color: $bodyColor,
    ))
    ->paragraph('Right / maxWidth', new TextOptions(
        fontName: StandardFont::HELVETICA_BOLD->value,
        fontSize: 12,
        lineHeight: 16,
        spacingAfter: 4,
        color: $sectionColor,
    ))
    ->paragraph($sample, new TextOptions(
        maxWidth: Units::mm(90),
        align: TextAlign::RIGHT,
        fontSize: 11,
        lineHeight: 14,
        spacingAfter: 10,
        color: $bodyColor,
    ))
    ->paragraph('Justify / width', new TextOptions(
        fontName: StandardFont::HELVETICA_BOLD->value,
        fontSize: 12,
        lineHeight: 16,
        spacingAfter: 4,
        color: $sectionColor,
    ))
    ->paragraph($sample, new TextOptions(
        width: Units::mm(120),
        align: TextAlign::JUSTIFY,
        fontName: StandardFont::COURIER->value,
        fontSize: 10,
        lineHeight: 13,
        spacingAfter: 10,
        color: $bodyColor,
    ))
    ->paragraph('Spacing Before / After', new TextOptions(
        fontName: StandardFont::HELVETICA_BOLD->value,
        fontSize: 12,
        lineHeight: 16,
        spacingBefore: 8,
        spacingAfter: 4,
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
        fontName: StandardFont::HELVETICA_BOLD->value,
        fontSize: 12,
        lineHeight: 16,
        spacingAfter: 4,
        color: $sectionColor,
    ))
    ->paragraph($sample, new TextOptions(
        width: Units::mm(120),
        firstLineIndent: Units::mm(12),
        fontSize: 11,
        lineHeight: 14,
        spacingAfter: 10,
        color: $bodyColor,
    ))
    ->paragraph('Hanging Indent', new TextOptions(
        fontName: StandardFont::HELVETICA_BOLD->value,
        fontSize: 12,
        lineHeight: 16,
        spacingAfter: 4,
        color: $sectionColor,
    ))
    ->paragraph($sample, new TextOptions(
        width: Units::mm(120),
        hangingIndent: Units::mm(12),
        fontSize: 11,
        lineHeight: 14,
        color: $bodyColor,
    ))
    ->writeToFile($outputDirectory . '/text-layout.pdf');
