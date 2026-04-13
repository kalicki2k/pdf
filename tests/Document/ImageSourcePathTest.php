<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use function file_put_contents;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

use Kalle\Pdf\Document\DefaultDocumentBuilder;
use Kalle\Pdf\Image\ImagePlacement;
use Kalle\Pdf\Tests\Image\JpegFixture;
use Kalle\Pdf\Tests\Image\PngFixture;
use Kalle\Pdf\Tests\Image\TiffFixture;
use PHPUnit\Framework\TestCase;

final class ImageSourcePathTest extends TestCase
{
    public function testItBuildsPageImageResourcesFromAJpegPath(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'pdf2-embedded-image-');

        if ($path === false) {
            self::fail('Unable to allocate a temporary image path.');
        }

        file_put_contents($path, JpegFixture::tinyGrayJpegBytes());

        $document = DefaultDocumentBuilder::make()
            ->imageFile($path, ImagePlacement::at(24, 48, width: 72))
            ->build();

        self::assertCount(1, $document->pages[0]->imageResources);
        self::assertStringContainsString("72 0 0 72 24 48 cm\n/Im1 Do", $document->pages[0]->contents);

        unlink($path);
    }

    public function testItBuildsPageImageResourcesFromACmykJpegPath(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'pdf2-embedded-image-');

        if ($path === false) {
            self::fail('Unable to allocate a temporary image path.');
        }

        file_put_contents($path, JpegFixture::tinyCmykJpegBytes());

        $document = DefaultDocumentBuilder::make()
            ->imageFile($path, ImagePlacement::at(24, 48, width: 72))
            ->build();

        self::assertCount(1, $document->pages[0]->imageResources);
        self::assertStringContainsString('/ColorSpace /DeviceCMYK', $document->pages[0]->imageResources['Im1']->pdfObjectContents());
        self::assertStringContainsString("72 0 0 72 24 48 cm\n/Im1 Do", $document->pages[0]->contents);

        unlink($path);
    }

    public function testItBuildsPageImageResourcesFromAPngPath(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'pdf2-embedded-image-');

        if ($path === false) {
            self::fail('Unable to allocate a temporary image path.');
        }

        file_put_contents($path, PngFixture::tinyRgbaPngBytes());

        $document = DefaultDocumentBuilder::make()
            ->imageFile($path, ImagePlacement::at(12, 18, width: 24))
            ->build();

        self::assertCount(1, $document->pages[0]->imageResources);
        self::assertNotNull($document->pages[0]->imageResources['Im1']->softMask);
        self::assertStringContainsString("24 0 0 24 12 18 cm\n/Im1 Do", $document->pages[0]->contents);

        unlink($path);
    }

    public function testItBuildsPageImageResourcesFromAnIndexedPngPath(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'pdf2-embedded-image-');

        if ($path === false) {
            self::fail('Unable to allocate a temporary image path.');
        }

        file_put_contents($path, PngFixture::tinyIndexedPngBytes());

        $document = DefaultDocumentBuilder::make()
            ->imageFile($path, ImagePlacement::at(8, 10, width: 16))
            ->build();

        self::assertCount(1, $document->pages[0]->imageResources);
        self::assertStringContainsString('[/Indexed /DeviceRGB 0 <808080>]', $document->pages[0]->imageResources['Im1']->pdfObjectContents());
        self::assertStringContainsString("16 0 0 16 8 10 cm\n/Im1 Do", $document->pages[0]->contents);

        unlink($path);
    }

    public function testItBuildsPageImageResourcesFromAnIndexedTransparentPngPath(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'pdf2-embedded-image-');

        if ($path === false) {
            self::fail('Unable to allocate a temporary image path.');
        }

        file_put_contents($path, PngFixture::tinyIndexedTransparentPngBytes());

        $document = DefaultDocumentBuilder::make()
            ->imageFile($path, ImagePlacement::at(8, 10, width: 16))
            ->build();

        self::assertCount(1, $document->pages[0]->imageResources);
        self::assertNotNull($document->pages[0]->imageResources['Im1']->softMask);
        self::assertStringContainsString('[/Indexed /DeviceRGB 0 <000000>]', $document->pages[0]->imageResources['Im1']->pdfObjectContents());
        self::assertStringContainsString("16 0 0 16 8 10 cm\n/Im1 Do", $document->pages[0]->contents);

        unlink($path);
    }

    public function testItBuildsPageImageResourcesFromACcittTiffPath(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'pdf2-embedded-image-');

        if ($path === false) {
            self::fail('Unable to allocate a temporary image path.');
        }

        file_put_contents($path, TiffFixture::tinyCcittGroup4TiffBytes());

        $document = DefaultDocumentBuilder::make()
            ->imageFile($path, ImagePlacement::at(8, 10, width: 16))
            ->build();

        self::assertCount(1, $document->pages[0]->imageResources);
        self::assertStringContainsString('/Filter /CCITTFaxDecode', $document->pages[0]->imageResources['Im1']->pdfObjectContents());
        self::assertStringContainsString('/DecodeParms << /K -1 /Columns 1 /Rows 1 /BlackIs1 true >>', $document->pages[0]->imageResources['Im1']->pdfObjectContents());

        unlink($path);
    }
}
