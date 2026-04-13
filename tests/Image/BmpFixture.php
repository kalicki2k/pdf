<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Image;

final class BmpFixture
{
    public static function tiny24BitRgbBmpBytes(): string
    {
        return 'BM'
            . pack('V', 58)
            . pack('v', 0)
            . pack('v', 0)
            . pack('V', 54)
            . pack('V', 40)
            . pack('V', 1)
            . pack('V', 1)
            . pack('v', 1)
            . pack('v', 24)
            . pack('V', 0)
            . pack('V', 4)
            . pack('V', 2835)
            . pack('V', 2835)
            . pack('V', 0)
            . pack('V', 0)
            . "\x00\x00\xFF\x00";
    }

    public static function tiny32BitRgbaBmpBytes(): string
    {
        return 'BM'
            . pack('V', 58)
            . pack('v', 0)
            . pack('v', 0)
            . pack('V', 54)
            . pack('V', 40)
            . pack('V', 1)
            . pack('V', 1)
            . pack('v', 1)
            . pack('v', 32)
            . pack('V', 0)
            . pack('V', 4)
            . pack('V', 2835)
            . pack('V', 2835)
            . pack('V', 0)
            . pack('V', 0)
            . "\x00\x00\xFF\x80";
    }

    public static function tiny32BitBitfieldsRgbaBmpBytes(): string
    {
        return 'BM'
            . pack('V', 74)
            . pack('v', 0)
            . pack('v', 0)
            . pack('V', 70)
            . pack('V', 40)
            . pack('V', 1)
            . pack('V', 1)
            . pack('v', 1)
            . pack('v', 32)
            . pack('V', 3)
            . pack('V', 4)
            . pack('V', 2835)
            . pack('V', 2835)
            . pack('V', 0)
            . pack('V', 0)
            . pack('V', 0x00FF0000)
            . pack('V', 0x0000FF00)
            . pack('V', 0x000000FF)
            . pack('V', 0xFF000000)
            . "\x00\x00\xFF\x80";
    }

    public static function tiny32BitBitfieldsReversedBmpBytes(): string
    {
        return 'BM'
            . pack('V', 74)
            . pack('v', 0)
            . pack('v', 0)
            . pack('V', 70)
            . pack('V', 40)
            . pack('V', 1)
            . pack('V', 1)
            . pack('v', 1)
            . pack('v', 32)
            . pack('V', 3)
            . pack('V', 4)
            . pack('V', 2835)
            . pack('V', 2835)
            . pack('V', 0)
            . pack('V', 0)
            . pack('V', 0x000000FF)
            . pack('V', 0x0000FF00)
            . pack('V', 0x00FF0000)
            . pack('V', 0xFF000000)
            . "\xFF\x00\x00\x80";
    }

    public static function unsupported32BitBitfieldsBmpBytes(): string
    {
        return 'BM'
            . pack('V', 74)
            . pack('v', 0)
            . pack('v', 0)
            . pack('V', 70)
            . pack('V', 40)
            . pack('V', 1)
            . pack('V', 1)
            . pack('v', 1)
            . pack('v', 32)
            . pack('V', 3)
            . pack('V', 4)
            . pack('V', 2835)
            . pack('V', 2835)
            . pack('V', 0)
            . pack('V', 0)
            . pack('V', 0x0F000000)
            . pack('V', 0x0000FF00)
            . pack('V', 0x000000FF)
            . pack('V', 0xF0000000)
            . "\x00\x00\xFF\x80";
    }

    public static function unsupported8BitPalettedBmpBytes(): string
    {
        return 'BM'
            . pack('V', 62)
            . pack('v', 0)
            . pack('v', 0)
            . pack('V', 58)
            . pack('V', 40)
            . pack('V', 1)
            . pack('V', 1)
            . pack('v', 1)
            . pack('v', 8)
            . pack('V', 0)
            . pack('V', 4)
            . pack('V', 2835)
            . pack('V', 2835)
            . pack('V', 1)
            . pack('V', 1)
            . "\x00\x00\xFF\x00"
            . "\x00\x00\x00\x00";
    }
}
