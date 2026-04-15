<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Image;

use function base64_decode;

use RuntimeException;

final class PngFixture
{
    public static function tinyRgbPngBytes(): string
    {
        return self::decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAAIGNIUk0AAHomAACAhAAA+gAAAIDoAAB1MAAA6mAAADqYAAAXcJy6UTwAAAAGYktHRAD/AP8A/6C9p5MAAAAHdElNRQfqBAwGBBnA15pKAAAADElEQVQI12P4z8AAAAMBAQAY3Y2wAAAAJXRFWHRkYXRlOmNyZWF0ZQAyMDI2LTA0LTEyVDA2OjA0OjI1KzAwOjAwLhCIzQAAACV0RVh0ZGF0ZTptb2RpZnkAMjAyNi0wNC0xMlQwNjowNDoyNSswMDowMF9NMHEAAAAodEVYdGRhdGU6dGltZXN0YW1wADIwMjYtMDQtMTJUMDY6MDQ6MjUrMDA6MDAIWBGuAAAAAElFTkSuQmCC',
        );
    }

    public static function tinyRgbaPngBytes(): string
    {
        return self::decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAAIGNIUk0AAHomAACAhAAA+gAAAIDoAAB1MAAA6mAAADqYAAAXcJy6UTwAAAAGYktHRAD/AP8A/6C9p5MAAAANSURBVAjXY/jPwPAfAAUAAf9ynFJnAAAAJXRFWHRkYXRlOmNyZWF0ZQAyMDI2LTA0LTEyVDA2OjA0OjI1KzAwOjAwLhCIzQAAACV0RVh0ZGF0ZTptb2RpZnkAMjAyNi0wNC0xMlQwNjowNDoyNSswMDowMF9NMHEAAAAodEVYdGRhdGU6dGltZXN0YW1wADIwMjYtMDQtMTJUMDY6MDQ6MjUrMDA6MDAIWBGuAAAAAElFTkSuQmCC',
        );
    }

    public static function tinyIndexedPngBytes(): string
    {
        return self::decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAMAAAAoyzS7AAAAA1BMVEWAgICQdD0xAAAAB3RJTUUH6gQMBgQZwNeaSgAAAApJREFUCNdjYAAAAAIAAeIhvDMAAAAldEVYdGRhdGU6Y3JlYXRlADIwMjYtMDQtMTJUMDY6MDQ6MjUrMDA6MDAuEIjNAAAAJXRFWHRkYXRlOm1vZGlmeQAyMDI2LTA0LTEyVDA2OjA0OjI1KzAwOjAwX00wcQAAACh0RVh0ZGF0ZTp0aW1lc3RhbXAAMjAyNi0wNC0xMlQwNjowNDoyNSswMDowMAhYEa4AAAAASUVORK5CYII=',
        );
    }

    public static function tinyIndexedTransparentPngBytes(): string
    {
        return self::decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAMAAAAoyzS7AAAAIGNIUk0AAHomAACAhAAA+gAAAIDoAAB1MAAA6mAAADqYAAAXcJy6UTwAAAADUExURQAAAKd6PdoAAAABdFJOUwBA5thmAAAAB3RJTUUH6gQMBgovke4iXQAAAApJREFUCNdjYAAAAAIAAeIhvDMAAAAldEVYdGRhdGU6Y3JlYXRlADIwMjYtMDQtMTJUMDY6MTA6NDcrMDA6MDC3he/ZAAAAJXRFWHRkYXRlOm1vZGlmeQAyMDI2LTA0LTEyVDA2OjEwOjQ3KzAwOjAwxthXZQAAACh0RVh0ZGF0ZTp0aW1lc3RhbXAAMjAyNi0wNC0xMlQwNjoxMDo0NyswMDowMJHNdroAAAAASUVORK5CYII=',
        );
    }

    private static function decode(string $encoded): string
    {
        $bytes = base64_decode($encoded, true);

        if (!is_string($bytes)) {
            throw new RuntimeException('Unable to decode PNG fixture.');
        }

        return $bytes;
    }
}
