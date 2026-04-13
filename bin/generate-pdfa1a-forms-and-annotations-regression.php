#!/usr/bin/env php
<?php

declare(strict_types=1);

use Kalle\Pdf\Color\Color;
use Kalle\Pdf\Document\DefaultDocumentBuilder;
use Kalle\Pdf\Document\DocumentRenderer;
use Kalle\Pdf\Document\Profile;
use Kalle\Pdf\Font\EmbeddedFontSource;
use Kalle\Pdf\Text\TextOptions;
use Kalle\Pdf\Writer\FileOutput;

require dirname(__DIR__) . '/vendor/autoload.php';

if ($argc !== 2) {
    fwrite(STDERR, "Usage: bin/generate-pdfa1a-forms-and-annotations-regression.php <output-pdf>\n");
    exit(1);
}

$fontPath = dirname(__DIR__) . '/assets/fonts/noto-sans/NotoSans-Regular.ttf';

if (!is_file($fontPath)) {
    fwrite(STDERR, sprintf("Required regression font not found: %s\n", $fontPath));
    exit(1);
}

$document = DefaultDocumentBuilder::make()
    ->profile(Profile::pdfA1a())
    ->title('PDF/A-1a Forms and Annotations Regression')
    ->author('kalle/pdf2')
    ->subject('PDF/A-1a tagged form and annotation regression fixture')
    ->language('de-DE')
    ->creator('Regression Fixture')
    ->creatorTool('bin/generate-pdfa1a-forms-and-annotations-regression.php')
    ->text('Archivformular mit Kommentar. Привет.', TextOptions::make(
        x: 72,
        y: 760,
        width: 360,
        fontSize: 12,
        lineHeight: 16,
        embeddedFont: EmbeddedFontSource::fromPath($fontPath),
    ))
    ->textAnnotation(420, 744, 18, 18, 'Kommentar', 'QA', 'Comment', true)
    ->textField('customer_name', 72, 700, 180, 18, 'Ada', 'Customer name')
    ->comboBox('status', 72, 664, 180, 18, ['new' => 'Neu', 'done' => 'Erledigt'], 'done', 'Status')
    ->listBox('skills', 72, 592, 180, 48, ['php' => 'PHP', 'pdf' => 'PDF'], ['php', 'pdf'], 'Skills')
    ->build();

$output = new FileOutput($argv[1]);
(new DocumentRenderer())->write($document, $output);
$output->close();
