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

    public function testItBuildsARunLengthEncodedImageSource(): void
    {
        $source = ImageSource::runLengthCompressed(str_repeat('A', 32), 8, 4, ImageColorSpace::GRAY, 8);

        self::assertSame('/RunLengthDecode', $source->filter);
        self::assertStringContainsString('/Filter /RunLengthDecode', $source->pdfObjectDictionaryContents());
    }

    public function testItBuildsAnLzwEncodedImageSource(): void
    {
        $source = ImageSource::lzwCompressed(str_repeat('ABCD', 16), 8, 8, ImageColorSpace::RGB, 8);

        self::assertSame('/LZWDecode', $source->filter);
        self::assertStringContainsString('/Filter /LZWDecode', $source->pdfObjectDictionaryContents());
        self::assertStringContainsString('/DecodeParms << /EarlyChange 1 >>', $source->pdfObjectDictionaryContents());
    }

    public function testItBuildsACcittFaxImageSource(): void
    {
        $source = ImageSource::ccittFax('fax-bytes', 1728, 200, k: 0, blackIs1: true);

        self::assertSame('/CCITTFaxDecode', $source->filter);
        self::assertSame(ImageColorSpace::GRAY, $source->colorSpace);
        self::assertSame(1, $source->bitsPerComponent);
        self::assertStringContainsString('/Filter /CCITTFaxDecode', $source->pdfObjectDictionaryContents());
        self::assertStringContainsString('/DecodeParms << /K 0 /Columns 1728 /Rows 200 /BlackIs1 true >>', $source->pdfObjectDictionaryContents());
    }

    public function testItChoosesRunLengthForHighlyRepetitiveRawImageData(): void
    {
        $source = ImageSource::compressed(str_repeat("\x00", 512), 64, 8, ImageColorSpace::GRAY, 1);

        self::assertSame('/RunLengthDecode', $source->filter);
    }

    public function testItBuildsACompressedMonochromeImageSourceFromBitRows(): void
    {
        $source = ImageSource::monochrome([
            '11111111',
            '11111111',
        ]);

        self::assertSame(ImageColorSpace::GRAY, $source->colorSpace);
        self::assertSame(1, $source->bitsPerComponent);
        self::assertSame(8, $source->width);
        self::assertSame(2, $source->height);
        self::assertContains($source->filter, ['/FlateDecode', '/LZWDecode', '/RunLengthDecode']);
    }

    public function testItBuildsACcittMonochromeImageSourceFromBitRows(): void
    {
        $source = ImageSource::monochromeCcitt([
            '11111111',
            '00000000',
        ]);

        self::assertSame('/CCITTFaxDecode', $source->filter);
        self::assertSame(1, $source->bitsPerComponent);
        self::assertStringContainsString('/DecodeParms << /K 0 /Columns 8 /Rows 2 /BlackIs1 true /EndOfLine true >>', $source->pdfObjectDictionaryContents());
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

    public function testItCreatesACcittFaxImageSourceFromATiffPath(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'pdf2-image-source-');

        if ($path === false) {
            self::fail('Unable to create a temporary image source path.');
        }

        file_put_contents($path, TiffFixture::tinyCcittGroup4TiffBytes());

        $source = ImageSource::fromPath($path);

        self::assertSame(1, $source->width);
        self::assertSame(1, $source->height);
        self::assertSame(ImageColorSpace::GRAY, $source->colorSpace);
        self::assertSame('/CCITTFaxDecode', $source->filter);
        self::assertStringContainsString('/DecodeParms << /K -1 /Columns 1 /Rows 1 /BlackIs1 true >>', $source->pdfObjectDictionaryContents());

        unlink($path);
    }

    public function testItCreatesACompressedMonochromeImageSourceFromAnUncompressedTiffPath(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'pdf2-image-source-');

        if ($path === false) {
            self::fail('Unable to create a temporary image source path.');
        }

        file_put_contents($path, TiffFixture::tinyUncompressedBilevelTiffBytes());

        $source = ImageSource::fromPath($path);

        self::assertSame(8, $source->width);
        self::assertSame(2, $source->height);
        self::assertSame(ImageColorSpace::GRAY, $source->colorSpace);
        self::assertSame(1, $source->bitsPerComponent);
        self::assertContains($source->filter, ['/FlateDecode', '/LZWDecode', '/RunLengthDecode', '/CCITTFaxDecode']);

        unlink($path);
    }

    public function testItCreatesACompressedMonochromeImageSourceFromAMultiStripUncompressedTiffPath(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'pdf2-image-source-');

        if ($path === false) {
            self::fail('Unable to create a temporary image source path.');
        }

        file_put_contents($path, TiffFixture::tinyMultiStripUncompressedBilevelTiffBytes());

        $source = ImageSource::fromPath($path);

        self::assertSame(8, $source->width);
        self::assertSame(2, $source->height);
        self::assertSame(ImageColorSpace::GRAY, $source->colorSpace);
        self::assertSame(1, $source->bitsPerComponent);

        unlink($path);
    }

    public function testItCreatesAGrayscaleImageSourceFromAn8BitTiffPath(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'pdf2-image-source-');

        if ($path === false) {
            self::fail('Unable to create a temporary image source path.');
        }

        file_put_contents($path, TiffFixture::tinyUncompressedGrayscaleTiffBytes());

        $source = ImageSource::fromPath($path);

        self::assertSame(2, $source->width);
        self::assertSame(1, $source->height);
        self::assertSame(ImageColorSpace::GRAY, $source->colorSpace);
        self::assertSame(8, $source->bitsPerComponent);
        self::assertContains($source->filter, ['/FlateDecode', '/LZWDecode', '/RunLengthDecode']);

        unlink($path);
    }

    public function testItCreatesAGrayscaleImageSourceFromAPackBitsTiffPath(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'pdf2-image-source-');

        if ($path === false) {
            self::fail('Unable to create a temporary image source path.');
        }

        file_put_contents($path, TiffFixture::tinyPackBitsGrayscaleTiffBytes());

        $source = ImageSource::fromPath($path);

        self::assertSame(2, $source->width);
        self::assertSame(1, $source->height);
        self::assertSame(ImageColorSpace::GRAY, $source->colorSpace);
        self::assertSame(8, $source->bitsPerComponent);
        self::assertContains($source->filter, ['/FlateDecode', '/LZWDecode', '/RunLengthDecode']);

        unlink($path);
    }

    public function testItCreatesAGrayscaleImageSourceFromAPredictorLzwTiffPath(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'pdf2-image-source-');

        if ($path === false) {
            self::fail('Unable to create a temporary image source path.');
        }

        file_put_contents($path, TiffFixture::tinyPredictorLzwGrayscaleTiffBytes());

        $source = ImageSource::fromPath($path);

        self::assertSame(2, $source->width);
        self::assertSame(1, $source->height);
        self::assertSame(ImageColorSpace::GRAY, $source->colorSpace);
        self::assertSame(8, $source->bitsPerComponent);

        unlink($path);
    }

    public function testItCreatesAnRgbImageSourceFromAn8BitRgbTiffPath(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'pdf2-image-source-');

        if ($path === false) {
            self::fail('Unable to create a temporary image source path.');
        }

        file_put_contents($path, TiffFixture::tinyUncompressedRgbTiffBytes());

        $source = ImageSource::fromPath($path);

        self::assertSame(1, $source->width);
        self::assertSame(1, $source->height);
        self::assertSame(ImageColorSpace::RGB, $source->colorSpace);
        self::assertSame(8, $source->bitsPerComponent);
        self::assertContains($source->filter, ['/FlateDecode', '/LZWDecode', '/RunLengthDecode']);

        unlink($path);
    }

    public function testItCreatesAnIndexedImageSourceFromAPaletteTiffPath(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'pdf2-image-source-');

        if ($path === false) {
            self::fail('Unable to create a temporary image source path.');
        }

        file_put_contents($path, TiffFixture::tinyUncompressedPaletteTiffBytes());

        $source = ImageSource::fromPath($path);

        self::assertSame(2, $source->width);
        self::assertSame(1, $source->height);
        self::assertSame(ImageColorSpace::RGB, $source->colorSpace);
        self::assertSame('/FlateDecode', $source->filter);
        self::assertStringContainsString('[/Indexed /DeviceRGB 1 <000000FF00FF>]', $source->pdfObjectContents());

        unlink($path);
    }

    public function testItCreatesAnRgbImageSourceFromAnLzwTiffPath(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'pdf2-image-source-');

        if ($path === false) {
            self::fail('Unable to create a temporary image source path.');
        }

        file_put_contents($path, TiffFixture::tinyLzwRgbTiffBytes());

        $source = ImageSource::fromPath($path);

        self::assertSame(1, $source->width);
        self::assertSame(1, $source->height);
        self::assertSame(ImageColorSpace::RGB, $source->colorSpace);
        self::assertSame(8, $source->bitsPerComponent);
        self::assertContains($source->filter, ['/FlateDecode', '/LZWDecode', '/RunLengthDecode']);

        unlink($path);
    }

    public function testItCreatesAnRgbImageSourceFromAPredictorDeflateTiffPath(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'pdf2-image-source-');

        if ($path === false) {
            self::fail('Unable to create a temporary image source path.');
        }

        file_put_contents($path, TiffFixture::tinyPredictorDeflateRgbTiffBytes());

        $source = ImageSource::fromPath($path);

        self::assertSame(2, $source->width);
        self::assertSame(1, $source->height);
        self::assertSame(ImageColorSpace::RGB, $source->colorSpace);
        self::assertSame(8, $source->bitsPerComponent);

        unlink($path);
    }

    public function testItCreatesACcittFaxImageSourceFromAMultiStripGroup3TiffPath(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'pdf2-image-source-');

        if ($path === false) {
            self::fail('Unable to create a temporary image source path.');
        }

        file_put_contents($path, TiffFixture::tinyMultiStripCcittGroup3TiffBytes());

        $source = ImageSource::fromPath($path);

        self::assertSame(8, $source->width);
        self::assertSame(2, $source->height);
        self::assertSame('/CCITTFaxDecode', $source->filter);
        self::assertStringContainsString('/DecodeParms << /K 0 /Columns 8 /Rows 2 /BlackIs1 true /EndOfLine true /EndOfBlock false >>', $source->pdfObjectDictionaryContents());

        unlink($path);
    }

    public function testItCreatesAnIndexedGifImageSourceFromAFilePath(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'pdf2-image-source-');

        if ($path === false) {
            self::fail('Unable to create a temporary image source path.');
        }

        file_put_contents($path, GifFixture::tinyOpaqueGifBytes());

        $source = ImageSource::fromPath($path);

        self::assertSame(1, $source->width);
        self::assertSame(1, $source->height);
        self::assertSame(ImageColorSpace::RGB, $source->colorSpace);
        self::assertSame('/FlateDecode', $source->filter);
        self::assertStringContainsString('[/Indexed /DeviceRGB 1 <FFFFFFFFFFFF>]', $source->pdfObjectContents());
        self::assertNull($source->softMask);

        unlink($path);
    }

    public function testItCreatesATransparentGifImageSourceWithSoftMaskFromAFilePath(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'pdf2-image-source-');

        if ($path === false) {
            self::fail('Unable to create a temporary image source path.');
        }

        file_put_contents($path, GifFixture::tinyTransparentGifBytes());

        $source = ImageSource::fromPath($path);

        self::assertSame(1, $source->width);
        self::assertSame(1, $source->height);
        self::assertStringContainsString('[/Indexed /DeviceRGB 1 <000000000000>]', $source->pdfObjectContents());
        self::assertNotNull($source->softMask);
        self::assertSame(ImageColorSpace::GRAY, $source->softMask->colorSpace);

        unlink($path);
    }

    public function testItCreatesA24BitBmpImageSourceFromAFilePath(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'pdf2-image-source-');

        if ($path === false) {
            self::fail('Unable to create a temporary image source path.');
        }

        file_put_contents($path, BmpFixture::tiny24BitRgbBmpBytes());

        $source = ImageSource::fromPath($path);

        self::assertSame(1, $source->width);
        self::assertSame(1, $source->height);
        self::assertSame(ImageColorSpace::RGB, $source->colorSpace);
        self::assertSame(8, $source->bitsPerComponent);
        self::assertSame('/FlateDecode', $source->filter);
        self::assertNull($source->softMask);

        unlink($path);
    }

    public function testItCreatesA32BitBmpImageSourceWithSoftMaskFromAFilePath(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'pdf2-image-source-');

        if ($path === false) {
            self::fail('Unable to create a temporary image source path.');
        }

        file_put_contents($path, BmpFixture::tiny32BitRgbaBmpBytes());

        $source = ImageSource::fromPath($path);

        self::assertSame(1, $source->width);
        self::assertSame(1, $source->height);
        self::assertSame(ImageColorSpace::RGB, $source->colorSpace);
        self::assertNotNull($source->softMask);
        self::assertSame(ImageColorSpace::GRAY, $source->softMask->colorSpace);

        unlink($path);
    }

    public function testItCreatesA32BitBitfieldsBmpImageSourceFromAFilePath(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'pdf2-image-source-');

        if ($path === false) {
            self::fail('Unable to create a temporary image source path.');
        }

        file_put_contents($path, BmpFixture::tiny32BitBitfieldsRgbaBmpBytes());

        $source = ImageSource::fromPath($path);

        self::assertSame(1, $source->width);
        self::assertSame(1, $source->height);
        self::assertSame(ImageColorSpace::RGB, $source->colorSpace);
        self::assertNotNull($source->softMask);

        unlink($path);
    }

    public function testItCreatesA32BitBitfieldsBmpImageSourceWithAlternateMaskOrder(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'pdf2-image-source-');

        if ($path === false) {
            self::fail('Unable to create a temporary image source path.');
        }

        file_put_contents($path, BmpFixture::tiny32BitBitfieldsReversedBmpBytes());

        $source = ImageSource::fromPath($path);

        self::assertSame(1, $source->width);
        self::assertSame(1, $source->height);
        self::assertSame(ImageColorSpace::RGB, $source->colorSpace);
        self::assertNotNull($source->softMask);

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

        file_put_contents($path, BmpFixture::unsupported8BitPalettedBmpBytes());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf(
            "BMP image '%s' uses unsupported bits-per-pixel value 8.",
            $path,
        ));

        try {
            ImageSource::fromPath($path);
        } finally {
            unlink($path);
        }
    }

    public function testItRejectsUnsupportedBitfieldsMasks(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'pdf2-image-source-');

        if ($path === false) {
            self::fail('Unable to create a temporary image source path.');
        }

        file_put_contents($path, BmpFixture::unsupported32BitBitfieldsBmpBytes());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf(
            "BMP image '%s' uses unsupported 32-bit channel masks.",
            $path,
        ));

        try {
            ImageSource::fromPath($path);
        } finally {
            unlink($path);
        }
    }

    public function testItRejectsWebpFilesExplicitly(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'pdf2-image-source-');

        if ($path === false) {
            self::fail('Unable to create a temporary image source path.');
        }

        file_put_contents($path, WebpFixture::tinyWebpBytes());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf(
            "WEBP image '%s' requires GD WebP runtime support, which is not available.",
            $path,
        ));

        try {
            ImageSource::fromPath($path);
        } finally {
            unlink($path);
        }
    }

    public function testItRejectsAnimatedGifFiles(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'pdf2-image-source-');

        if ($path === false) {
            self::fail('Unable to create a temporary image source path.');
        }

        file_put_contents($path, GifFixture::tinyAnimatedGifBytes());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf(
            "GIF image '%s' uses multiple image frames, which are not supported.",
            $path,
        ));

        try {
            ImageSource::fromPath($path);
        } finally {
            unlink($path);
        }
    }

    public function testItRejectsInterlacedGifFiles(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'pdf2-image-source-');

        if ($path === false) {
            self::fail('Unable to create a temporary image source path.');
        }

        file_put_contents($path, GifFixture::tinyInterlacedGifBytes());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf(
            "GIF image '%s' uses interlacing, which is not supported.",
            $path,
        ));

        try {
            ImageSource::fromPath($path);
        } finally {
            unlink($path);
        }
    }

    public function testItRejectsMultipageTiffFiles(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'pdf2-image-source-');

        if ($path === false) {
            self::fail('Unable to create a temporary image source path.');
        }

        file_put_contents($path, TiffFixture::multipageBilevelTiffBytes());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf(
            "TIFF image '%s' uses multiple image directories, which are not supported.",
            $path,
        ));

        try {
            ImageSource::fromPath($path);
        } finally {
            unlink($path);
        }
    }

    public function testItCreatesAnIndexedImageSourceFromACompressedPaletteTiffPath(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'pdf2-image-source-');

        if ($path === false) {
            self::fail('Unable to create a temporary image source path.');
        }

        file_put_contents($path, TiffFixture::tinyLzwPaletteTiffBytes());

        $source = ImageSource::fromPath($path);

        self::assertStringContainsString('[/Indexed /DeviceRGB 1 <000000FF00FF>]', $source->pdfObjectContents());

        unlink($path);
    }

    public function testItCreatesAnIndexedImageSourceFromAnLzwPaletteTiffPath(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'pdf2-image-source-');

        if ($path === false) {
            self::fail('Unable to create a temporary image source path.');
        }

        file_put_contents($path, TiffFixture::tinyLzwPaletteTiffBytes());

        $source = ImageSource::fromPath($path);

        self::assertStringContainsString('[/Indexed /DeviceRGB 1 <000000FF00FF>]', $source->pdfObjectContents());

        unlink($path);
    }

    public function testItCreatesAnIndexedImageSourceFromAPackBitsPaletteTiffPath(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'pdf2-image-source-');

        if ($path === false) {
            self::fail('Unable to create a temporary image source path.');
        }

        file_put_contents($path, TiffFixture::tinyPackBitsPaletteTiffBytes());

        $source = ImageSource::fromPath($path);

        self::assertStringContainsString('[/Indexed /DeviceRGB 1 <000000FF00FF>]', $source->pdfObjectContents());

        unlink($path);
    }

    public function testItCreatesAnIndexedImageSourceFromADeflatePaletteTiffPath(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'pdf2-image-source-');

        if ($path === false) {
            self::fail('Unable to create a temporary image source path.');
        }

        file_put_contents($path, TiffFixture::tinyDeflatePaletteTiffBytes());

        $source = ImageSource::fromPath($path);

        self::assertStringContainsString('[/Indexed /DeviceRGB 1 <000000FF00FF>]', $source->pdfObjectContents());

        unlink($path);
    }

    public function testItRejectsPaletteTiffPredictorData(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'pdf2-image-source-');

        if ($path === false) {
            self::fail('Unable to create a temporary image source path.');
        }

        file_put_contents($path, TiffFixture::tinyPredictorPaletteTiffBytes());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf(
            "TIFF image '%s' uses unsupported TIFF predictor 2 for palette TIFF import.",
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
