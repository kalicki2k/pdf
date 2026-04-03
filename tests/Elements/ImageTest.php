<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Elements;

use Kalle\Pdf\Elements\Image;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ImageTest extends TestCase
{
    #[Test]
    public function it_renders_an_image_xobject_stream(): void
    {
        $image = new Image(320, 200, 'DeviceRGB', 'DCTDecode', 'abc123');

        self::assertSame(
            "<< /Type /XObject\n"
            . "/Subtype /Image\n"
            . "/Width 320\n"
            . "/Height 200\n"
            . "/ColorSpace /DeviceRGB\n"
            . "/BitsPerComponent 8\n"
            . "/Filter /DCTDecode\n"
            . "/Length 6 >>\n"
            . "stream\n"
            . "abc123\n"
            . "endstream\n",
            $image->render(),
        );
    }

    #[Test]
    public function it_inherits_position_handling_from_element(): void
    {
        $image = new Image(10, 20, 'DeviceGray', 'FlateDecode', 'data');

        $result = $image->setPosition(15.5, 25.25);

        self::assertSame($image, $result);
        self::assertSame(15.5, $image->x);
        self::assertSame(25.25, $image->y);
    }
}
