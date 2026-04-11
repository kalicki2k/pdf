<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Color;

use InvalidArgumentException;
use Kalle\Pdf\Color\Color;
use Kalle\Pdf\Color\ColorSpace;
use PHPUnit\Framework\TestCase;

final class ColorTest extends TestCase
{
    public function testItBuildsAColorFromHex(): void
    {
        $color = Color::hex('#f5f5f5');

        self::assertSame(ColorSpace::RGB, $color->space);
        self::assertSame([245 / 255, 245 / 255, 245 / 255], $color->components());
    }

    public function testItBuildsAColorFromShortHex(): void
    {
        $color = Color::hex('#abc');

        self::assertSame(ColorSpace::RGB, $color->space);
        self::assertSame([170 / 255, 187 / 255, 204 / 255], $color->components());
    }

    public function testItBuildsAGrayColor(): void
    {
        $color = Color::gray(0.5);

        self::assertSame(ColorSpace::GRAY, $color->space);
        self::assertSame([0.5], $color->components());
    }

    public function testItBuildsACmykColor(): void
    {
        $color = Color::cmyk(0.1, 0.2, 0.3, 0.4);

        self::assertSame(ColorSpace::CMYK, $color->space);
        self::assertSame([0.1, 0.2, 0.3, 0.4], $color->components());
    }

    public function testItRejectsInvalidHexValues(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Color::hex('oops');
    }

    public function testItRejectsOutOfRangeChannels(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Color::cmyk(1.1, 0.0, 0.0, 0.0);
    }
}
