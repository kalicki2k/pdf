<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Text;

use Kalle\Pdf\Font\EmbeddedFont\EmbeddedFont;
use Kalle\Pdf\Font\EmbeddedFont\EmbeddedFontSource;
use Kalle\Pdf\Text\EmbeddedFontTextMeasurer;
use PHPUnit\Framework\TestCase;

final class EmbeddedFontTextMeasurerTest extends TestCase
{
    public function testItMeasuresTextWidthAndVerticalMetricsForAnEmbeddedFont(): void
    {
        $font = EmbeddedFont::fromSource(
            EmbeddedFontSource::fromPath(dirname(__DIR__, 2) . '/_old/assets/fonts/noto-sans/NotoSans-Regular.ttf'),
        );
        $measurer = new EmbeddedFontTextMeasurer($font);

        self::assertGreaterThan(0.0, $measurer->width('Hello', 12));
        self::assertGreaterThan(0.0, $measurer->ascent(12));
        self::assertGreaterThan(0.0, $measurer->descent(12));
    }
}
