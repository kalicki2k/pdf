<?php

declare(strict_types=1);

namespace Kalle\Pdf\Image;

use function chr;
use function strlen;

final readonly class LzwEncoder
{
    private const int CLEAR_TABLE = 256;
    private const int END_OF_DATA = 257;
    private const int MAX_CODE = 4095;

    public function __construct(
        private int $earlyChange = 1,
    ) {
    }

    public function encode(string $data): string
    {
        $dictionary = $this->initialDictionary();
        $nextCode = self::END_OF_DATA + 1;
        $codeWidth = 9;
        $bitBuffer = 0;
        $bitCount = 0;
        $encoded = '';

        $writeCode = function (int $code) use (&$bitBuffer, &$bitCount, &$encoded, &$codeWidth): void {
            $bitBuffer = ($bitBuffer << $codeWidth) | $code;
            $bitCount += $codeWidth;

            while ($bitCount >= 8) {
                $bitCount -= 8;
                $encoded .= chr(($bitBuffer >> $bitCount) & 0xFF);
                $bitBuffer &= (1 << $bitCount) - 1;
            }
        };

        $writeCode(self::CLEAR_TABLE);

        if ($data === '') {
            $writeCode(self::END_OF_DATA);

            if ($bitCount > 0) {
                $encoded .= chr($bitBuffer << (8 - $bitCount));
            }

            return $encoded;
        }

        $phrase = $data[0];
        $length = strlen($data);

        for ($offset = 1; $offset < $length; $offset++) {
            $character = $data[$offset];
            $candidate = $phrase . $character;

            if (isset($dictionary[$candidate])) {
                $phrase = $candidate;

                continue;
            }

            $writeCode($dictionary[$phrase]);

            if ($nextCode <= self::MAX_CODE) {
                $dictionary[$candidate] = $nextCode;
                $nextCode++;

                if ($nextCode === (1 << $codeWidth) - $this->earlyChange && $codeWidth < 12) {
                    $codeWidth++;
                }
            } else {
                $writeCode(self::CLEAR_TABLE);
                $dictionary = $this->initialDictionary();
                $nextCode = self::END_OF_DATA + 1;
                $codeWidth = 9;
            }

            $phrase = $character;
        }

        $writeCode($dictionary[$phrase]);
        $writeCode(self::END_OF_DATA);

        if ($bitCount > 0) {
            $encoded .= chr($bitBuffer << (8 - $bitCount));
        }

        return $encoded;
    }

    /**
     * @return array<string, int>
     */
    private function initialDictionary(): array
    {
        $dictionary = [];

        for ($index = 0; $index < 256; $index++) {
            $dictionary[chr($index)] = $index;
        }

        return $dictionary;
    }
}
