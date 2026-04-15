<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Drawing;

use InvalidArgumentException;
use Kalle\Pdf\Color\Color;
use Kalle\Pdf\Drawing\StrokeStyle;
use PHPUnit\Framework\TestCase;

final class StrokeStyleTest extends TestCase
{
    public function testItStoresWidthAndColor(): void
    {
        $style = new StrokeStyle(2.5, Color::rgb(1, 0, 0));

        self::assertSame(2.5, $style->width);
        self::assertEquals(Color::rgb(1, 0, 0), $style->color);
    }

    public function testItRejectsNonPositiveWidth(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Stroke width must be greater than zero.');

        new StrokeStyle(0.0);
    }
}
