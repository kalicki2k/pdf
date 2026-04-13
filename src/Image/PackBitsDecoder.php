<?php

declare(strict_types=1);

namespace Kalle\Pdf\Image;

use function ord;
use function str_repeat;
use function strlen;

use InvalidArgumentException;

final readonly class PackBitsDecoder
{
    public function decode(string $data): string
    {
        $decoded = '';
        $offset = 0;
        $length = strlen($data);

        while ($offset < $length) {
            $header = ord($data[$offset]);
            $offset++;

            if ($header <= 127) {
                $literalLength = $header + 1;

                if ($offset + $literalLength > $length) {
                    throw new InvalidArgumentException('PackBits data ends inside a literal run.');
                }

                $decoded .= substr($data, $offset, $literalLength);
                $offset += $literalLength;

                continue;
            }

            if ($header === 128) {
                continue;
            }

            if ($offset >= $length) {
                throw new InvalidArgumentException('PackBits data ends inside a repeated run.');
            }

            $repeatLength = 257 - $header;
            $decoded .= str_repeat($data[$offset], $repeatLength);
            $offset++;
        }

        return $decoded;
    }
}
