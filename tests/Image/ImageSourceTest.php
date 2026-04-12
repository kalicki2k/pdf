<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Image;

use InvalidArgumentException;
use Kalle\Pdf\Image\ImageColorSpace;
use Kalle\Pdf\Image\ImageSource;
use PHPUnit\Framework\TestCase;

use function file_put_contents;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

final class ImageSourceTest extends TestCase
{
    public function testItBuildsAJpegImageSource(): void
    {
        $source = ImageSource::jpeg('jpeg-bytes', 320, 180);

        self::assertSame('/DCTDecode', $source->filter);
        self::assertSame(ImageColorSpace::RGB, $source->colorSpace);
        self::assertSame(8, $source->bitsPerComponent);
    }

    public function testItCreatesAJpegImageSourceFromAFilePath(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'pdf2-image-source-');

        if ($path === false) {
            self::fail('Unable to create a temporary image source path.');
        }

        file_put_contents($path, JpegFixture::tinyGrayJpegBytes());

        $source = ImageSource::fromPath($path);

        self::assertSame(1, $source->width);
        self::assertSame(1, $source->height);
        self::assertSame(ImageColorSpace::GRAY, $source->colorSpace);
        self::assertSame('/DCTDecode', $source->filter);

        unlink($path);
    }

    public function testItCreatesACmykJpegImageSourceFromAFilePath(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'pdf2-image-source-');

        if ($path === false) {
            self::fail('Unable to create a temporary image source path.');
        }

        file_put_contents($path, JpegFixture::tinyCmykJpegBytes());

        $source = ImageSource::fromPath($path);

        self::assertSame(1, $source->width);
        self::assertSame(1, $source->height);
        self::assertSame(ImageColorSpace::CMYK, $source->colorSpace);
        self::assertSame('/DCTDecode', $source->filter);

        unlink($path);
    }

    public function testItRejectsNonGraySoftMasks(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Soft mask images must use the gray color space.');

        new ImageSource(
            width: 10,
            height: 10,
            colorSpace: ImageColorSpace::RGB,
            bitsPerComponent: 8,
            data: 'rgb',
            softMask: ImageSource::flate('mask', 10, 10, ImageColorSpace::RGB),
        );
    }

    public function testItRejectsMissingPaths(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Image path '/definitely/missing/image.jpg' does not point to a readable file.");

        ImageSource::fromPath('/definitely/missing/image.jpg');
    }

    public function testItRejectsUnsupportedFileFormats(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'pdf2-image-source-');

        if ($path === false) {
            self::fail('Unable to create a temporary image source path.');
        }

        file_put_contents($path, 'not-a-jpeg');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf(
            "Image path '%s' uses an unsupported image format.",
            $path,
        ));

        try {
            ImageSource::fromPath($path);
        } finally {
            unlink($path);
        }
    }

    public function testItCreatesARgbPngImageSourceFromAFilePath(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'pdf2-image-source-');

        if ($path === false) {
            self::fail('Unable to create a temporary image source path.');
        }

        file_put_contents($path, PngFixture::tinyRgbPngBytes());

        $source = ImageSource::fromPath($path);

        self::assertSame(1, $source->width);
        self::assertSame(1, $source->height);
        self::assertSame(ImageColorSpace::RGB, $source->colorSpace);
        self::assertSame('/FlateDecode', $source->filter);
        self::assertNull($source->softMask);

        unlink($path);
    }

    public function testItCreatesAnRgbaPngImageSourceWithSoftMaskFromAFilePath(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'pdf2-image-source-');

        if ($path === false) {
            self::fail('Unable to create a temporary image source path.');
        }

        file_put_contents($path, PngFixture::tinyRgbaPngBytes());

        $source = ImageSource::fromPath($path);

        self::assertSame(1, $source->width);
        self::assertSame(1, $source->height);
        self::assertSame(ImageColorSpace::RGB, $source->colorSpace);
        self::assertSame('/FlateDecode', $source->filter);
        self::assertNotNull($source->softMask);
        self::assertSame(ImageColorSpace::GRAY, $source->softMask?->colorSpace);
        self::assertSame('/FlateDecode', $source->softMask?->filter);

        unlink($path);
    }

    public function testItCreatesAnIndexedPngImageSourceFromAFilePath(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'pdf2-image-source-');

        if ($path === false) {
            self::fail('Unable to create a temporary image source path.');
        }

        file_put_contents($path, PngFixture::tinyIndexedPngBytes());

        $source = ImageSource::fromPath($path);

        self::assertSame(1, $source->width);
        self::assertSame(1, $source->height);
        self::assertSame(ImageColorSpace::RGB, $source->colorSpace);
        self::assertSame('/FlateDecode', $source->filter);
        self::assertStringContainsString('[/Indexed /DeviceRGB 0 <808080>]', $source->pdfObjectContents());

        unlink($path);
    }

    public function testItCreatesAnIndexedTransparentPngImageSourceWithSoftMaskFromAFilePath(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'pdf2-image-source-');

        if ($path === false) {
            self::fail('Unable to create a temporary image source path.');
        }

        file_put_contents($path, PngFixture::tinyIndexedTransparentPngBytes());

        $source = ImageSource::fromPath($path);

        self::assertSame(1, $source->width);
        self::assertSame(1, $source->height);
        self::assertStringContainsString('[/Indexed /DeviceRGB 0 <000000>]', $source->pdfObjectContents());
        self::assertNotNull($source->softMask);
        self::assertSame(ImageColorSpace::GRAY, $source->softMask?->colorSpace);
        self::assertSame('/FlateDecode', $source->softMask?->filter);

        unlink($path);
    }
}
