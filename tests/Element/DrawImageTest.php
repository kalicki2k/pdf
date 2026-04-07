<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Element;

use Kalle\Pdf\Element\DrawImage;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DrawImageTest extends TestCase
{
    #[Test]
    public function it_renders_an_image_draw_command(): void
    {
        $image = new DrawImage('Im1', 10, 20, 100, 40);

        self::assertSame(
            "q\n"
            . "100 0 0 40 10 20 cm\n"
            . "/Im1 Do\n"
            . 'Q',
            $image->render(),
        );
    }

    #[Test]
    public function it_formats_decimal_dimensions_without_trailing_zeroes(): void
    {
        $image = new DrawImage('Im2', 10.5, 20.25, 100.5, 40.75);

        self::assertSame(
            "q\n"
            . "100.5 0 0 40.75 10.5 20.25 cm\n"
            . "/Im2 Do\n"
            . 'Q',
            $image->render(),
        );
    }
}
