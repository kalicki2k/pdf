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
    fwrite(STDERR, "Usage: bin/generate-pdfa1a-choice-fields-regression.php <output-pdf>\n");
    exit(1);
}

$fontPath = dirname(__DIR__) . '/assets/fonts/noto-sans/NotoSans-Regular.ttf';

if (!is_file($fontPath)) {
    fwrite(STDERR, sprintf("Required regression font not found: %s\n", $fontPath));
    exit(1);
}

$document = DefaultDocumentBuilder::make()
    ->profile(Profile::pdfA1a())
    ->title('PDF/A-1a Choice Fields Regression')
    ->author('kalle/pdf2')
    ->subject('PDF/A-1a combo box and list box regression fixture')
    ->language('de-DE')
    ->creator('Regression Fixture')
    ->creatorTool('bin/generate-pdfa1a-choice-fields-regression.php')
    ->text('Formularauswahl pruefen. Привет.', TextOptions::make(
        left: 72,
        bottom: 760,
        width: 360,
        fontSize: 12,
        lineHeight: 16,
        embeddedFont: EmbeddedFontSource::fromPath($fontPath),
        color: Color::rgb(0.1, 0.1, 0.1),
    ))
    ->comboBox('status', 72, 700, 140, 18, ['new' => 'New', 'done' => 'Done'], 'done', 'Status')
    ->listBox('skills', 72, 630, 140, 48, ['php' => 'PHP', 'pdf' => 'PDF'], ['php', 'pdf'], 'Skills')
    ->build();

$output = new FileOutput($argv[1]);
(new DocumentRenderer())->write($document, $output);
$output->close();
