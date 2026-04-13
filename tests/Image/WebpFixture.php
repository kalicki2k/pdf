<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Image;

use function base64_decode;
use function function_exists;
use function imagealphablending;
use function imagecolorallocate;
use function imagecolorallocatealpha;
use function imagecreatetruecolor;
use function imagedestroy;
use function imagefill;
use function imagesavealpha;
use function imagewebp;
use function ob_get_clean;
use function ob_start;
use function pack;
use function strlen;

use GdImage;
use RuntimeException;

final class WebpFixture
{
    public static function tinyWebpBytes(): string
    {
        $bytes = base64_decode('UklGRiIAAABXRUJQVlA4IBYAAAAwAQCdASoBAAEAAUAmJaQAA3AA/v89WAAAAA==', true);

        if (!is_string($bytes)) {
            throw new RuntimeException('Unable to decode WebP fixture.');
        }

        return $bytes;
    }

    public static function tinyOpaqueWebpBytes(): string
    {
        if (!function_exists('imagecreatetruecolor') || !function_exists('imagewebp')) {
            throw new RuntimeException('Unable to create opaque WebP fixture without GD WebP support.');
        }

        $image = imagecreatetruecolor(1, 1);

        if ($image === false) {
            throw new RuntimeException('Unable to allocate opaque WebP fixture image.');
        }

        $blue = imagecolorallocate($image, 0x22, 0x66, 0xCC);
        imagefill($image, 0, 0, $blue);

        return self::encodeGdImageAsWebp($image);
    }

    public static function tinyTransparentWebpBytes(): string
    {
        if (!function_exists('imagecreatetruecolor') || !function_exists('imagewebp')) {
            throw new RuntimeException('Unable to create transparent WebP fixture without GD WebP support.');
        }

        $image = imagecreatetruecolor(1, 1);

        if ($image === false) {
            throw new RuntimeException('Unable to allocate transparent WebP fixture image.');
        }

        imagealphablending($image, false);
        imagesavealpha($image, true);

        $transparentBlue = imagecolorallocatealpha($image, 0x22, 0x66, 0xCC, 63);
        imagefill($image, 0, 0, $transparentBlue);

        return self::encodeGdImageAsWebp($image);
    }

    public static function tinyLosslessWebpBytes(): string
    {
        if (!function_exists('imagecreatetruecolor') || !function_exists('imagewebp') || !\defined('IMG_WEBP_LOSSLESS')) {
            throw new RuntimeException('Unable to create lossless WebP fixture without GD lossless WebP support.');
        }

        $image = imagecreatetruecolor(1, 1);

        if ($image === false) {
            throw new RuntimeException('Unable to allocate lossless WebP fixture image.');
        }

        $green = imagecolorallocate($image, 0x11, 0xAA, 0x44);
        imagefill($image, 0, 0, $green);

        return self::encodeGdImageAsWebp($image, \IMG_WEBP_LOSSLESS);
    }

    public static function tinyAnimatedWebpBytes(): string
    {
        $vp8xPayload = "\x02\x00\x00\x00" . "\x00\x00\x00" . "\x00\x00\x00" . "\x00\x00\x00";
        $animPayload = "\xFF\xFF\xFF\xFF\x00\x00";
        $chunkBytes = 'VP8X' . pack('V', strlen($vp8xPayload)) . $vp8xPayload
            . 'ANIM' . pack('V', strlen($animPayload)) . $animPayload;

        return 'RIFF' . pack('V', 4 + strlen($chunkBytes)) . 'WEBP' . $chunkBytes;
    }

    private static function encodeGdImageAsWebp(GdImage $image, int $quality = -1): string
    {
        ob_start();
        $written = imagewebp($image, null, $quality);
        $bytes = ob_get_clean();
        imagedestroy($image);

        if ($written !== true || !is_string($bytes)) {
            throw new RuntimeException('Unable to encode GD WebP fixture.');
        }

        return $bytes;
    }
}
