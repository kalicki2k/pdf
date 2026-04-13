<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Image;

use InvalidArgumentException;
use Kalle\Pdf\Image\CcittFaxEncoder;
use PHPUnit\Framework\TestCase;

final class CcittFaxEncoderTest extends TestCase
{
    public function testItEncodesMonochromeRowsIntoCcittData(): void
    {
        $encoded = (new CcittFaxEncoder())->encodeRows([
            '11111111',
            '00000000',
        ]);

        self::assertNotSame('', $encoded);
    }

    public function testItRejectsMismatchingBitmapSizes(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('CCITT bitmap size does not match width and height.');

        (new CcittFaxEncoder())->encodeBitmap("\x00", 16, 1);
    }
}
