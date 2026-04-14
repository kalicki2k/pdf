<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Image;

use InvalidArgumentException;
use Kalle\Pdf\Image\MonochromeBitmapEncoder;
use PHPUnit\Framework\TestCase;

final class MonochromeBitmapEncoderTest extends TestCase
{
    public function testItPacksRowsIntoPdfDeviceGrayBits(): void
    {
        $bitmap = new MonochromeBitmapEncoder()->encodeRows([
            '10101010',
            '01010101',
        ]);

        self::assertSame(8, $bitmap->width);
        self::assertSame(2, $bitmap->height);
        self::assertSame("\x55\xAA", $bitmap->data);
    }

    public function testItPadsIncompleteRowsWithWhitePixels(): void
    {
        $bitmap = new MonochromeBitmapEncoder()->encodeRows([
            '111',
        ]);

        self::assertSame("\x1F", $bitmap->data);
    }

    public function testItRejectsRowsWithDifferentWidths(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Monochrome bitmap row 2 has width 1; expected 2.');

        new MonochromeBitmapEncoder()->encodeRows([
            '11',
            '1',
        ]);
    }
}
