<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Element;

use Kalle\Pdf\Element\Image;
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

    #[Test]
    public function it_creates_an_image_from_a_png_file_and_detects_the_pdf_parameters(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'pdf-image-');

        if ($path === false) {
            self::fail('Unable to create temporary file.');
        }

        file_put_contents($path, $this->createPng(width: 1, height: 1, grayscaleValue: 127));

        try {
            $image = Image::fromFile($path);

            self::assertSame(1, $image->getWidth());
            self::assertSame(1, $image->getHeight());
            self::assertStringContainsString('/ColorSpace /DeviceGray', $image->render());
            self::assertStringContainsString('/Filter /FlateDecode', $image->render());
            self::assertStringContainsString('/DecodeParms << /Predictor 15 /Colors 1 /BitsPerComponent 8 /Columns 1 >>', $image->render());
        } finally {
            @unlink($path);
        }
    }

    #[Test]
    public function it_rejects_unsupported_image_types_when_loading_from_file(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'pdf-image-');

        if ($path === false) {
            self::fail('Unable to create temporary file.');
        }

        file_put_contents($path, base64_decode('R0lGODdhAQABAIAAAP///////ywAAAAAAQABAAACAkQBADs=', true));

        try {
            $this->expectException(\InvalidArgumentException::class);
            $this->expectExceptionMessage("Unsupported image type 'image/gif'.");

            Image::fromFile($path);
        } finally {
            @unlink($path);
        }
    }

    private function createPng(int $width, int $height, int $grayscaleValue): string
    {
        $scanline = chr(0) . str_repeat(chr($grayscaleValue), $width);
        $pixelData = str_repeat($scanline, $height);
        $compressed = gzcompress($pixelData);

        if ($compressed === false) {
            self::fail('Unable to compress PNG test data.');
        }

        return "\x89PNG\x0D\x0A\x1A\x0A"
            . $this->createPngChunk('IHDR', pack('NNC5', $width, $height, 8, 0, 0, 0, 0))
            . $this->createPngChunk('IDAT', $compressed)
            . $this->createPngChunk('IEND', '');
    }

    private function createPngChunk(string $type, string $data): string
    {
        $crc = crc32($type . $data);

        return pack('N', strlen($data))
            . $type
            . $data
            . pack('N', (int) sprintf('%u', $crc));
    }
}
