<?php

declare(strict_types=1);

namespace Kalle\Pdf\Image;

use function chr;
use function intdiv;
use function strlen;

use InvalidArgumentException;

final readonly class CcittFaxEncoder
{
    /**
     * @var array<int, string>
     */
    private const array WHITE_TERMINATING = [
        0 => '00110101', 1 => '000111', 2 => '0111', 3 => '1000', 4 => '1011', 5 => '1100', 6 => '1110', 7 => '1111',
        8 => '10011', 9 => '10100', 10 => '00111', 11 => '01000', 12 => '001000', 13 => '000011', 14 => '110100', 15 => '110101',
        16 => '101010', 17 => '101011', 18 => '0100111', 19 => '0001100', 20 => '0001000', 21 => '0010111', 22 => '0000011', 23 => '0000100',
        24 => '0101000', 25 => '0101011', 26 => '0010011', 27 => '0100100', 28 => '0011000', 29 => '00000010', 30 => '00000011', 31 => '00011010',
        32 => '00011011', 33 => '00010010', 34 => '00010011', 35 => '00010100', 36 => '00010101', 37 => '00010110', 38 => '00010111', 39 => '00101000',
        40 => '00101001', 41 => '00101010', 42 => '00101011', 43 => '00101100', 44 => '00101101', 45 => '00000100', 46 => '00000101', 47 => '00001010',
        48 => '00001011', 49 => '01010010', 50 => '01010011', 51 => '01010100', 52 => '01010101', 53 => '00100100', 54 => '00100101', 55 => '01011000',
        56 => '01011001', 57 => '01011010', 58 => '01011011', 59 => '01001010', 60 => '01001011', 61 => '00110010', 62 => '00110011', 63 => '00110100',
    ];

    /**
     * @var array<int, string>
     */
    private const array WHITE_MAKEUP = [
        64 => '11011', 128 => '10010', 192 => '010111', 256 => '0110111', 320 => '00110110', 384 => '00110111',
        448 => '01100100', 512 => '01100101', 576 => '01101000', 640 => '01100111', 704 => '011001100', 768 => '011001101',
        832 => '011010010', 896 => '011010011', 960 => '011010100', 1024 => '011010101', 1088 => '011010110', 1152 => '011010111',
        1216 => '011011000', 1280 => '011011001', 1344 => '011011010', 1408 => '011011011', 1472 => '010011000', 1536 => '010011001',
        1600 => '010011010', 1664 => '011000', 1728 => '010011011',
    ];

    /**
     * @var array<int, string>
     */
    private const array BLACK_TERMINATING = [
        0 => '0000110111', 1 => '010', 2 => '11', 3 => '10', 4 => '011', 5 => '0011', 6 => '0010', 7 => '00011',
        8 => '000101', 9 => '000100', 10 => '0000100', 11 => '0000101', 12 => '0000111', 13 => '00000100', 14 => '00000111', 15 => '000011000',
        16 => '0000010111', 17 => '0000011000', 18 => '0000001000', 19 => '00001100111', 20 => '00001101000', 21 => '00001101100',
        22 => '00000110111', 23 => '00000101000', 24 => '00000010111', 25 => '00000011000', 26 => '000011001010', 27 => '000011001011',
        28 => '000011001100', 29 => '000011001101', 30 => '000001101000', 31 => '000001101001', 32 => '000001101010', 33 => '000001101011',
        34 => '000011010010', 35 => '000011010011', 36 => '000011010100', 37 => '000011010101', 38 => '000011010110', 39 => '000011010111',
        40 => '000001101100', 41 => '000001101101', 42 => '000011011010', 43 => '000011011011', 44 => '000001010100', 45 => '000001010101',
        46 => '000001010110', 47 => '000001010111', 48 => '000001100100', 49 => '000001100101', 50 => '000001010010', 51 => '000001010011',
        52 => '000000100100', 53 => '000000110111', 54 => '000000111000', 55 => '000000100111', 56 => '000000101000', 57 => '000001011000',
        58 => '000001011001', 59 => '000000101011', 60 => '000000101100', 61 => '000001011010', 62 => '000001100110', 63 => '000001100111',
    ];

    /**
     * @var array<int, string>
     */
    private const array BLACK_MAKEUP = [
        64 => '0000001111', 128 => '000011001000', 192 => '000011001001', 256 => '000001011011', 320 => '000000110011',
        384 => '000000110100', 448 => '000000110101', 512 => '0000001101100', 576 => '0000001101101', 640 => '0000001001010',
        704 => '0000001001011', 768 => '0000001001100', 832 => '0000001001101', 896 => '0000001110010', 960 => '0000001110011',
        1024 => '0000001110100', 1088 => '0000001110101', 1152 => '0000001110110', 1216 => '0000001110111', 1280 => '0000001010010',
        1344 => '0000001010011', 1408 => '0000001010100', 1472 => '0000001010101', 1536 => '0000001011010', 1600 => '0000001011011',
        1664 => '0000001100100', 1728 => '0000001100101',
    ];

    /**
     * @var array<int, string>
     */
    private const array COMMON_MAKEUP = [
        1792 => '00000001000', 1856 => '00000001100', 1920 => '00000001101', 1984 => '000000010010', 2048 => '000000010011',
        2112 => '000000010100', 2176 => '000000010101', 2240 => '000000010110', 2304 => '000000010111', 2368 => '000000011100',
        2432 => '000000011101', 2496 => '000000011110', 2560 => '000000011111',
    ];

    private const string EOL = '000000000001';

    public function encodeBitmap(string $bitmap, int $width, int $height): string
    {
        if ($width <= 0 || $height <= 0) {
            throw new InvalidArgumentException('CCITT bitmap dimensions must be greater than 0.');
        }

        $rowByteLength = intdiv($width + 7, 8);

        if (strlen($bitmap) !== $rowByteLength * $height) {
            throw new InvalidArgumentException('CCITT bitmap size does not match width and height.');
        }

        $writer = new CcittBitWriter();

        for ($rowIndex = 0; $rowIndex < $height; $rowIndex++) {
            $writer->writeBits(self::EOL);
            $this->encodeRow($writer, $bitmap, $rowIndex * $rowByteLength, $width);
        }

        for ($index = 0; $index < 6; $index++) {
            $writer->writeBits(self::EOL);
        }

        return $writer->bytes();
    }

    /**
     * @param list<string> $rows
     */
    public function encodeRows(array $rows): string
    {
        $bitmap = (new MonochromeBitmapEncoder())->encodeRows($rows);

        return $this->encodeBitmap($bitmap->data, $bitmap->width, $bitmap->height);
    }

    private function encodeRow(CcittBitWriter $writer, string $bitmap, int $rowOffset, int $width): void
    {
        $color = 0;
        $runLength = 0;

        for ($column = 0; $column < $width; $column++) {
            $bit = $this->bitmapBit($bitmap, $rowOffset, $column);

            if ($bit === $color) {
                $runLength++;

                continue;
            }

            $writer->writeBits($this->codeForRunLength($runLength, $color === 0));
            $color = $bit;
            $runLength = 1;
        }

        $writer->writeBits($this->codeForRunLength($runLength, $color === 0));
    }

    private function bitmapBit(string $bitmap, int $rowOffset, int $column): int
    {
        $byte = ord($bitmap[$rowOffset + intdiv($column, 8)]);
        $sample = ($byte >> (7 - ($column % 8))) & 1;

        // Packed bitmap uses PDF DeviceGray sample semantics: 0 => black, 1 => white.
        return $sample === 0 ? 1 : 0;
    }

    private function codeForRunLength(int $runLength, bool $white): string
    {
        $code = '';
        $makeup = $white ? self::WHITE_MAKEUP : self::BLACK_MAKEUP;
        $terminating = $white ? self::WHITE_TERMINATING : self::BLACK_TERMINATING;

        while ($runLength >= 2560) {
            $code .= self::COMMON_MAKEUP[2560];
            $runLength -= 2560;
        }

        while ($runLength >= 1792) {
            $matched = false;

            foreach (self::COMMON_MAKEUP as $length => $bits) {
                if ($length > $runLength) {
                    continue;
                }

                $code .= $bits;
                $runLength -= $length;
                $matched = true;

                break;
            }

            if (!$matched) {
                break;
            }
        }

        while ($runLength >= 64) {
            $matched = false;

            foreach ($makeup as $length => $bits) {
                if ($length > $runLength) {
                    continue;
                }

                $code .= $bits;
                $runLength -= $length;
                $matched = true;

                break;
            }

            if (!$matched) {
                break;
            }
        }

        return $code . $terminating[$runLength];
    }
}

final class CcittBitWriter
{
    private int $buffer = 0;
    private int $bitCount = 0;
    private string $bytes = '';

    public function writeBits(string $bits): void
    {
        $length = strlen($bits);

        for ($index = 0; $index < $length; $index++) {
            $this->buffer = ($this->buffer << 1) | ($bits[$index] === '1' ? 1 : 0);
            $this->bitCount++;

            if ($this->bitCount === 8) {
                $this->bytes .= chr($this->buffer & 0xFF);
                $this->buffer = 0;
                $this->bitCount = 0;
            }
        }
    }

    public function bytes(): string
    {
        if ($this->bitCount === 0) {
            return $this->bytes;
        }

        return $this->bytes . chr(($this->buffer << (8 - $this->bitCount)) & 0xFF);
    }
}
