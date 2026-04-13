<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Image;

use function base64_decode;

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
}
