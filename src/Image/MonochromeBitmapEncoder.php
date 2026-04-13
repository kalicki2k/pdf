<?php

declare(strict_types=1);

namespace Kalle\Pdf\Image;

use function chr;
use function count;
use function preg_match;
use function strlen;

use InvalidArgumentException;

final readonly class MonochromeBitmapEncoder
{
    /**
     * Packs rows of `0`/`1` pixels into PDF 1-bit image bytes.
     *
     * Input semantics:
     * - `1` means a black pixel
     * - `0` means a white pixel
     *
     * PDF DeviceGray image samples with the default decode map `0` to black and
     * `1` to white, so the packed bits are inverted during serialization.
     *
     * @param list<string> $rows
     */
    public function encodeRows(array $rows): PackedMonochromeBitmap
    {
        if ($rows === []) {
            throw new InvalidArgumentException('Monochrome bitmap rows must not be empty.');
        }

        $width = strlen($rows[0]);

        if ($width === 0) {
            throw new InvalidArgumentException('Monochrome bitmap rows must not be empty strings.');
        }

        $packed = '';

        foreach ($rows as $rowIndex => $row) {
            if (strlen($row) !== $width) {
                throw new InvalidArgumentException(sprintf(
                    'Monochrome bitmap row %d has width %d; expected %d.',
                    $rowIndex + 1,
                    strlen($row),
                    $width,
                ));
            }

            if (preg_match('/[^01]/', $row) === 1) {
                throw new InvalidArgumentException(sprintf(
                    'Monochrome bitmap row %d may only contain 0 and 1.',
                    $rowIndex + 1,
                ));
            }

            $currentByte = 0;
            $bitIndex = 0;

            for ($column = 0; $column < $width; $column++) {
                // Input `1` means black, but PDF's default DeviceGray decode uses
                // sample value 0 for black and 1 for white.
                $sampleBit = $row[$column] === '1' ? 0 : 1;
                $currentByte |= $sampleBit << (7 - $bitIndex);
                $bitIndex++;

                if ($bitIndex === 8) {
                    $packed .= chr($currentByte);
                    $currentByte = 0;
                    $bitIndex = 0;
                }
            }

            if ($bitIndex !== 0) {
                // Pad incomplete bytes with white pixels.
                while ($bitIndex < 8) {
                    $currentByte |= 1 << (7 - $bitIndex);
                    $bitIndex++;
                }

                $packed .= chr($currentByte);
            }
        }

        return new PackedMonochromeBitmap($width, count($rows), $packed);
    }
}
