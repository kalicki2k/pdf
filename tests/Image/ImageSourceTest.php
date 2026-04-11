<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Image;

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
}
