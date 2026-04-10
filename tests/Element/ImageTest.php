<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Element;

require_once __DIR__ . '/Support/ImageGzcompressStub.php';

use InvalidArgumentException;
use Kalle\Pdf\Binary\BinaryData;

use Kalle\Pdf\Encryption\Object\StandardObjectEncryptor;

use Kalle\Pdf\Encryption\Profile\EncryptionProfile;

use Kalle\Pdf\Encryption\Standard\StandardSecurityHandlerData;
use Kalle\Pdf\Image;
use Kalle\Pdf\Render\StringPdfOutput;
use Kalle\Pdf\Security\EncryptionAlgorithm;

use function Kalle\Pdf\setImageGzcompressFailure;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionException;
use ReflectionMethod;
use Throwable;

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
    public function it_renders_an_image_xobject_stream_from_binary_data(): void
    {
        $image = new Image(320, 200, 'DeviceRGB', 'DCTDecode', BinaryData::fromString('abc123'));

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
    public function it_writes_an_image_xobject_stream_to_a_pdf_output(): void
    {
        $image = new Image(320, 200, 'DeviceRGB', 'DCTDecode', BinaryData::fromString('abc123'));
        $output = new StringPdfOutput();

        $image->write($output);

        self::assertSame($image->render(), $output->contents());
    }

    #[Test]
    public function it_writes_an_encrypted_image_xobject_stream_consistently(): void
    {
        $image = new Image(320, 200, 'DeviceRGB', 'DCTDecode', BinaryData::fromString('abc123'));
        $encryptor = new StandardObjectEncryptor(
            new EncryptionProfile(EncryptionAlgorithm::RC4_128, 128, 2, 3),
            new StandardSecurityHandlerData('', '', '1234567890123456', -4),
        );
        $output = new StringPdfOutput();

        $image->writeEncrypted($output, $encryptor, 9);

        self::assertSame(
            $encryptor->encryptStreamObject("9 0 obj\n" . $image->render() . "endobj\n", 9),
            "9 0 obj\n" . $output->contents() . "endobj\n",
        );
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
            $this->expectException(InvalidArgumentException::class);
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
            $this->expectException(InvalidArgumentException::class);
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
            $this->expectException(InvalidArgumentException::class);
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
    public function it_creates_an_image_from_an_rgb_png_file(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'pdf-image-rgb-');

        if ($path === false) {
            self::fail('Unable to create temporary file.');
        }

        file_put_contents($path, $this->createRgbPng(width: 1, height: 1, red: 255, green: 0, blue: 127));

        try {
            $image = Image::fromFile($path);

            self::assertSame(1, $image->getWidth());
            self::assertSame(1, $image->getHeight());
            self::assertStringContainsString('/ColorSpace /DeviceRGB', $image->render());
            self::assertNull($image->getSoftMask());
        } finally {
            @unlink($path);
        }
    }

    #[Test]
    public function it_creates_an_image_from_a_grayscale_alpha_png_file(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'pdf-image-gray-alpha-');

        if ($path === false) {
            self::fail('Unable to create temporary file.');
        }

        file_put_contents($path, $this->createGrayscaleAlphaPng(width: 1, height: 1, gray: 127, alpha: 200));

        try {
            $image = Image::fromFile($path);
            $softMask = $image->getSoftMask();

            self::assertStringContainsString('/ColorSpace /DeviceGray', $image->render(9));
            self::assertStringContainsString('/SMask 9 0 R', $image->render(9));
            self::assertNotNull($softMask);
            self::assertStringContainsString('/ColorSpace /DeviceGray', $softMask->render());
        } finally {
            @unlink($path);
        }
    }

    #[Test]
    public function it_creates_jpeg_images_with_grayscale_rgb_and_cmyk_color_spaces(): void
    {
        $method = new ReflectionMethod(Image::class, 'fromJpegData');

        $gray = $method->invoke(
            null,
            'gray.jpg',
            'jpeg-data',
            [0 => 10, 1 => 20, 'channels' => 1, 'bits' => 8],
        );
        $rgb = $method->invoke(
            null,
            'rgb.jpg',
            'jpeg-data',
            [0 => 20, 1 => 30, 'channels' => 3, 'bits' => 8],
        );
        $cmyk = $method->invoke(
            null,
            'cmyk.jpg',
            'jpeg-data',
            [0 => 30, 1 => 40, 'channels' => 4, 'bits' => 8],
        );

        self::assertStringContainsString('/ColorSpace /DeviceGray', $gray->render());
        self::assertStringContainsString('/ColorSpace /DeviceRGB', $rgb->render());
        self::assertStringContainsString('/ColorSpace /DeviceCMYK', $cmyk->render());
    }

    #[Test]
    public function it_rejects_unsupported_jpeg_channel_counts(): void
    {
        $method = new ReflectionMethod(Image::class, 'fromJpegData');

        $this->expectException(InvalidArgumentException::class);
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
        $method = new ReflectionMethod(Image::class, 'fromPngData');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid PNG file 'broken.png'.");

        $method->invoke(null, 'broken.png', 'not-a-png');
    }

    #[Test]
    public function it_rejects_png_files_without_an_ihdr_chunk(): void
    {
        $method = new ReflectionMethod(Image::class, 'fromPngData');
        $pngWithoutHeader = "\x89PNG\x0D\x0A\x1A\x0A" . $this->createPngChunk('IEND', '');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid PNG file 'missing-ihdr.png'.");

        $method->invoke(null, 'missing-ihdr.png', $pngWithoutHeader);
    }

    #[Test]
    public function it_rejects_png_variants_with_unsupported_structure(): void
    {
        $method = new ReflectionMethod(Image::class, 'fromPngData');

        $unsupportedCompression = "\x89PNG\x0D\x0A\x1A\x0A"
            . $this->createPngChunk('IHDR', pack('NNC5', 1, 1, 8, 0, 1, 0, 0))
            . $this->createPngChunk('IDAT', gzcompress(chr(0) . chr(127)) ?: '')
            . $this->createPngChunk('IEND', '');

        try {
            $method->invoke(null, 'compression.png', $unsupportedCompression);
            self::fail('Expected exception for unsupported compression settings.');
        } catch (ReflectionException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            self::assertSame("Unsupported PNG compression settings in 'compression.png'.", $exception->getMessage());
        }

        $interlaced = "\x89PNG\x0D\x0A\x1A\x0A"
            . $this->createPngChunk('IHDR', pack('NNC5', 1, 1, 8, 0, 0, 0, 1))
            . $this->createPngChunk('IDAT', gzcompress(chr(0) . chr(127)) ?: '')
            . $this->createPngChunk('IEND', '');

        try {
            $method->invoke(null, 'interlaced.png', $interlaced);
            self::fail('Expected exception for interlaced PNG.');
        } catch (ReflectionException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            self::assertSame("Interlaced PNG images are not supported for 'interlaced.png'.", $exception->getMessage());
        }

        $indexed = "\x89PNG\x0D\x0A\x1A\x0A"
            . $this->createPngChunk('IHDR', pack('NNC5', 1, 1, 8, 3, 0, 0, 0))
            . $this->createPngChunk('IDAT', gzcompress(chr(0) . chr(0)) ?: '')
            . $this->createPngChunk('IEND', '');

        try {
            $method->invoke(null, 'indexed.png', $indexed);
            self::fail('Expected exception for indexed PNG.');
        } catch (ReflectionException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            self::assertSame("Indexed PNG images are not supported for 'indexed.png'.", $exception->getMessage());
        }

        $unsupportedColor = "\x89PNG\x0D\x0A\x1A\x0A"
            . $this->createPngChunk('IHDR', pack('NNC5', 1, 1, 8, 1, 0, 0, 0))
            . $this->createPngChunk('IDAT', gzcompress(chr(0) . chr(0)) ?: '')
            . $this->createPngChunk('IEND', '');

        try {
            $method->invoke(null, 'color.png', $unsupportedColor);
            self::fail('Expected exception for unsupported PNG color type.');
        } catch (ReflectionException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            self::assertSame("Unsupported PNG color type '1' in 'color.png'.", $exception->getMessage());
        }
    }

    #[Test]
    public function it_rejects_pngs_without_image_data_and_alpha_images_with_unsupported_bit_depth(): void
    {
        $method = new ReflectionMethod(Image::class, 'fromPngData');

        $withoutData = "\x89PNG\x0D\x0A\x1A\x0A"
            . $this->createPngChunk('IHDR', pack('NNC5', 1, 1, 8, 0, 0, 0, 0))
            . $this->createPngChunk('IEND', '');

        try {
            $method->invoke(null, 'empty.png', $withoutData);
            self::fail('Expected exception for missing PNG image data.');
        } catch (ReflectionException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            self::assertSame("PNG file 'empty.png' does not contain image data.", $exception->getMessage());
        }

        $alpha16Bit = "\x89PNG\x0D\x0A\x1A\x0A"
            . $this->createPngChunk('IHDR', pack('NNC5', 1, 1, 16, 6, 0, 0, 0))
            . $this->createPngChunk('IDAT', gzcompress(chr(0) . str_repeat(chr(0), 8)) ?: '')
            . $this->createPngChunk('IEND', '');

        try {
            $method->invoke(null, 'alpha16.png', $alpha16Bit);
            self::fail('Expected exception for unsupported alpha bit depth.');
        } catch (ReflectionException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            self::assertSame(
                "PNG images with alpha channels currently require 8 bits per component for 'alpha16.png'.",
                $exception->getMessage(),
            );
        }
    }

    #[Test]
    public function it_rejects_invalid_alpha_channel_payload_lengths(): void
    {
        $splitPngAlphaChannels = new ReflectionMethod(Image::class, 'splitPngAlphaChannels');

        $compressed = gzcompress(chr(0) . str_repeat(chr(0), 3));
        self::assertNotFalse($compressed);

        try {
            $splitPngAlphaChannels->invoke(null, 'alpha-length.png', $compressed, 1, 1, 3);
            self::fail('Expected exception for unexpected alpha image length.');
        } catch (ReflectionException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            self::assertSame("Unexpected PNG alpha image data length for 'alpha-length.png'.", $exception->getMessage());
        }
    }

    #[Test]
    public function it_rejects_invalid_png_chunk_headers_and_invalid_alpha_payload_compression(): void
    {
        $readUint32 = new ReflectionMethod(Image::class, 'readUint32');
        $splitPngAlphaChannels = new ReflectionMethod(Image::class, 'splitPngAlphaChannels');

        try {
            $readUint32->invoke(null, 'abc', 0);
            self::fail('Expected exception for invalid PNG chunk header.');
        } catch (ReflectionException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            self::assertSame('Unable to read PNG chunk data.', $exception->getMessage());
        }

        try {
            $splitPngAlphaChannels->invoke(null, 'alpha.png', 'not-compressed', 1, 1, 3);
            self::fail('Expected exception for invalid compressed alpha data.');
        } catch (ReflectionException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            self::assertSame("Unable to decompress PNG image data for 'alpha.png'.", $exception->getMessage());
        }
    }

    #[Test]
    public function it_rejects_png_alpha_recompression_failures(): void
    {
        $splitPngAlphaChannels = new ReflectionMethod(Image::class, 'splitPngAlphaChannels');
        $pixelData = chr(0) . chr(10) . chr(20) . chr(30) . chr(40);
        $compressed = gzcompress($pixelData);

        self::assertNotFalse($compressed);

        setImageGzcompressFailure(true);

        try {
            $splitPngAlphaChannels->invoke(null, 'alpha-recompress.png', $compressed, 1, 1, 3);
            self::fail('Expected exception for failed PNG alpha recompression.');
        } catch (ReflectionException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            self::assertSame('Failed to recompress PNG image data.', $exception->getMessage());
        } finally {
            setImageGzcompressFailure(false);
        }
    }

    #[Test]
    public function it_unfilters_png_scanlines_for_all_supported_filter_types_and_rejects_unknown_ones(): void
    {
        $method = new ReflectionMethod(Image::class, 'unfilterPngScanline');

        self::assertSame([10, 20], $method->invoke(null, [10, 20], [0, 0], 0, 1, 'row.png'));
        self::assertSame([10, 30], $method->invoke(null, [10, 20], [0, 0], 1, 1, 'row.png'));
        self::assertSame([15, 26], $method->invoke(null, [10, 20], [5, 6], 2, 1, 'row.png'));
        self::assertSame([12, 29], $method->invoke(null, [10, 20], [5, 6], 3, 1, 'row.png'));
        self::assertSame([15, 35], $method->invoke(null, [10, 20], [5, 6], 4, 1, 'row.png'));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Unsupported PNG filter type '9' in 'row.png'.");

        $method->invoke(null, [10], [0], 9, 1, 'row.png');
    }

    #[Test]
    public function it_uses_each_paeth_predictor_branch(): void
    {
        $method = new ReflectionMethod(Image::class, 'paethPredictor');

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

    private function createRgbPng(int $width, int $height, int $red, int $green, int $blue): string
    {
        $scanline = chr(0) . str_repeat(chr($red) . chr($green) . chr($blue), $width);
        $pixelData = str_repeat($scanline, $height);
        $compressed = gzcompress($pixelData);

        if ($compressed === false) {
            self::fail('Unable to compress RGB PNG test data.');
        }

        return "\x89PNG\x0D\x0A\x1A\x0A"
            . $this->createPngChunk('IHDR', pack('NNC5', $width, $height, 8, 2, 0, 0, 0))
            . $this->createPngChunk('IDAT', $compressed)
            . $this->createPngChunk('IEND', '');
    }

    private function createGrayscaleAlphaPng(int $width, int $height, int $gray, int $alpha): string
    {
        $scanline = chr(0) . str_repeat(chr($gray) . chr($alpha), $width);
        $pixelData = str_repeat($scanline, $height);
        $compressed = gzcompress($pixelData);

        if ($compressed === false) {
            self::fail('Unable to compress grayscale alpha PNG test data.');
        }

        return "\x89PNG\x0D\x0A\x1A\x0A"
            . $this->createPngChunk('IHDR', pack('NNC5', $width, $height, 8, 4, 0, 0, 0))
            . $this->createPngChunk('IDAT', $compressed)
            . $this->createPngChunk('IEND', '');
    }
}
