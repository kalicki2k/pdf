<?php

declare(strict_types=1);

namespace Kalle\Pdf\Element;

use InvalidArgumentException;

class Image extends Element
{
    private int $width;
    private int $height;
    private string $colorSpace;
    private string $filter;
    private string $data;
    private int $bitsPerComponent;
    private ?string $decodeParameters;
    private ?self $softMask;

    public function __construct(
        int $width,
        int $height,
        string $colorSpace,
        string $filter,
        string $data,
        int $bitsPerComponent = 8,
        ?string $decodeParameters = null,
        ?self $softMask = null,
    ) {
        $this->width = $width;
        $this->height = $height;
        $this->colorSpace = $colorSpace;
        $this->filter = $filter;
        $this->data = $data;
        $this->bitsPerComponent = $bitsPerComponent;
        $this->decodeParameters = $decodeParameters;
        $this->softMask = $softMask;
    }

    public static function fromFile(string $path): self
    {
        $data = file_get_contents($path);

        if ($data === false) {
            throw new InvalidArgumentException("Unable to read image file '$path'.");
        }

        $imageInfo = @getimagesize($path);

        if ($imageInfo === false) {
            throw new InvalidArgumentException("Unsupported or invalid image file '$path'.");
        }

        return match ($imageInfo[2]) {
            IMAGETYPE_JPEG => self::fromJpegData($path, $data, $imageInfo),
            IMAGETYPE_PNG => self::fromPngData($path, $data),
            default => throw new InvalidArgumentException(sprintf(
                "Unsupported image type '%s'.",
                $imageInfo['mime'],
            )),
        };
    }

    public function getWidth(): int
    {
        return $this->width;
    }

    public function getHeight(): int
    {
        return $this->height;
    }

    public function getSoftMask(): ?self
    {
        return $this->softMask;
    }

    public function render(?int $softMaskObjectId = null): string
    {
        $output = '<< /Type /XObject' . PHP_EOL;
        $output .= '/Subtype /Image' . PHP_EOL;
        $output .= "/Width {$this->width}" . PHP_EOL;
        $output .= "/Height {$this->height}" . PHP_EOL;
        $output .= "/ColorSpace /{$this->colorSpace}" . PHP_EOL;
        $output .= "/BitsPerComponent {$this->bitsPerComponent}" . PHP_EOL;
        $output .= "/Filter /{$this->filter}" . PHP_EOL;

        if ($this->decodeParameters !== null) {
            $output .= "/DecodeParms {$this->decodeParameters}" . PHP_EOL;
        }

        if ($softMaskObjectId !== null) {
            $output .= "/SMask {$softMaskObjectId} 0 R" . PHP_EOL;
        }

        $output .= '/Length ' . strlen($this->data) . ' >>' . PHP_EOL;
        $output .= 'stream' . PHP_EOL;
        $output .= $this->data . PHP_EOL;
        $output .= 'endstream' . PHP_EOL;

        return $output;
    }

    /**
     * @param array{0:int,1:int,2?:int,channels?:int,bits?:int,mime?:string} $imageInfo
     */
    private static function fromJpegData(string $path, string $data, array $imageInfo): self
    {
        $channels = (int) ($imageInfo['channels'] ?? 3);
        $colorSpace = match ($channels) {
            1 => 'DeviceGray',
            3 => 'DeviceRGB',
            4 => 'DeviceCMYK',
            default => throw new InvalidArgumentException("Unsupported JPEG channel count '$channels' in '$path'."),
        };

        return new self(
            width: (int) $imageInfo[0],
            height: (int) $imageInfo[1],
            colorSpace: $colorSpace,
            filter: 'DCTDecode',
            data: $data,
            bitsPerComponent: (int) ($imageInfo['bits'] ?? 8),
        );
    }

