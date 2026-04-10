<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Internal\Style;

use InvalidArgumentException;
use Kalle\Pdf\Style\Color;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ColorTest extends TestCase
{
    #[Test]
    public function it_renders_rgb_as_non_stroking_pdf_operator(): void
    {
        $color = Color::rgb(255, 0, 0);

        self::assertSame('1 0 0 rg', $color->renderNonStrokingOperator());
    }

    #[Test]
    public function it_renders_gray_as_non_stroking_pdf_operator(): void
    {
        $color = Color::gray(0.5);

        self::assertSame('0.5 g', $color->renderNonStrokingOperator());
    }

    #[Test]
    public function it_renders_cmyk_as_non_stroking_pdf_operator(): void
    {
        $color = Color::cmyk(0.1, 0.2, 0.3, 0.4);

        self::assertSame('0.1 0.2 0.3 0.4 k', $color->renderNonStrokingOperator());
    }

    #[Test]
    public function it_supports_hex_rgb_input(): void
    {
        $color = Color::hex('#3366CC');

        self::assertSame('0.2 0.4 0.8 rg', $color->renderNonStrokingOperator());
    }

    #[Test]
    public function it_can_render_stroking_operators_too(): void
    {
        $color = Color::rgb(255, 0, 0);

        self::assertSame('1 0 0 RG', $color->renderStrokingOperator());
    }

    #[Test]
    public function it_renders_cmyk_as_stroking_pdf_operator(): void
    {
        $color = Color::cmyk(0.1, 0.2, 0.3, 0.4);

        self::assertSame('0.1 0.2 0.3 0.4 K', $color->renderStrokingOperator());
    }

    #[Test]
    public function it_rejects_invalid_rgb_channels(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Red channel must be between 0 and 255, got 300.');

        Color::rgb(300, 0, 0);
    }

    #[Test]
    public function it_rejects_invalid_gray_values(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Gray value must be between 0.0 and 1.0, got 1.5.');

        Color::gray(1.5);
    }

    #[Test]
    public function it_rejects_invalid_cmyk_values(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Black value must be between 0.0 and 1.0, got 1.5.');

        Color::cmyk(0.1, 0.2, 0.3, 1.5);
    }

    #[Test]
    public function it_rejects_invalid_hex_values(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Hex color must be a 6-digit RGB value, got '#FFF'.");

        Color::hex('#FFF');
    }
}
