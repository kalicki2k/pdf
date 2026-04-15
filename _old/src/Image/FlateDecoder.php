<?php

declare(strict_types=1);

namespace Kalle\Pdf\Image;

use function function_exists;
use function gzinflate;
use function gzuncompress;
use function is_string;
use function strlen;
use function substr;
use function zlib_decode;

use InvalidArgumentException;

final readonly class FlateDecoder
{
    public function decode(string $data): string
    {
        $decoded = gzuncompress($data);

        if (is_string($decoded)) {
            return $decoded;
        }

        if (function_exists('zlib_decode')) {
            $decoded = zlib_decode($data);

            if (is_string($decoded)) {
                return $decoded;
            }
        }

        $decoded = gzinflate($data);

        if (is_string($decoded)) {
            return $decoded;
        }

        if (strlen($data) > 6) {
            $decoded = gzinflate(substr($data, 2, -4));

            if (is_string($decoded)) {
                return $decoded;
            }
        }

        throw new InvalidArgumentException('Flate data could not be decompressed.');
    }
}
