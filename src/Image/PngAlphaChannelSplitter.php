<?php

declare(strict_types=1);

namespace Kalle\Pdf\Image;

use InvalidArgumentException;
use Kalle\Pdf\Binary\BinaryData;

final class PngAlphaChannelSplitter
{
    /**
     * @return array{0:BinaryData,1:BinaryData}
     */
    public static function split(string $path, BinaryData $compressedData, int $width, int $height, int $colors): array
    {
        $colorData = BinaryData::fromSource(new PngAlphaChannelBinaryDataSource(
            $path,
            $compressedData,
            $width,
            $height,
            $colors,
            false,
        ));
        $alphaData = BinaryData::fromSource(new PngAlphaChannelBinaryDataSource(
            $path,
            $compressedData,
            $width,
            $height,
            $colors,
            true,
        ));

        $colorData->length();
        $alphaData->length();

        return [$colorData, $alphaData];
    }

    /**
     * @param list<int> $rowBytes
     * @param list<int> $previousRow
     * @return list<int>
     */
    public static function unfilterScanline(
        array $rowBytes,
        array $previousRow,
        int $filterType,
        int $bytesPerPixel,
        string $path,
    ): array {
        $result = [];

        foreach ($rowBytes as $index => $value) {
            $left = $index >= $bytesPerPixel ? $result[$index - $bytesPerPixel] : 0;
            $up = $previousRow[$index] ?? 0;
            $upperLeft = $index >= $bytesPerPixel ? ($previousRow[$index - $bytesPerPixel] ?? 0) : 0;

            $result[] = match ($filterType) {
                0 => $value,
                1 => ($value + $left) & 0xFF,
                2 => ($value + $up) & 0xFF,
                3 => ($value + intdiv($left + $up, 2)) & 0xFF,
                4 => ($value + self::paethPredictor($left, $up, $upperLeft)) & 0xFF,
                default => throw new InvalidArgumentException("Unsupported PNG filter type '$filterType' in '$path'."),
            };
        }

        return $result;
    }

    public static function paethPredictor(int $left, int $up, int $upperLeft): int
    {
        $prediction = $left + $up - $upperLeft;
        $distanceLeft = abs($prediction - $left);
        $distanceUp = abs($prediction - $up);
        $distanceUpperLeft = abs($prediction - $upperLeft);

        if ($distanceLeft <= $distanceUp && $distanceLeft <= $distanceUpperLeft) {
            return $left;
        }

        if ($distanceUp <= $distanceUpperLeft) {
            return $up;
        }

        return $upperLeft;
    }
}
