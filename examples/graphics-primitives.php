<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Kalle\Pdf\Color\Color;
use Kalle\Pdf\Document\DefaultDocumentBuilder;
use Kalle\Pdf\Drawing\Path;
use Kalle\Pdf\Drawing\StrokeStyle;
use Kalle\Pdf\Drawing\Units;
use Kalle\Pdf\Page\Margin;
use Kalle\Pdf\Page\PageSize;
use Kalle\Pdf\Text\TextOptions;

$outputDirectory = __DIR__ . '/../var/examples';

if (!is_dir($outputDirectory) && !mkdir($outputDirectory, 0777, true) && !is_dir($outputDirectory)) {
    throw new RuntimeException('Unable to create example output directory.');
}

$margin = Margin::all(Units::mm(18));
$titleColor = Color::hex('#0f172a');
$bodyColor = Color::hex('#334155');
$mutedColor = Color::hex('#64748b');
$accentColor = Color::hex('#1d4ed8');
$dangerColor = Color::hex('#b91c1c');
$fillBlue = Color::hex('#dbeafe');
$fillRed = Color::hex('#fee2e2');
$fillAmber = Color::hex('#fef3c7');

$triangle = Path::builder()
    ->moveTo(70, 470)
    ->lineTo(125, 545)
    ->lineTo(180, 470)
    ->close()
    ->build();

$wave = Path::builder()
    ->moveTo(70, 335)
    ->curveTo(95, 360, 120, 310, 145, 335)
    ->curveTo(170, 360, 195, 310, 220, 335)
    ->curveTo(245, 360, 270, 310, 295, 335)
    ->build();

DefaultDocumentBuilder::make()
    ->title('Graphics Primitives Example')
    ->author('Kalle PDF')
    ->subject('Shows the first public graphics API in pdf2')
    ->creator('examples/graphics-primitives.php')
    ->creatorTool('pdf2')
    ->pageSize(PageSize::A4())
    ->margin($margin)
    ->text('Graphics Primitives', TextOptions::make(
        x: $margin->left,
        y: PageSize::A4()->height() - $margin->top,
        fontSize: 24,
        color: $titleColor,
    ))
    ->text('This example combines several small graphics examples on one page: lines, stroked and filled rectangles, a rounded panel and custom paths.', TextOptions::make(
        x: $margin->left,
        y: 760,
        width: 470,
        fontSize: 11,
        lineHeight: 16,
        color: $bodyColor,
    ))
    ->text('1. Line styles', TextOptions::make(
        x: 55,
        y: 710,
        fontSize: 14,
        color: $accentColor,
    ))
    ->line(70, 685, 250, 685, new StrokeStyle(1.0, Color::gray(0.25)))
    ->line(70, 665, 250, 665, new StrokeStyle(3.0, $accentColor))
    ->line(70, 645, 250, 645, new StrokeStyle(6.0, $dangerColor))
    ->text('Thin neutral, medium accent, heavy alert.', TextOptions::make(
        x: 270,
        y: 665,
        width: 220,
        fontSize: 10,
        lineHeight: 14,
        color: $mutedColor,
    ))
    ->text('2. Rectangles', TextOptions::make(
        x: 55,
        y: 605,
        fontSize: 14,
        color: $accentColor,
    ))
    ->rectangle(70, 520, 90, 50, new StrokeStyle(1.5, $accentColor))
    ->rectangle(180, 520, 90, 50, fillColor: $fillBlue)
    ->rectangle(290, 520, 90, 50, new StrokeStyle(1.5, $dangerColor), $fillRed)
    ->text('Stroke only', TextOptions::make(x: 82, y: 500, fontSize: 10, color: $mutedColor))
    ->text('Fill only', TextOptions::make(x: 195, y: 500, fontSize: 10, color: $mutedColor))
    ->text('Fill + stroke', TextOptions::make(x: 298, y: 500, fontSize: 10, color: $mutedColor))
    ->text('3. Paths', TextOptions::make(
        x: 55,
        y: 455,
        fontSize: 14,
        color: $accentColor,
    ))
    ->path($triangle, new StrokeStyle(1.5, $dangerColor), $fillAmber)
    ->path($wave, new StrokeStyle(2.0, $accentColor))
    ->text('A closed triangle path can be filled and stroked; an open path stays stroked only.', TextOptions::make(
        x: 320,
        y: 410,
        width: 170,
        fontSize: 10,
        lineHeight: 14,
        color: $mutedColor,
    ))
    ->text('4. Rounded rectangle', TextOptions::make(
        x: 55,
        y: 280,
        fontSize: 14,
        color: $accentColor,
    ))
    ->roundedRectangle(70, 120, 420, 120, 18, new StrokeStyle(1.25, $accentColor), $fillBlue)
    ->text('Rounded rectangles reuse the same path machinery internally. That keeps the public API small while still covering a common page-decoration primitive.', TextOptions::make(
        x: 95,
        y: 205,
        width: 360,
        fontSize: 11,
        lineHeight: 16,
        color: $bodyColor,
    ))
    ->text('Output: var/examples/graphics-primitives.pdf', TextOptions::make(
        x: 95,
        y: 145,
        fontSize: 10,
        color: $mutedColor,
    ))
    ->writeToFile($outputDirectory . '/graphics-primitives.pdf');
