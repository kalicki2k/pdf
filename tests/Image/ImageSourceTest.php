<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Image;

use function file_put_contents;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

use InvalidArgumentException;
use Kalle\Pdf\Image\ImageColorSpace;
use Kalle\Pdf\Image\ImageSource;
use PHPUnit\Framework\TestCase;

final class ImageSourceTest extends TestCase
{
    public function testItBuildsAJpegImageSource(): void
    {
        $source = ImageSource::jpeg('jpeg-bytes', 320, 180);

        self::assertSame('/DCTDecode', $source->filter);
        self::assertSame(ImageColorSpace::RGB, $source->colorSpace);
        self::assertSame(8, $source->bitsPerComponent);
    }

    public function testItExposesImageDictionaryAndStreamContentsSeparately(): void
    {
        $source = new ImageSource(
            width: 2,
            height: 3,
            colorSpace: ImageColorSpace::RGB,
            bitsPerComponent: 8,
            data: 'rgb-data',
            filter: '/FlateDecode',
            additionalDictionaryEntries: ['/Intent /Perceptual'],
        );

        self::assertSame(
            '<< /Type /XObject /Subtype /Image /Width 2 /Height 3 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Intent /Perceptual /Length 8 >>',
            $source->pdfObjectDictionaryContents(),
        );
        self::assertSame('rgb-data', $source->pdfObjectStreamContents());
        self::assertSame(
            '<<' . ' /Type /XObject /Subtype /Image /Width 2 /Height 3 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Intent /Perceptual /Length 8 >>' . "\nstream\nrgb-data\nendstream",
            $source->pdfObjectContents(),
        );
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

    public function testItCreatesAnRgbJpegImageSourceFromAFilePath(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'pdf2-image-source-');

        if ($path === false) {
            self::fail('Unable to create a temporary image source path.');
        }

        file_put_contents($path, JpegFixture::tinyRgbJpegBytes());

        $source = ImageSource::fromPath($path);

        self::assertSame(1, $source->width);
        self::assertSame(1, $source->height);
        self::assertSame(ImageColorSpace::RGB, $source->colorSpace);
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

        file_put_contents($path, base64_decode('R0lGODdhAQABAIAAAP///////ywAAAAAAQABAAACAkQBADs=', true));

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
        $softMask = $source->softMask;

        self::assertSame(1, $source->width);
        self::assertSame(1, $source->height);
        self::assertSame(ImageColorSpace::RGB, $source->colorSpace);
        self::assertSame('/FlateDecode', $source->filter);
        self::assertNotNull($softMask);
        self::assertSame(ImageColorSpace::GRAY, $softMask->colorSpace);
        self::assertSame('/FlateDecode', $softMask->filter);

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
        $softMask = $source->softMask;

        self::assertSame(1, $source->width);
        self::assertSame(1, $source->height);
        self::assertStringContainsString('[/Indexed /DeviceRGB 0 <000000>]', $source->pdfObjectContents());
        self::assertNotNull($softMask);
        self::assertSame(ImageColorSpace::GRAY, $softMask->colorSpace);
        self::assertSame('/FlateDecode', $softMask->filter);

        unlink($path);
    }
}
