<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Image;

final class TiffFixture
{
    public static function tinyCcittGroup4TiffBytes(): string
    {
        $stripData = "\x00";
        $ifdOffset = 8;
        $entryCount = 9;
        $stripOffset = $ifdOffset + 2 + ($entryCount * 12) + 4;

        $entries = [
            self::entryLong(256, 1),
            self::entryLong(257, 1),
            self::entryShort(258, 1),
            self::entryShort(259, 4),
            self::entryShort(262, 1),
            self::entryLong(273, $stripOffset),
            self::entryShort(277, 1),
            self::entryLong(278, 1),
            self::entryLong(279, strlen($stripData)),
        ];

        return 'II'
            . pack('v', 42)
            . pack('V', $ifdOffset)
            . pack('v', $entryCount)
            . implode('', $entries)
            . pack('V', 0)
            . $stripData;
    }

    private static function entryShort(int $tag, int $value): string
    {
        return pack('vvVvxx', $tag, 3, 1, $value);
    }

    private static function entryLong(int $tag, int $value): string
    {
        return pack('vvVV', $tag, 4, 1, $value);
    }
}
