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

    public function __construct(
        int $width,
        int $height,
        string $colorSpace,
        string $filter,
        string $data,
        int $bitsPerComponent = 8,
        ?string $decodeParameters = null,
    ) {
        $this->width = $width;
        $this->height = $height;
        $this->colorSpace = $colorSpace;
        $this->filter = $filter;
        $this->data = $data;
        $this->bitsPerComponent = $bitsPerComponent;
        $this->decodeParameters = $decodeParameters;
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

    public function render(): string
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
            4, 6 => throw new InvalidArgumentException("PNG images with alpha channels are not supported for '$path'."),
            default => throw new InvalidArgumentException("Unsupported PNG color type '$colorType' in '$path'."),
        };

        if ($imageData === '') {
            throw new InvalidArgumentException("PNG file '$path' does not contain image data.");
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
}
