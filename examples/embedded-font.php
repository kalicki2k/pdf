<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Kalle\Pdf\Color\Color;
use Kalle\Pdf\Drawing\Units;
use Kalle\Pdf\Font\EmbeddedFontSource;
use Kalle\Pdf\Pdf;
use Kalle\Pdf\Text\TextOptions;

$outputDirectory = __DIR__ . '/../var/examples';

if (!is_dir($outputDirectory) && !mkdir($outputDirectory, 0777, true) && !is_dir($outputDirectory)) {
    throw new RuntimeException('Unable to create example output directory.');
}

$fontCandidates = [
    '/usr/share/fonts/adwaita-sans-fonts/AdwaitaSans-Regular.ttf',
    '/usr/share/fonts/google-carlito-fonts/Carlito-Regular.ttf',
    '/usr/share/fonts/google-droid-sans-fonts/DroidSans.ttf',
];
$fontPath = array_find($fontCandidates, fn ($candidate) => is_file($candidate));

if ($fontPath === null) {
    $fontSource = EmbeddedFontSource::fromString(base64_decode(
        'AAEAAAAHAAAAAAAAY21hcAAAAAAAAAB8AAAALGhlYWQAAAAAAAAAqAAAADZoaGVhAAAAAAAAAOAAAAAkaG10eAAAAAAAAAEEAAAACG1heHAAAAAAAAABDAAAAAZuYW1lAAAAAAAAARQAAAAycG9zdAAAAAAAAAFIAAAAHAAAAAEAAwABAAAADAAEACAAAAAEAAQAAQAAAEH//wAAAEH////AAAEAAAAAAAEAAAAAAAAAAAAAXw889QAAA+gAAAAAAAAAAAAAAAAAAAAA/87/OAO2AyAAAAAAAAAAAAAAAAAAAQAAAyD/OAAAA+gAAAAAAlgAAQAAAAAAAAAAAAAAAAAAAAIB9AAAAlgAAAABAAAAAgAAAAAAAQASAAMAAQQJAAYAIAAAAFQAZQBzAHQARgBvAG4AdAAtAFIAZQBnAHUAbABhAHIAAAADAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=',
        true,
    ) ?: throw new RuntimeException('Unable to decode fallback embedded font bytes.'));
} else {
    $fontSource = EmbeddedFontSource::fromPath($fontPath);
}

$headline = $fontPath === null ? 'AAA' : 'Embedded TrueType font';
$body = $fontPath === null
    ? 'AAAAAAAAAA'
    : 'This example uses an embedded TrueType font in a simple WinAnsi PDF font resource.';

Pdf::document()
    ->title('Embedded Font Example')
    ->author('Kalle PDF')
    ->subject('Phase 1 embedded TrueType font example')
    ->creator('examples/embedded-font.php')
    ->creatorTool('pdf2')
    ->text($headline, TextOptions::make(
        x: Units::mm(20),
        y: Units::mm(270),
        fontSize: 24,
        embeddedFont: $fontSource,
        color: Color::hex('#0f172a'),
    ))
    ->text($body, TextOptions::make(
        x: Units::mm(20),
        y: Units::mm(250),
        fontSize: 12,
        lineHeight: 16,
        embeddedFont: $fontSource,
        color: Color::hex('#334155'),
    ))
    ->writeToFile($outputDirectory . '/embedded-font.pdf');
