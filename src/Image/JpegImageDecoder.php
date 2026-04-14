<?php

declare(strict_types=1);

namespace Kalle\Pdf\Image;

use function get_debug_type;
use function is_scalar;
use function ord;
use function sprintf;
use function strlen;
use function substr;

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
            decode: $this->decodeArrayFromMetadata($data, $imageInfo),
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

    /**
     * @param array<string|int, mixed> $imageInfo
     * @return list<float|int>|null
     */
    private function decodeArrayFromMetadata(string $data, array $imageInfo): ?array
    {
        if (($imageInfo['channels'] ?? null) !== 4) {
            return null;
        }

        return $this->isAdobeCmykJpeg($data)
            ? [1, 0, 1, 0, 1, 0, 1, 0]
            : null;
    }

    private function isAdobeCmykJpeg(string $data): bool
    {
        $offset = 2;
        $length = strlen($data);

        while ($offset + 4 <= $length) {
            if (ord($data[$offset]) !== 0xFF) {
                break;
            }

            while ($offset < $length && ord($data[$offset]) === 0xFF) {
                $offset++;
            }

            if ($offset >= $length) {
                break;
            }

            $marker = ord($data[$offset]);
            $offset++;

            if ($marker === 0xD8 || $marker === 0xD9 || ($marker >= 0xD0 && $marker <= 0xD7) || $marker === 0x01) {
                continue;
            }

            if ($offset + 2 > $length) {
                break;
            }

            $segmentLength = (ord($data[$offset]) << 8) | ord($data[$offset + 1]);

            if ($segmentLength < 2 || $offset + $segmentLength > $length) {
                break;
            }

            if ($marker === 0xEE) {
                $payload = substr($data, $offset + 2, $segmentLength - 2);

                if (strlen($payload) >= 12 && substr($payload, 0, 5) === 'Adobe') {
                    $colorTransform = ord($payload[11]);

                    return $colorTransform === 0 || $colorTransform === 2;
                }
            }

            $offset += $segmentLength;
        }

        return false;
    }
}
