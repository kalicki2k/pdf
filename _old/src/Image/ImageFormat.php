<?php

declare(strict_types=1);

namespace Kalle\Pdf\Image;

use function sprintf;

use InvalidArgumentException;

enum ImageFormat: string
{
    case JPEG = 'jpeg';
    case PNG = 'png';
    case TIFF = 'tiff';
    case GIF = 'gif';
    case BMP = 'bmp';
    case WEBP = 'webp';

    /**
     * @param array<string|int, mixed> $imageInfo
     */
    public static function fromImageInfo(array $imageInfo, string $path): self
    {
        $type = $imageInfo[2] ?? null;

        return match ($type) {
            IMAGETYPE_JPEG => self::JPEG,
            IMAGETYPE_PNG => self::PNG,
            IMAGETYPE_TIFF_II,
            IMAGETYPE_TIFF_MM => self::TIFF,
            IMAGETYPE_GIF => self::GIF,
            IMAGETYPE_BMP => self::BMP,
            IMAGETYPE_WEBP => self::WEBP,
            default => throw new InvalidArgumentException(sprintf(
                "Image path '%s' uses an unsupported image format.",
                $path,
            )),
        };
    }

    public function unsupportedVariantMessage(string $path): string
    {
        return sprintf(
            "Image path '%s' uses the unsupported %s image format.",
            $path,
            strtoupper($this->value),
        );
    }
}
