<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Image;

use function count;
use function gzcompress;
use function is_string;
use function strlen;

use Kalle\Pdf\Image\CcittFaxEncoder;
use Kalle\Pdf\Image\LzwEncoder;
use RuntimeException;

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

    public static function tinyUncompressedGrayscaleTiffBytes(): string
    {
        return self::imageTiffBytes(
            width: 2,
            height: 1,
            bitsPerSample: [8],
            compression: 1,
            photometricInterpretation: 1,
            stripData: ["\x22\xCC"],
            rowsPerStrip: 1,
            samplesPerPixel: 1,
        );
    }

    public static function tinyPackBitsGrayscaleTiffBytes(): string
    {
        return self::imageTiffBytes(
            width: 2,
            height: 1,
            bitsPerSample: [8],
            compression: 32773,
            photometricInterpretation: 1,
            stripData: ["\x01\x22\xCC"],
            rowsPerStrip: 1,
            samplesPerPixel: 1,
        );
    }

    public static function tinyPredictorLzwGrayscaleTiffBytes(): string
    {
        $row = self::applyHorizontalPredictor("\x10\x30");

        return self::imageTiffBytes(
            width: 2,
            height: 1,
            bitsPerSample: [8],
            compression: 5,
            photometricInterpretation: 1,
            stripData: [(new LzwEncoder())->encode($row)],
            rowsPerStrip: 1,
            samplesPerPixel: 1,
            predictor: 2,
        );
    }

    public static function tinyUncompressedRgbTiffBytes(): string
    {
        return self::imageTiffBytes(
            width: 1,
            height: 1,
            bitsPerSample: [8, 8, 8],
            compression: 1,
            photometricInterpretation: 2,
            stripData: ["\xFF\x00\x80"],
            rowsPerStrip: 1,
            samplesPerPixel: 3,
        );
    }

    public static function tinyLzwRgbTiffBytes(): string
    {
        return self::imageTiffBytes(
            width: 1,
            height: 1,
            bitsPerSample: [8, 8, 8],
            compression: 5,
            photometricInterpretation: 2,
            stripData: [(new LzwEncoder())->encode("\xFF\x00\x80")],
            rowsPerStrip: 1,
            samplesPerPixel: 3,
        );
    }

    public static function tinyPredictorDeflateRgbTiffBytes(): string
    {
        $row = self::applyHorizontalPredictor("\x20\x40\x60\x50\x70\x90", 3);
        $compressed = gzcompress($row);

        if (!is_string($compressed)) {
            throw new RuntimeException('Unable to compress TIFF predictor fixture.');
        }

        return self::imageTiffBytes(
            width: 2,
            height: 1,
            bitsPerSample: [8, 8, 8],
            compression: 8,
            photometricInterpretation: 2,
            stripData: [$compressed],
            rowsPerStrip: 1,
            samplesPerPixel: 3,
            predictor: 2,
        );
    }

    public static function tinyMultiStripCcittGroup3TiffBytes(): string
    {
        $encoder = new CcittFaxEncoder();

        return self::bilevelTiffBytes(
            width: 8,
            height: 2,
            compression: 3,
            stripData: [
                $encoder->encodeRows(['11111111']),
                $encoder->encodeRows(['00000000']),
            ],
            rowsPerStrip: 1,
            photometricInterpretation: 1,
        );
    }

    public static function multipageBilevelTiffBytes(): string
    {
        $bytes = self::tinyUncompressedBilevelTiffBytes();
        $secondIfdOffset = strlen($bytes);

        return substr($bytes, 0, 118)
            . pack('V', $secondIfdOffset)
            . substr($bytes, 122)
            . pack('v', 0)
            . pack('V', 0);
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
     * @param list<int> $bitsPerSample
     * @param list<string> $stripData
     */
    private static function imageTiffBytes(
        int $width,
        int $height,
        array $bitsPerSample,
        int $compression,
        int $photometricInterpretation,
        array $stripData,
        int $rowsPerStrip,
        int $samplesPerPixel,
        int $planarConfiguration = 1,
        int $predictor = 1,
    ): string {
        $ifdOffset = 8;
        $entryCount = $predictor === 1 ? 10 : 11;
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
            self::entryShort(258, $bitsPerSample, $nextOffset, $extraValues),
            self::entryShort(259, [$compression], $nextOffset, $extraValues),
            self::entryShort(262, [$photometricInterpretation], $nextOffset, $extraValues),
            self::entryLong(273, $stripOffsets, $nextOffset, $extraValues),
            self::entryShort(277, [$samplesPerPixel], $nextOffset, $extraValues),
            self::entryLong(278, [$rowsPerStrip], $nextOffset, $extraValues),
            self::entryLong(279, $stripByteCounts, $nextOffset, $extraValues),
            self::entryShort(284, [$planarConfiguration], $nextOffset, $extraValues),
        ];

        if ($predictor !== 1) {
            $entries[] = self::entryShort(317, [$predictor], $nextOffset, $extraValues);
        }

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

    private static function applyHorizontalPredictor(string $bytes, int $samplesPerPixel = 1): string
    {
        $predicted = '';

        for ($index = 0; $index < strlen($bytes); $index++) {
            $current = ord($bytes[$index]);

            if ($index < $samplesPerPixel) {
                $predicted .= chr($current);

                continue;
            }

            $previous = ord($bytes[$index - $samplesPerPixel]);
            $predicted .= chr(($current - $previous + 256) & 0xFF);
        }

        return $predicted;
    }
}
