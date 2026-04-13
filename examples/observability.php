<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Kalle\Pdf\Color\Color;
use Kalle\Pdf\Debug\DebugConfig;
use Kalle\Pdf\Document\Document;
use Kalle\Pdf\Document\TaggedPdf\TaggedStructureTag;
use Kalle\Pdf\Drawing\Units;
use Kalle\Pdf\Font\StandardFont;
use Kalle\Pdf\Page\Margin;
use Kalle\Pdf\Page\PageSize;
use Kalle\Pdf\Text\TextOptions;

$outputDirectory = __DIR__ . '/../var/examples';

if (!is_dir($outputDirectory) && !mkdir($outputDirectory, 0777, true) && !is_dir($outputDirectory)) {
    throw new RuntimeException('Unable to create example output directory.');
}

$pdfPath = $outputDirectory . '/observability-report.pdf';
$logPath = $outputDirectory . '/observability-report.log';

if (is_file($logPath) && !unlink($logPath)) {
    throw new RuntimeException('Unable to reset example log file.');
}

$titleColor = Color::hex('#0f172a');
$sectionColor = Color::hex('#1d4ed8');
$bodyColor = Color::hex('#334155');
$mutedColor = Color::hex('#64748b');
$accentColor = Color::hex('#0f766e');

$reportSections = [
    ['title' => 'Platform Stability', 'region' => 'Core API', 'focus' => 'error budgets, queue latency and deployment safety'],
    ['title' => 'Document Throughput', 'region' => 'Batch Export', 'focus' => 'render queue utilization and peak-hour capacity'],
    ['title' => 'Font and Image Resources', 'region' => 'Asset Pipeline', 'focus' => 'embedded font reuse and image object growth'],
    ['title' => 'Serialization Health', 'region' => 'Writer', 'focus' => 'object counts, stream sizes and trailer consistency'],
    ['title' => 'Regional Billing Output', 'region' => 'DACH', 'focus' => 'invoice generation timings and attachment volume'],
    ['title' => 'Accessibility Pipeline', 'region' => 'PDF/UA', 'focus' => 'tagging coverage, alt text and form structure'],
    ['title' => 'Archival Profiles', 'region' => 'PDF/A', 'focus' => 'metadata completeness and output intent distribution'],
    ['title' => 'Interactive Documents', 'region' => 'Forms', 'focus' => 'widget creation, link density and annotation behavior'],
    ['title' => 'Release Summary', 'region' => 'Operations', 'focus' => 'render KPIs, top regressions and next actions'],
];

$builder = Document::make()
    ->title('Observability Report Example')
    ->author('Kalle PDF')
    ->subject('Ten-page observability example for the PDF engine logger')
    ->language('en-US')
    ->creator('examples/observability.php')
    ->creatorTool('pdf2')
    ->pageSize(PageSize::A4())
    ->margin(Margin::all(Units::mm(18)))
    ->debug(
        DebugConfig::json()
            ->toFile($logPath),
    )
    ->namedDestination('contents')
    ->text('PDF Engine Observability Report', TextOptions::make(
        x: 72,
        y: 780,
        fontSize: 24,
        lineHeight: 28,
        fontName: StandardFont::HELVETICA_BOLD->value,
        color: $titleColor,
    ))
    ->text('This example produces a ten-page report and writes structured debug output as newline-delimited JSON. The built-in JsonDebugSink receives lifecycle, PDF structure and performance events through the Debugger facade while the document itself stays a regular PDF build.', TextOptions::make(
        x: 72,
        y: 738,
        width: 450,
        fontSize: 11,
        lineHeight: 15,
        color: $bodyColor,
    ))
    ->text('Contents', TextOptions::make(
        x: 72,
        y: 676,
        fontSize: 14,
        lineHeight: 18,
        fontName: StandardFont::HELVETICA_BOLD->value,
        color: $sectionColor,
    ))
    ->text('Rendered output', TextOptions::make(
        x: 72,
        y: 180,
        fontSize: 12,
        lineHeight: 16,
        fontName: StandardFont::HELVETICA_BOLD->value,
        color: $accentColor,
    ))
    ->text('PDF: ' . $pdfPath . "\nLog: " . $logPath . "\nEvents include document.created, page.added, object.created, object.serialized, xref.written, trailer.written, document.render, page.render and file.write.", TextOptions::make(
        x: 72,
        y: 150,
        width: 450,
        fontSize: 10,
        lineHeight: 14,
        color: $mutedColor,
    ));

