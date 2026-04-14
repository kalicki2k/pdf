<?php

declare(strict_types=1);

namespace Kalle\Pdf\Image;

use function function_exists;
use function imagecolorat;
use function imagedestroy;
use function imageistruecolor;
use function imagepalettetotruecolor;
use function imagesx;
use function imagesy;
use function round;
use function sprintf;
use function strpos;
use function substr;

use GdImage;
use InvalidArgumentException;

final readonly class WebpImageDecoder
{
    public function decode(string $data, string $path = 'memory'): ImageSource
    {
        $this->guardAgainstAnimatedWebp($data, $path);

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

        return new DecodedRasterImage(
            width: $width,
            height: $height,
            colorSpace: ImageColorSpace::RGB,
            bitsPerComponent: 8,
            pixelData: $rgb,
            alphaData: $hasAlpha ? $alpha : null,
        )->toImageSource($path);
    }

    private function guardAgainstAnimatedWebp(string $data, string $path): void
    {
        if (!str_starts_with($data, 'RIFF') || substr($data, 8, 4) !== 'WEBP') {
            return;
        }

        if (str_contains(substr($data, 12), 'ANIM') || str_contains(substr($data, 12), 'ANMF')) {
            throw new InvalidArgumentException(sprintf(
                "WEBP image '%s' uses animation, which is not supported.",
                $path,
            ));
        }
    }
}
