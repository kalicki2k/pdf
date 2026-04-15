<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Image;

use InvalidArgumentException;
use Kalle\Pdf\Image\PackBitsDecoder;
use PHPUnit\Framework\TestCase;

final class PackBitsDecoderTest extends TestCase
{
    public function testItDecodesLiteralAndRepeatedRuns(): void
    {
        $decoder = new PackBitsDecoder();

        self::assertSame('AABBB', $decoder->decode("\x01AA\xFEB"));
    }

    public function testItRejectsTruncatedLiteralRuns(): void
    {
        $decoder = new PackBitsDecoder();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('PackBits data ends inside a literal run.');

        $decoder->decode("\x02AB");
    }
}
