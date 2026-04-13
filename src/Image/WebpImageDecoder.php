<?php

declare(strict_types=1);

namespace Kalle\Pdf\Image;

use function function_exists;
use function gzcompress;
use function imagecolorat;
use function imagedestroy;
use function imageistruecolor;
use function imagepalettetotruecolor;
use function imagesx;
use function imagesy;
use function is_string;
use function round;
use function sprintf;

use GdImage;
use InvalidArgumentException;
use RuntimeException;

final readonly class WebpImageDecoder
{
    public function decode(string $data, string $path = 'memory'): ImageSource
    {
        if (!function_exists('imagecreatefromstring')) {
            throw new InvalidArgumentException(sprintf(
                "WEBP image '%s' requires GD WebP runtime support, which is not available.",
                $path,
            ));
        }

        $image = imagecreatefromstring($data);

        if (!($image instanceof GdImage)) {
            throw new InvalidArgumentException(sprintf(
                "WEBP image '%s' could not be decoded by the available GD runtime.",
                $path,
            ));
        }

        if (!imageistruecolor($image) && function_exists('imagepalettetotruecolor')) {
            imagepalettetotruecolor($image);
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $rgb = '';
        $alpha = '';
        $hasAlpha = false;

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $argb = imagecolorat($image, $x, $y);
                $red = ($argb >> 16) & 0xFF;
                $green = ($argb >> 8) & 0xFF;
                $blue = $argb & 0xFF;
                $gdAlpha = ($argb >> 24) & 0x7F;
                $alphaByte = (int) round((127 - $gdAlpha) * 255 / 127);

                $rgb .= chr($red) . chr($green) . chr($blue);
                $alpha .= chr($alphaByte & 0xFF);
                $hasAlpha = $hasAlpha || $alphaByte !== 0xFF;
            }
        }

        imagedestroy($image);

        $compressedRgb = gzcompress($rgb);

        if (!is_string($compressedRgb)) {
            throw new RuntimeException(sprintf(
                "Unable to compress WEBP image '%s'.",
                $path,
            ));
        }

        $softMask = null;

        if ($hasAlpha) {
            $compressedAlpha = gzcompress($alpha);

            if (!is_string($compressedAlpha)) {
                throw new RuntimeException(sprintf(
                    "Unable to compress WEBP alpha channel for '%s'.",
                    $path,
                ));
            }

            $softMask = ImageSource::alphaMask($compressedAlpha, $width, $height);
        }

        return ImageSource::flate($compressedRgb, $width, $height, ImageColorSpace::RGB, 8, $softMask);
    }
}
