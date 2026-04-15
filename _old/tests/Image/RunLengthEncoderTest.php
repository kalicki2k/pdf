<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Image;

use Kalle\Pdf\Image\RunLengthEncoder;
use PHPUnit\Framework\TestCase;

final class RunLengthEncoderTest extends TestCase
{
    public function testItEncodesMixedLiteralAndRepeatedRuns(): void
    {
        $encoded = new RunLengthEncoder()->encode('AAAABBBCCDAA');

        self::assertSame(
            "\xFD" . 'A' . "\xFE" . 'B' . "\x04CCDAA\x80",
            $encoded,
        );
    }

    public function testItEncodesEmptyDataAsEndOfDataOnly(): void
    {
        self::assertSame("\x80", new RunLengthEncoder()->encode(''));
    }
}
