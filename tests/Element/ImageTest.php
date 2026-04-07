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

    #[Test]
    public function it_rejects_unreadable_image_files(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'pdf-image-');

        if ($path === false) {
            self::fail('Unable to create temporary file.');
        }

        file_put_contents($path, 'image-data');
        chmod($path, 0000);

        try {
            $this->expectException(\InvalidArgumentException::class);
            $this->expectExceptionMessage("Unable to read image file '$path'.");

            Image::fromFile($path);
        } finally {
            chmod($path, 0600);
            @unlink($path);
        }
    }

    #[Test]
    public function it_rejects_invalid_image_files_when_metadata_cannot_be_detected(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'pdf-image-');

        if ($path === false) {
            self::fail('Unable to create temporary file.');
        }

        file_put_contents($path, 'not-an-image');

        try {
            $this->expectException(\InvalidArgumentException::class);
            $this->expectExceptionMessage("Unsupported or invalid image file '$path'.");

            Image::fromFile($path);
        } finally {
            @unlink($path);
        }
    }

    #[Test]
    public function it_creates_an_image_from_an_rgba_png_file_with_a_soft_mask(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'pdf-image-alpha-');

        if ($path === false) {
            self::fail('Unable to create temporary file.');
        }

        file_put_contents($path, $this->createRgbaPng(width: 1, height: 1, red: 255, green: 0, blue: 0, alpha: 127));

        try {
            $image = Image::fromFile($path);
            $softMask = $image->getSoftMask();

            self::assertSame(1, $image->getWidth());
            self::assertSame(1, $image->getHeight());
            self::assertNotNull($softMask);
            self::assertStringContainsString('/ColorSpace /DeviceRGB', $image->render(9));
            self::assertStringContainsString('/SMask 9 0 R', $image->render(9));
            self::assertStringContainsString('/ColorSpace /DeviceGray', $softMask->render());
        } finally {
            @unlink($path);
        }
    }

    #[Test]
    public function it_creates_jpeg_images_with_grayscale_and_cmyk_color_spaces(): void
    {
        $method = new \ReflectionMethod(Image::class, 'fromJpegData');

        $gray = $method->invoke(
            null,
            'gray.jpg',
            'jpeg-data',
            [0 => 10, 1 => 20, 'channels' => 1, 'bits' => 8],
        );
        $cmyk = $method->invoke(
            null,
            'cmyk.jpg',
            'jpeg-data',
            [0 => 30, 1 => 40, 'channels' => 4, 'bits' => 8],
        );

        self::assertStringContainsString('/ColorSpace /DeviceGray', $gray->render());
        self::assertStringContainsString('/ColorSpace /DeviceCMYK', $cmyk->render());
    }

    #[Test]
    public function it_rejects_unsupported_jpeg_channel_counts(): void
    {
        $method = new \ReflectionMethod(Image::class, 'fromJpegData');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Unsupported JPEG channel count '2' in 'broken.jpg'.");

        $method->invoke(
            null,
            'broken.jpg',
            'jpeg-data',
            [0 => 10, 1 => 20, 'channels' => 2, 'bits' => 8],
        );
    }

    #[Test]
    public function it_rejects_invalid_png_signatures_and_missing_headers(): void
    {
        $method = new \ReflectionMethod(Image::class, 'fromPngData');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid PNG file 'broken.png'.");

        $method->invoke(null, 'broken.png', 'not-a-png');
    }

    #[Test]
    public function it_rejects_png_variants_with_unsupported_structure(): void
    {
        $method = new \ReflectionMethod(Image::class, 'fromPngData');

        $unsupportedCompression = "\x89PNG\x0D\x0A\x1A\x0A"
            . $this->createPngChunk('IHDR', pack('NNC5', 1, 1, 8, 0, 1, 0, 0))
            . $this->createPngChunk('IDAT', gzcompress(chr(0) . chr(127)) ?: '')
            . $this->createPngChunk('IEND', '');

        try {
            $method->invoke(null, 'compression.png', $unsupportedCompression);
            self::fail('Expected exception for unsupported compression settings.');
        } catch (\ReflectionException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            self::assertSame("Unsupported PNG compression settings in 'compression.png'.", $exception->getMessage());
        }

        $interlaced = "\x89PNG\x0D\x0A\x1A\x0A"
            . $this->createPngChunk('IHDR', pack('NNC5', 1, 1, 8, 0, 0, 0, 1))
            . $this->createPngChunk('IDAT', gzcompress(chr(0) . chr(127)) ?: '')
            . $this->createPngChunk('IEND', '');

        try {
            $method->invoke(null, 'interlaced.png', $interlaced);
            self::fail('Expected exception for interlaced PNG.');
        } catch (\ReflectionException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            self::assertSame("Interlaced PNG images are not supported for 'interlaced.png'.", $exception->getMessage());
        }

        $indexed = "\x89PNG\x0D\x0A\x1A\x0A"
            . $this->createPngChunk('IHDR', pack('NNC5', 1, 1, 8, 3, 0, 0, 0))
            . $this->createPngChunk('IDAT', gzcompress(chr(0) . chr(0)) ?: '')
            . $this->createPngChunk('IEND', '');

        try {
            $method->invoke(null, 'indexed.png', $indexed);
            self::fail('Expected exception for indexed PNG.');
        } catch (\ReflectionException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            self::assertSame("Indexed PNG images are not supported for 'indexed.png'.", $exception->getMessage());
        }

        $unsupportedColor = "\x89PNG\x0D\x0A\x1A\x0A"
            . $this->createPngChunk('IHDR', pack('NNC5', 1, 1, 8, 1, 0, 0, 0))
            . $this->createPngChunk('IDAT', gzcompress(chr(0) . chr(0)) ?: '')
            . $this->createPngChunk('IEND', '');

        try {
            $method->invoke(null, 'color.png', $unsupportedColor);
            self::fail('Expected exception for unsupported PNG color type.');
        } catch (\ReflectionException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            self::assertSame("Unsupported PNG color type '1' in 'color.png'.", $exception->getMessage());
        }
    }

    #[Test]
    public function it_rejects_pngs_without_image_data_and_alpha_images_with_unsupported_bit_depth(): void
    {
        $method = new \ReflectionMethod(Image::class, 'fromPngData');

        $withoutData = "\x89PNG\x0D\x0A\x1A\x0A"
            . $this->createPngChunk('IHDR', pack('NNC5', 1, 1, 8, 0, 0, 0, 0))
            . $this->createPngChunk('IEND', '');

        try {
            $method->invoke(null, 'empty.png', $withoutData);
            self::fail('Expected exception for missing PNG image data.');
        } catch (\ReflectionException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            self::assertSame("PNG file 'empty.png' does not contain image data.", $exception->getMessage());
        }

        $alpha16Bit = "\x89PNG\x0D\x0A\x1A\x0A"
            . $this->createPngChunk('IHDR', pack('NNC5', 1, 1, 16, 6, 0, 0, 0))
            . $this->createPngChunk('IDAT', gzcompress(chr(0) . str_repeat(chr(0), 8)) ?: '')
            . $this->createPngChunk('IEND', '');

        try {
            $method->invoke(null, 'alpha16.png', $alpha16Bit);
            self::fail('Expected exception for unsupported alpha bit depth.');
        } catch (\ReflectionException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            self::assertSame(
                "PNG images with alpha channels currently require 8 bits per component for 'alpha16.png'.",
                $exception->getMessage(),
            );
        }
    }

    #[Test]
    public function it_rejects_invalid_alpha_channel_payload_lengths(): void
    {
        $splitPngAlphaChannels = new \ReflectionMethod(Image::class, 'splitPngAlphaChannels');

        $compressed = gzcompress(chr(0) . str_repeat(chr(0), 3));
        self::assertNotFalse($compressed);

        try {
            $splitPngAlphaChannels->invoke(null, 'alpha-length.png', $compressed, 1, 1, 3);
            self::fail('Expected exception for unexpected alpha image length.');
        } catch (\ReflectionException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            self::assertSame("Unexpected PNG alpha image data length for 'alpha-length.png'.", $exception->getMessage());
        }
    }

    #[Test]
    public function it_unfilters_png_scanlines_for_all_supported_filter_types_and_rejects_unknown_ones(): void
    {
        $method = new \ReflectionMethod(Image::class, 'unfilterPngScanline');

        self::assertSame([10, 20], $method->invoke(null, [10, 20], [0, 0], 0, 1, 'row.png'));
        self::assertSame([10, 30], $method->invoke(null, [10, 20], [0, 0], 1, 1, 'row.png'));
        self::assertSame([15, 26], $method->invoke(null, [10, 20], [5, 6], 2, 1, 'row.png'));
        self::assertSame([12, 29], $method->invoke(null, [10, 20], [5, 6], 3, 1, 'row.png'));
        self::assertSame([15, 35], $method->invoke(null, [10, 20], [5, 6], 4, 1, 'row.png'));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Unsupported PNG filter type '9' in 'row.png'.");

        $method->invoke(null, [10], [0], 9, 1, 'row.png');
    }

    #[Test]
    public function it_uses_each_paeth_predictor_branch(): void
    {
        $method = new \ReflectionMethod(Image::class, 'paethPredictor');

        self::assertSame(10, $method->invoke(null, 10, 20, 20));
        self::assertSame(20, $method->invoke(null, 10, 20, 10));
        self::assertSame(1, $method->invoke(null, 0, 2, 1));
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

    private function createRgbaPng(int $width, int $height, int $red, int $green, int $blue, int $alpha): string
    {
        $scanline = chr(0) . str_repeat(chr($red) . chr($green) . chr($blue) . chr($alpha), $width);
        $pixelData = str_repeat($scanline, $height);
        $compressed = gzcompress($pixelData);

        if ($compressed === false) {
            self::fail('Unable to compress PNG alpha test data.');
        }

        return "\x89PNG\x0D\x0A\x1A\x0A"
            . $this->createPngChunk('IHDR', pack('NNC5', $width, $height, 8, 6, 0, 0, 0))
            . $this->createPngChunk('IDAT', $compressed)
            . $this->createPngChunk('IEND', '');
    }
}
