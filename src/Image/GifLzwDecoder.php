<?php

declare(strict_types=1);

namespace Kalle\Pdf\Image;

use function ord;
use function sprintf;
use function strlen;

use InvalidArgumentException;

final readonly class GifLzwDecoder
{
    public function decode(string $data, int $minimumCodeSize, string $path = 'memory'): string
    {
        if ($minimumCodeSize < 2 || $minimumCodeSize > 8) {
            throw new InvalidArgumentException(sprintf(
                "GIF image '%s' uses unsupported LZW minimum code size %d.",
                $path,
                $minimumCodeSize,
            ));
        }

        $clearCode = 1 << $minimumCodeSize;
        $endCode = $clearCode + 1;
        $codeSize = $minimumCodeSize + 1;
        $nextCode = $endCode + 1;
        $bitOffset = 0;
        $previous = null;
        $output = '';
        $dictionary = $this->initialDictionary($clearCode);

        while (($code = $this->readCode($data, $bitOffset, $codeSize)) !== null) {
            $bitOffset += $codeSize;

            if ($code === $clearCode) {
                $dictionary = $this->initialDictionary($clearCode);
                $codeSize = $minimumCodeSize + 1;
                $nextCode = $endCode + 1;
                $previous = null;

                continue;
            }

            if ($code === $endCode) {
                break;
            }

            if (isset($dictionary[$code])) {
                $entry = $dictionary[$code];
            } elseif ($code === $nextCode && $previous !== null) {
                $entry = $previous . $previous[0];
            } else {
                throw new InvalidArgumentException(sprintf(
                    "GIF image '%s' contains invalid LZW image data.",
                    $path,
                ));
            }

            $output .= $entry;

            if ($previous !== null && $nextCode < 4096) {
                $dictionary[$nextCode] = $previous . $entry[0];
                $nextCode++;

                if ($nextCode === (1 << $codeSize) && $codeSize < 12) {
                    $codeSize++;
                }
            }

            $previous = $entry;
        }

        return $output;
    }

    /**
     * @return array<int, string>
     */
    private function initialDictionary(int $clearCode): array
    {
        $dictionary = [];

        for ($index = 0; $index < $clearCode; $index++) {
            $dictionary[$index] = chr($index & 0xFF);
        }

        return $dictionary;
    }

    private function readCode(string $data, int $bitOffset, int $codeSize): ?int
    {
        $value = 0;

        for ($bitIndex = 0; $bitIndex < $codeSize; $bitIndex++) {
            $absoluteBit = $bitOffset + $bitIndex;
            $byteOffset = intdiv($absoluteBit, 8);

            if ($byteOffset >= strlen($data)) {
                return null;
            }

            $bitInByte = $absoluteBit % 8;
            $bit = (ord($data[$byteOffset]) >> $bitInByte) & 0x01;
            $value |= $bit << $bitIndex;
        }

        return $value;
    }
}
