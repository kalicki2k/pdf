<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Image;

use function base64_decode;

use RuntimeException;

final class JpegFixture
{
    public static function tinyGrayJpegBytes(): string
    {
        $bytes = base64_decode(
            '/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAAMCAgICAgMCAgIDAwMDBAYEBAQEBAgGBgUGCQgKCgkICQkKDA8MCgsOCwkJDRENDg8QEBEQCgwSExIQEw8QEBD/wAALCAABAAEBAREA/8QAFAABAAAAAAAAAAAAAAAAAAAACf/EABQQAQAAAAAAAAAAAAAAAAAAAAD/2gAIAQEAAD8AVN//2Q==',
            true,
        );

        if (!is_string($bytes)) {
            throw new RuntimeException('Unable to decode JPEG fixture.');
        }

        return $bytes;
    }

    public static function tinyCmykJpegBytes(): string
    {
        $bytes = base64_decode(
            '/9j/7gAOQWRvYmUAZAAAAAAC/9sAQwADAgICAgIDAgICAwMDAwQGBAQEBAQIBgYFBgkICgoJCAkJCgwPDAoLDgsJCQ0RDQ4PEBAREAoMEhMSEBMPEBAQ/9sAQwEDAwMEAwQIBAQIEAsJCxAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQ/8AAFAgAAQABBAERAAIRAQMRAQQRAP/EABUAAQEAAAAAAAAAAAAAAAAAAAgJ/8QAFBABAAAAAAAAAAAAAAAAAAAAAP/EABUBAQEAAAAAAAAAAAAAAAAAAAcJ/8QAFBEBAAAAAAAAAAAAAAAAAAAAAP/aAA4EAQACEQMRBAAAPwBEHNKpVN//2Q==',
            true,
        );

        if (!is_string($bytes)) {
            throw new RuntimeException('Unable to decode JPEG fixture.');
        }

        return $bytes;
    }
}