$contentsStartY = 646.0;

foreach ($reportSections as $index => $section) {
    $pageNumber = $index + 2;
    $linkY = $contentsStartY - ($index * 46.0);
    $label = $pageNumber . '. ' . $section['title'] . ' - ' . $section['region'];

    $builder = $builder
        ->text($label, TextOptions::make(
            x: 88,
            y: $linkY,
            width: 350,
            fontSize: 12,
            lineHeight: 15,
            color: $bodyColor,
        ))
        ->text('Focus: ' . $section['focus'], TextOptions::make(
            x: 88,
            y: $linkY - 18,
            width: 380,
            fontSize: 9.5,
            lineHeight: 13,
            color: $mutedColor,
        ))
        ->linkToNamedDestination(
            'section-' . $pageNumber,
            80,
            $linkY - 6,
            380,
            26,
            'Open ' . $section['title'],
            'Open report section ' . $section['title'],
        );
}

foreach ($reportSections as $index => $section) {
    $pageNumber = $index + 2;

    $builder = $builder
        ->newPage()
        ->namedDestination('section-' . $pageNumber)
        ->text($pageNumber . '. ' . $section['title'], TextOptions::make(
            tag: TaggedStructureTag::H1,
            fontSize: 21,
            lineHeight: 25,
            spacingAfter: 6,
            fontName: StandardFont::HELVETICA_BOLD->value,
            color: $titleColor,
        ))
        ->text(
            'Region: ' . $section['region'] . '. Focus: ' . $section['focus'] . '. This page is intentionally dense enough to exercise object creation, stream serialization and performance scopes while remaining readable as a realistic operations review.',
            TextOptions::make(
                fontSize: 11,
                lineHeight: 15,
                spacingAfter: 12,
                color: $bodyColor,
            ),
        )
        ->text('Snapshot', TextOptions::make(
            fontSize: 13,
            lineHeight: 17,
            spacingAfter: 4,
            fontName: StandardFont::HELVETICA_BOLD->value,
            color: $sectionColor,
        ));

    for ($paragraphIndex = 1; $paragraphIndex <= 5; $paragraphIndex++) {
        $builder = $builder->text(
            'Window ' . $paragraphIndex . ': render throughput remained predictable across daily export batches. The team observed bounded memory growth, deterministic page construction and stable write offsets during repeated document generation. Each batch recorded enough variation to make the performance logs interesting without turning the example into random noise. Review focus stayed on ' . $section['focus'] . ' for ' . $section['region'] . '.',
            TextOptions::make(
                fontSize: 10.5,
                lineHeight: 14.5,
                spacingAfter: 8,
                color: $bodyColor,
            ),
        );
    }

    $builder = $builder
        ->text('Operational Notes', TextOptions::make(
            fontSize: 13,
            lineHeight: 17,
            spacingBefore: 6,
            spacingAfter: 4,
            fontName: StandardFont::HELVETICA_BOLD->value,
            color: $sectionColor,
        ))
        ->text(
            'The JSON sink output for this example is easiest to inspect with jq or any structured log viewer. Lifecycle events show document creation and write boundaries. PDF events reveal indirect object creation and serialization. Performance events show document-level, page-level and file-write timings together with memory deltas.',
            TextOptions::make(
                fontSize: 10.5,
                lineHeight: 14.5,
                spacingAfter: 8,
                color: $bodyColor,
            ),
        )
        ->text(
            'Use this file as a template when integrating your own debug sink setup. In production you would normally lower PDF structure logging from trace to debug or disable it outside focused investigations, while keeping lifecycle and performance channels enabled for operational visibility.',
            TextOptions::make(
                fontSize: 10.5,
                lineHeight: 14.5,
                spacingAfter: 0,
                color: $bodyColor,
            ),
        )
        ->linkToNamedDestination(
            'contents',
            72,
            48,
            140,
            16,
            'Back to contents',
            'Back to report contents',
        )
        ->text('Back to contents', TextOptions::make(
            x: 72,
            y: 58,
            fontSize: 10,
            lineHeight: 12,
            color: $accentColor,
        ));
}

$builder->writeToFile($pdfPath);

fwrite(STDOUT, "Wrote PDF example to: $pdfPath\n");
fwrite(STDOUT, "Wrote structured log to: $logPath\n");
