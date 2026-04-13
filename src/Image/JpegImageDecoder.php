<?php

declare(strict_types=1);

namespace Kalle\Pdf\Image;

use function get_debug_type;
use function is_scalar;
use function sprintf;

use InvalidArgumentException;
use RuntimeException;

final readonly class JpegImageDecoder
{
    /**
     * @param array<string|int, mixed> $imageInfo
     */
    public function decode(string $data, array $imageInfo, string $path = 'memory'): ImageSource
    {
        $width = $imageInfo[0] ?? null;
        $height = $imageInfo[1] ?? null;

        if (!is_int($width) || !is_int($height)) {
            throw new RuntimeException(sprintf(
                "JPEG image '%s' returned invalid dimensions from metadata inspection.",
                $path,
            ));
        }

        return ImageSource::jpeg(
            data: $data,
            width: $width,
            height: $height,
            colorSpace: $this->colorSpaceFromImageInfo($path, $imageInfo),
        );
    }

    /**
     * @param array<string|int, mixed> $imageInfo
     */
    private function colorSpaceFromImageInfo(string $path, array $imageInfo): ImageColorSpace
    {
        $channels = $imageInfo['channels'] ?? null;

        return match ($channels) {
            1 => ImageColorSpace::GRAY,
            3, null => ImageColorSpace::RGB,
            4 => ImageColorSpace::CMYK,
            default => throw new InvalidArgumentException(sprintf(
                "JPEG image '%s' uses unsupported channel count '%s'.",
                $path,
                is_scalar($channels) ? (string) $channels : get_debug_type($channels),
            )),
        };
    }
}
