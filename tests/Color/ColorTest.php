<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Color;

use InvalidArgumentException;
use Kalle\Pdf\Color\Color;
use Kalle\Pdf\Color\ColorSpace;
use Kalle\Pdf\Color\MaterialColor;
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

    public function testItBuildsNamedCssColors(): void
    {
        self::assertEquals(Color::hex('#ff0000'), Color::red());
        self::assertEquals(Color::hex('#008000'), Color::green());
        self::assertEquals(Color::hex('#0000ff'), Color::blue());
        self::assertEquals(Color::hex('#ffff00'), Color::yellow());
        self::assertEquals(Color::hex('#00ffff'), Color::cyan());
        self::assertEquals(Color::hex('#ff00ff'), Color::magenta());
        self::assertEquals(Color::hex('#ffa500'), Color::orange());
        self::assertEquals(Color::hex('#800080'), Color::purple());
        self::assertEquals(Color::hex('#ffc0cb'), Color::pink());
        self::assertEquals(Color::hex('#a52a2a'), Color::brown());
        self::assertEquals(Color::hex('#00ff00'), Color::lime());
        self::assertEquals(Color::hex('#000080'), Color::navy());
        self::assertEquals(Color::hex('#008080'), Color::teal());
        self::assertEquals(Color::hex('#808000'), Color::olive());
        self::assertEquals(Color::hex('#800000'), Color::maroon());
        self::assertEquals(Color::hex('#c0c0c0'), Color::silver());
    }

    public function testItBuildsMaterialColors(): void
    {
        self::assertEquals(Color::hex('#F44336'), Color::material(MaterialColor::RED));
        self::assertEquals(Color::hex('#2196F3'), Color::material(MaterialColor::BLUE, 500));
        self::assertEquals(Color::hex('#82B1FF'), Color::material(MaterialColor::BLUE, 'A100'));
        self::assertEquals(Color::hex('#607D8B'), Color::material(MaterialColor::BLUE_GREY));
        self::assertEquals(Color::hex('#212121'), Color::material(MaterialColor::GREY, 900));
    }

    public function testItRejectsInvalidHexValues(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Color::hex('oops');
    }

    public function testItRejectsUnsupportedMaterialShades(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Color::material(MaterialColor::GREY, 'A100');
    }

    public function testItRejectsOutOfRangeChannels(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Color::cmyk(1.1, 0.0, 0.0, 0.0);
    }
}
