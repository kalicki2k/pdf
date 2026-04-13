<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Image;

use function base64_decode;
use function substr;

use RuntimeException;

final class GifFixture
{
    public static function tinyOpaqueGifBytes(): string
    {
        return self::decode('R0lGODdhAQABAIAAAP///////ywAAAAAAQABAAACAkQBADs=');
    }

    public static function tinyTransparentGifBytes(): string
    {
        return self::decode('R0lGODlhAQABAPAAAAAAAAAAACH5BAEAAAAALAAAAAABAAEAAAICRAEAOw==');
    }

    public static function tinyAnimatedGifBytes(): string
    {
        $singleFrame = self::tinyOpaqueGifBytes();
        $frame = substr($singleFrame, 19, -1);

        return substr($singleFrame, 0, -1) . $frame . "\x3B";
    }

    public static function tinyInterlacedGifBytes(): string
    {
        $bytes = self::tinyOpaqueGifBytes();

        return substr($bytes, 0, 28) . "\x40" . substr($bytes, 29);
    }

    private static function decode(string $encoded): string
    {
        $bytes = base64_decode($encoded, true);

        if (!is_string($bytes)) {
            throw new RuntimeException('Unable to decode GIF fixture.');
        }

        return $bytes;
    }
}
