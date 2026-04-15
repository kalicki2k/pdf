<?php

declare(strict_types=1);

namespace Kalle\Pdf\Image;

use function chr;
use function ord;
use function strlen;

use InvalidArgumentException;

final readonly class LzwDecoder
{
    private const int CLEAR_TABLE = 256;
    private const int END_OF_DATA = 257;

    public function __construct(
        private int $earlyChange = 1,
    ) {
    }

    public function decode(string $data): string
    {
        $dictionary = $this->initialDictionary();
        $nextCode = self::END_OF_DATA + 1;
        $codeWidth = 9;
        $offset = 0;
        $bitBuffer = 0;
        $bitCount = 0;
        $decoded = '';
        $previous = null;

        $readCode = function () use ($data, &$offset, &$bitBuffer, &$bitCount, &$codeWidth): ?int {
            while ($bitCount < $codeWidth) {
                if ($offset >= strlen($data)) {
                    return null;
                }

                $bitBuffer = ($bitBuffer << 8) | ord($data[$offset]);
                $bitCount += 8;
                $offset++;
            }

            $bitCount -= $codeWidth;
            $code = ($bitBuffer >> $bitCount) & ((1 << $codeWidth) - 1);
            $bitBuffer &= (1 << $bitCount) - 1;

            return $code;
        };

        while (($code = $readCode()) !== null) {
            if ($code === self::CLEAR_TABLE) {
                $dictionary = $this->initialDictionary();
                $nextCode = self::END_OF_DATA + 1;
                $codeWidth = 9;
                $previous = null;

                continue;
            }

            if ($code === self::END_OF_DATA) {
                break;
            }

            if (isset($dictionary[$code])) {
                $entry = $dictionary[$code];
            } elseif ($code === $nextCode && $previous !== null) {
                $entry = $previous . $previous[0];
            } else {
                throw new InvalidArgumentException('LZW data contains an invalid code sequence.');
            }

            $decoded .= $entry;

            if ($previous !== null && $nextCode <= 4095) {
                $dictionary[$nextCode] = $previous . $entry[0];
                $nextCode++;

                if ($nextCode === (1 << $codeWidth) - $this->earlyChange && $codeWidth < 12) {
                    $codeWidth++;
                }
            }

            $previous = $entry;
        }

        return $decoded;
    }

    /**
     * @return array<int, string>
     */
    private function initialDictionary(): array
    {
        $dictionary = [];

        for ($index = 0; $index < 256; $index++) {
            $dictionary[$index] = chr($index);
        }

        return $dictionary;
    }
}
