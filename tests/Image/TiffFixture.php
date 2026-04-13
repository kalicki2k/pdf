<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Image;

use function count;
use function strlen;

final class TiffFixture
{
    public static function tinyCcittGroup4TiffBytes(): string
    {
        return self::bilevelTiffBytes(
            width: 1,
            height: 1,
            compression: 4,
            stripData: ["\x00"],
            rowsPerStrip: 1,
            photometricInterpretation: 1,
        );
    }

    public static function tinyUncompressedBilevelTiffBytes(): string
    {
        return self::bilevelTiffBytes(
            width: 8,
            height: 2,
            compression: 1,
            stripData: ["\x55\xAA"],
            rowsPerStrip: 2,
            photometricInterpretation: 0,
        );
    }

    public static function tinyMultiStripUncompressedBilevelTiffBytes(): string
    {
        return self::bilevelTiffBytes(
            width: 8,
            height: 2,
            compression: 1,
            stripData: ["\x55", "\xAA"],
            rowsPerStrip: 1,
            photometricInterpretation: 0,
        );
    }

    /**
     * @param list<string> $stripData
     */
    private static function bilevelTiffBytes(
        int $width,
        int $height,
        int $compression,
        array $stripData,
        int $rowsPerStrip,
        int $photometricInterpretation,
    ): string {
        $ifdOffset = 8;
        $entryCount = 9;
        $firstDataOffset = $ifdOffset + 2 + ($entryCount * 12) + 4;
        $dataArea = '';
        $nextOffset = $firstDataOffset;
        $extraValues = '';

        $stripOffsets = [];
        $stripByteCounts = [];

        foreach ($stripData as $strip) {
            $stripOffsets[] = $nextOffset;
            $stripByteCounts[] = strlen($strip);
            $dataArea .= $strip;
            $nextOffset += strlen($strip);
        }

        $entries = [
            self::entryLong(256, [$width], $nextOffset, $extraValues),
            self::entryLong(257, [$height], $nextOffset, $extraValues),
            self::entryShort(258, [1], $nextOffset, $extraValues),
            self::entryShort(259, [$compression], $nextOffset, $extraValues),
            self::entryShort(262, [$photometricInterpretation], $nextOffset, $extraValues),
            self::entryLong(273, $stripOffsets, $nextOffset, $extraValues),
            self::entryShort(277, [1], $nextOffset, $extraValues),
            self::entryLong(278, [$rowsPerStrip], $nextOffset, $extraValues),
            self::entryLong(279, $stripByteCounts, $nextOffset, $extraValues),
        ];

        return 'II'
            . pack('v', 42)
            . pack('V', $ifdOffset)
            . pack('v', $entryCount)
            . implode('', $entries)
            . pack('V', 0)
            . $dataArea
            . $extraValues;
    }

    /**
     * @param list<int> $values
     */
    private static function entryShort(int $tag, array $values, int &$nextOffset, string &$extraValues): string
    {
        $valueBytes = implode('', array_map(static fn (int $value): string => pack('v', $value), $values));

        return self::entry($tag, 3, $values, $valueBytes, $nextOffset, $extraValues);
    }

    /**
     * @param list<int> $values
     */
    private static function entryLong(int $tag, array $values, int &$nextOffset, string &$extraValues): string
    {
        $valueBytes = implode('', array_map(static fn (int $value): string => pack('V', $value), $values));

        return self::entry($tag, 4, $values, $valueBytes, $nextOffset, $extraValues);
    }

    /**
     * @param list<int> $values
     */
    private static function entry(
        int $tag,
        int $type,
        array $values,
        string $valueBytes,
        int &$nextOffset,
        string &$extraValues,
    ): string {
        $count = count($values);

        if (strlen($valueBytes) <= 4) {
            return pack('vvV', $tag, $type, $count) . str_pad($valueBytes, 4, "\x00");
        }

        $offset = $nextOffset;
        $extraValues .= $valueBytes;
        $nextOffset += strlen($valueBytes);

        return pack('vvVV', $tag, $type, $count, $offset);
    }
}
