<?php

declare(strict_types=1);

namespace Kalle\Pdf\Image;

use function chr;
use function ord;
use function strlen;
use function substr;

final readonly class RunLengthEncoder
{
    public function encode(string $data): string
    {
        $length = strlen($data);

        if ($length === 0) {
            return "\x80";
        }

        $encoded = '';
        $offset = 0;

        while ($offset < $length) {
            $runLength = $this->repeatedRunLength($data, $offset, $length);

            if ($runLength >= 3) {
                $encoded .= chr(257 - $runLength) . $data[$offset];
                $offset += $runLength;

                continue;
            }

            $literalStart = $offset;
            $literalLength = 0;

            while ($offset < $length && $literalLength < 128) {
                $runLength = $this->repeatedRunLength($data, $offset, $length);

                if ($runLength >= 3) {
                    break;
                }

                $offset++;
                $literalLength++;
            }

            $encoded .= chr($literalLength - 1) . substr($data, $literalStart, $literalLength);
        }

        return $encoded . "\x80";
    }

    private function repeatedRunLength(string $data, int $offset, int $length): int
    {
        $runLength = 1;
        $byte = ord($data[$offset]);

        while ($offset + $runLength < $length && $runLength < 128 && ord($data[$offset + $runLength]) === $byte) {
            $runLength++;
        }

        return $runLength;
    }
}