    private static function fromPngData(string $path, string $data): self
    {
        if (!str_starts_with($data, "\x89PNG\x0D\x0A\x1A\x0A")) {
            throw new InvalidArgumentException("Invalid PNG file '$path'.");
        }

        $offset = 8;
        $width = null;
        $height = null;
        $bitDepth = null;
        $colorType = null;
        $compressionMethod = null;
        $filterMethod = null;
        $interlaceMethod = null;
        $imageData = '';

        while ($offset + 8 <= strlen($data)) {
            $length = self::readUint32($data, $offset);
            $offset += 4;
            $type = substr($data, $offset, 4);
            $offset += 4;
            $chunkData = substr($data, $offset, $length);
            $offset += $length + 4;

            if ($type === 'IHDR') {
                $width = self::readUint32($chunkData, 0);
                $height = self::readUint32($chunkData, 4);
                $bitDepth = ord($chunkData[8]);
                $colorType = ord($chunkData[9]);
                $compressionMethod = ord($chunkData[10]);
                $filterMethod = ord($chunkData[11]);
                $interlaceMethod = ord($chunkData[12]);
                continue;
            }

            if ($type === 'IDAT') {
                $imageData .= $chunkData;
                continue;
            }

            if ($type === 'IEND') {
                break;
            }
        }

        if ($width === null || $height === null || $bitDepth === null || $colorType === null) {
            throw new InvalidArgumentException("Invalid PNG file '$path'.");
        }

        if ($compressionMethod !== 0 || $filterMethod !== 0) {
            throw new InvalidArgumentException("Unsupported PNG compression settings in '$path'.");
        }

        if ($interlaceMethod !== 0) {
            throw new InvalidArgumentException("Interlaced PNG images are not supported for '$path'.");
        }

        [$colorSpace, $colors] = match ($colorType) {
            0 => ['DeviceGray', 1],
            2 => ['DeviceRGB', 3],
            3 => throw new InvalidArgumentException("Indexed PNG images are not supported for '$path'."),
            4 => ['DeviceGray', 1],
            6 => ['DeviceRGB', 3],
            default => throw new InvalidArgumentException("Unsupported PNG color type '$colorType' in '$path'."),
        };

        if ($imageData === '') {
            throw new InvalidArgumentException("PNG file '$path' does not contain image data.");
        }

        if (in_array($colorType, [4, 6], true)) {
            if ($bitDepth !== 8) {
                throw new InvalidArgumentException("PNG images with alpha channels currently require 8 bits per component for '$path'.");
            }

            [$colorData, $alphaData] = self::splitPngAlphaChannels($path, $imageData, $width, $height, $colors);

            return new self(
                width: $width,
                height: $height,
                colorSpace: $colorSpace,
                filter: 'FlateDecode',
                data: $colorData,
                bitsPerComponent: $bitDepth,
                decodeParameters: sprintf(
                    '<< /Predictor 15 /Colors %d /BitsPerComponent %d /Columns %d >>',
                    $colors,
                    $bitDepth,
                    $width,
                ),
                softMask: new self(
                    width: $width,
                    height: $height,
                    colorSpace: 'DeviceGray',
                    filter: 'FlateDecode',
                    data: $alphaData,
                    bitsPerComponent: $bitDepth,
                    decodeParameters: sprintf(
                        '<< /Predictor 15 /Colors 1 /BitsPerComponent %d /Columns %d >>',
                        $bitDepth,
                        $width,
                    ),
                ),
            );
        }

        return new self(
            width: $width,
            height: $height,
            colorSpace: $colorSpace,
            filter: 'FlateDecode',
            data: $imageData,
            bitsPerComponent: $bitDepth,
            decodeParameters: sprintf(
                '<< /Predictor 15 /Colors %d /BitsPerComponent %d /Columns %d >>',
                $colors,
                $bitDepth,
                $width,
            ),
        );
    }

    private static function readUint32(string $data, int $offset): int
    {
        $value = unpack('N', substr($data, $offset, 4));

        if ($value === false || !isset($value[1]) || !is_int($value[1])) {
            throw new InvalidArgumentException('Unable to read PNG chunk data.');
        }

        return $value[1];
    }

    /**
     * @return array{0:string,1:string}
     */
    private static function splitPngAlphaChannels(string $path, string $compressedData, int $width, int $height, int $colors): array
    {
        $decompressedData = gzuncompress($compressedData);

        if ($decompressedData === false) {
            throw new InvalidArgumentException("Unable to decompress PNG image data for '$path'.");
        }

        $channels = $colors + 1;
        $bytesPerPixel = $channels;
        $scanlineLength = 1 + ($width * $channels);
        $expectedLength = $scanlineLength * $height;

        if (strlen($decompressedData) !== $expectedLength) {
            throw new InvalidArgumentException("Unexpected PNG alpha image data length for '$path'.");
        }

        $colorOutput = '';
        $alphaOutput = '';
        $previousRow = array_fill(0, $width * $channels, 0);

        for ($rowIndex = 0; $rowIndex < $height; $rowIndex++) {
            $rowOffset = $rowIndex * $scanlineLength;
            $filterType = ord($decompressedData[$rowOffset]);
            $filteredRow = substr($decompressedData, $rowOffset + 1, $width * $channels);
            $rowBytes = array_map('ord', str_split($filteredRow));
            $unfilteredRow = self::unfilterPngScanline($rowBytes, $previousRow, $filterType, $bytesPerPixel, $path);
            $previousRow = $unfilteredRow;

            $colorOutput .= chr(0);
            $alphaOutput .= chr(0);

            for ($pixelOffset = 0, $count = count($unfilteredRow); $pixelOffset < $count; $pixelOffset += $channels) {
                for ($channelIndex = 0; $channelIndex < $colors; $channelIndex++) {
                    $colorOutput .= chr($unfilteredRow[$pixelOffset + $channelIndex]);
                }

                $alphaOutput .= chr($unfilteredRow[$pixelOffset + $colors]);
            }
        }

        $compressedColorOutput = gzcompress($colorOutput);
        $compressedAlphaOutput = gzcompress($alphaOutput);

        if ($compressedColorOutput === false || $compressedAlphaOutput === false) {
            throw new InvalidArgumentException("Unable to compress PNG alpha image data for '$path'.");
        }

        return [$compressedColorOutput, $compressedAlphaOutput];
    }

    /**
     * @param list<int> $rowBytes
     * @param list<int> $previousRow
     * @return list<int>
     */
    private static function unfilterPngScanline(array $rowBytes, array $previousRow, int $filterType, int $bytesPerPixel, string $path): array
    {
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

    private static function paethPredictor(int $left, int $up, int $upperLeft): int
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
