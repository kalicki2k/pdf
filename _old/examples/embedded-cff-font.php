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
    '/usr/share/fonts/opentype/urw-base35/URWGothic-Book.otf',
    '/usr/share/fonts/opentype/urw-base35/URWBookman-Light.otf',
    '/usr/share/fonts/opentype/freefont/FreeSerif.otf',
];
$fontPath = array_find($fontCandidates, fn ($candidate) => is_file($candidate));

if ($fontPath === null) {
    $fontSource = EmbeddedFontSource::fromString(base64_decode(
        'T1RUTwAIAAAAAAAAQ0ZGIAAAAAAAAACMAAAARmNtYXAAAAAAAAAA1AAAAExoZWFkAAAAAAAAASAAAAA2aGhlYQAAAAAAAAFYAAAAJGhtdHgAAAAAAAABfAAAABRtYXhwAAAAAAAAAZAAAAAGbmFtZQAAAAAAAAGYAAAAMnBvc3QAAAAAAAABzAAAABwBAAQBAAEBARBUZXN0Q2ZmLVJlZ3VsYXIAAQEBGxz/zhz/OBwDthwDIAUc//QMAhwAOw8cAD4RAAAAAAABhwACAQECAw4OAAAAAAABAAMACgAAAAwADAAAAAAAQAAAAAAAAAAEAAAAQQAAAEEAAAABAAAEFgAABBYAAAACAABOLQAATi0AAAADAAH2AAAB9gAAAAAEAAEAAAAAAAAAAAAAXw889QAAA+gAAAAAAAAAAAAAAAAAAAAA/87/OAO2AyAAAAAAAAAAAAAAAAAAAQAAAyD/OAAAA+gAAAAAAlgAAQAAAAAAAAAAAAAAAAAAAAUB9AAAAlgAAAK8AAADIAAAA4QAAAABAAAABQAAAAAAAQASAAMAAQQJAAYAIAAAAFQAZQBzAHQARgBvAG4AdAAtAFIAZQBnAHUAbABhAHIAAAADAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=',
        true,
    ) ?: throw new RuntimeException('Unable to decode fallback embedded CFF font bytes.'));
    $headline = 'AЖ中😀';
    $body = 'AЖ中😀';
} else {
    $fontSource = EmbeddedFontSource::fromPath($fontPath);
    $headline = 'Embedded OpenType CFF font';
    $body = 'This example writes a subsetted OpenType CFF font through the Unicode Type0/CID path.';
}

Pdf::document()
    ->title('Embedded CFF Font Example')
    ->author('Kalle PDF')
    ->subject('Phase 3 embedded OpenType CFF font example')
    ->creator('examples/embedded-cff-font.php')
    ->creatorTool('pdf2')
    ->text($headline, TextOptions::make(
        left: Units::mm(20),
        bottom: Units::mm(270),
        fontSize: 24,
        embeddedFont: $fontSource,
        color: Color::hex('#0f172a'),
    ))
    ->text($body, TextOptions::make(
        left: Units::mm(20),
        bottom: Units::mm(250),
        fontSize: 12,
        lineHeight: 16,
        embeddedFont: $fontSource,
        color: Color::hex('#334155'),
    ))
    ->writeToFile($outputDirectory . '/embedded-cff-font.pdf');
