<?php

declare(strict_types=1);

namespace Kalle\Pdf\Image;

use function ord;
use function sprintf;
use function strlen;
use function substr;

use InvalidArgumentException;

final readonly class GifImageDecoder
{
    private const string SIGNATURE_87A = 'GIF87a';
    private const string SIGNATURE_89A = 'GIF89a';

    public function __construct(
        private GifLzwDecoder $lzwDecoder = new GifLzwDecoder(),
    ) {
    }

    public function decode(string $data, string $path = 'memory'): ImageSource
    {
        $signature = substr($data, 0, 6);

        if ($signature !== self::SIGNATURE_87A && $signature !== self::SIGNATURE_89A) {
            throw new InvalidArgumentException(sprintf(
                "GIF image '%s' has an invalid signature.",
                $path,
            ));
        }

        if (strlen($data) < 13) {
            throw new InvalidArgumentException(sprintf(
                "GIF image '%s' is truncated before the logical screen descriptor.",
                $path,
            ));
        }

        $screenWidth = $this->readUint16(substr($data, 6, 2));
        $screenHeight = $this->readUint16(substr($data, 8, 2));
        $packedFields = ord($data[10]);
        $offset = 13;
        $globalColorTable = null;

        if (($packedFields & 0x80) !== 0) {
            $globalColorTableLength = 3 * (1 << (($packedFields & 0x07) + 1));
            $globalColorTable = substr($data, $offset, $globalColorTableLength);

            if (strlen($globalColorTable) !== $globalColorTableLength) {
                throw new InvalidArgumentException(sprintf(
                    "GIF image '%s' has a truncated global color table.",
                    $path,
                ));
            }

            $offset += $globalColorTableLength;
        }

        $imageDescriptor = null;
        $transparencyIndex = null;

        while ($offset < strlen($data)) {
            $blockType = ord($data[$offset]);
            $offset++;

            if ($blockType === 0x3B) {
                break;
            }

            if ($blockType === 0x21) {
                $offset = $this->readExtension($data, $offset, $path, $transparencyIndex);

                continue;
            }

            if ($blockType === 0x2C) {
                if ($imageDescriptor !== null) {
                    throw new InvalidArgumentException(sprintf(
                        "GIF image '%s' uses multiple image frames, which are not supported.",
                        $path,
                    ));
                }

                $imageDescriptor = $this->readImageDescriptor(
                    $data,
                    $offset,
                    $screenWidth,
                    $screenHeight,
                    $globalColorTable,
                    $transparencyIndex,
                    $path,
                );
                $offset = $imageDescriptor['nextOffset'];

                continue;
            }

            throw new InvalidArgumentException(sprintf(
                "GIF image '%s' contains an unsupported block type 0x%02X.",
                $path,
                $blockType,
            ));
        }

        if ($imageDescriptor === null) {
            throw new InvalidArgumentException(sprintf(
                "GIF image '%s' is missing image data.",
                $path,
            ));
        }

        return new DecodedRasterImage(
            width: $screenWidth,
            height: $screenHeight,
            colorSpace: ImageColorSpace::RGB,
            bitsPerComponent: 8,
            pixelData: $imageDescriptor['indexBytes'],
            alphaData: $imageDescriptor['alphaBytes'],
            lookupTable: $imageDescriptor['palette'],
        )->toImageSource($path);
    }

    private function readExtension(string $data, int $offset, string $path, ?int &$transparencyIndex): int
    {
        $label = ord(($data[$offset] ?? "\0")[0]);
        $offset++;

        if ($label === 0xF9) {
            $blockSize = ord(($data[$offset] ?? "\0")[0]);
            $offset++;

            if ($blockSize !== 4 || strlen(substr($data, $offset, 4)) !== 4) {
                throw new InvalidArgumentException(sprintf(
                    "GIF image '%s' has an invalid graphic control extension.",
                    $path,
                ));
            }

            $packed = ord($data[$offset]);
            $transparencyIndex = ($packed & 0x01) !== 0 ? ord($data[$offset + 3]) : null;
            $offset += 4;

            if (($data[$offset] ?? null) !== "\x00") {
                throw new InvalidArgumentException(sprintf(
                    "GIF image '%s' has an unterminated graphic control extension.",
                    $path,
                ));
            }

            return $offset + 1;
        }

        return $this->skipSubBlocks($data, $offset, $path);
    }

    /**
     * @return array{
     *   palette: string,
     *   indexBytes: string,
     *   alphaBytes: ?string,
     *   nextOffset: int
     * }
     */
    private function readImageDescriptor(
        string $data,
        int $offset,
        int $screenWidth,
        int $screenHeight,
        ?string $globalColorTable,
        ?int $transparencyIndex,
        string $path,
    ): array {
        $descriptor = substr($data, $offset, 9);

        if (strlen($descriptor) !== 9) {
            throw new InvalidArgumentException(sprintf(
                "GIF image '%s' has a truncated image descriptor.",
                $path,
            ));
        }

        $left = $this->readUint16(substr($descriptor, 0, 2));
        $top = $this->readUint16(substr($descriptor, 2, 2));
        $width = $this->readUint16(substr($descriptor, 4, 2));
        $height = $this->readUint16(substr($descriptor, 6, 2));
        $packed = ord($descriptor[8]);
        $offset += 9;

        if ($left !== 0 || $top !== 0 || $width !== $screenWidth || $height !== $screenHeight) {
            throw new InvalidArgumentException(sprintf(
                "GIF image '%s' uses a partial image frame, which is not supported.",
                $path,
            ));
        }

        if (($packed & 0x40) !== 0) {
            throw new InvalidArgumentException(sprintf(
                "GIF image '%s' uses interlacing, which is not supported.",
                $path,
            ));
        }

        $palette = $globalColorTable;

        if (($packed & 0x80) !== 0) {
            $localColorTableLength = 3 * (1 << (($packed & 0x07) + 1));
            $palette = substr($data, $offset, $localColorTableLength);

            if (strlen($palette) !== $localColorTableLength) {
                throw new InvalidArgumentException(sprintf(
                    "GIF image '%s' has a truncated local color table.",
                    $path,
                ));
            }

            $offset += $localColorTableLength;
        }

        if ($palette === null || $palette === '') {
            throw new InvalidArgumentException(sprintf(
                "GIF image '%s' is missing a color table.",
                $path,
            ));
        }

        $minimumCodeSize = ord(($data[$offset] ?? "\0")[0]);
        $offset++;
        ['payload' => $imageData, 'nextOffset' => $offset] = $this->readSubBlocks($data, $offset, $path);
        $indexBytes = $this->lzwDecoder->decode($imageData, $minimumCodeSize, $path);
        $expectedLength = $width * $height;

        if (strlen($indexBytes) < $expectedLength) {
            throw new InvalidArgumentException(sprintf(
                "GIF image '%s' ended unexpectedly while decoding image data.",
                $path,
            ));
        }

        if (strlen($indexBytes) > $expectedLength) {
            $indexBytes = substr($indexBytes, 0, $expectedLength);
        }

        $paletteEntries = intdiv(strlen($palette), 3);
        $alphaBytes = $transparencyIndex !== null ? '' : null;

        for ($index = 0; $index < $expectedLength; $index++) {
            $paletteIndex = ord($indexBytes[$index]);

            if ($paletteIndex >= $paletteEntries) {
                throw new InvalidArgumentException(sprintf(
                    "GIF image '%s' references palette index %d outside the available color table.",
                    $path,
                    $paletteIndex,
                ));
            }

            if ($alphaBytes !== null) {
                $alphaBytes .= $paletteIndex === $transparencyIndex ? "\x00" : "\xFF";
            }
        }

        return [
            'palette' => $palette,
            'indexBytes' => $indexBytes,
            'alphaBytes' => $alphaBytes,
            'nextOffset' => $offset,
        ];
    }

    /**
     * @return array{payload: string, nextOffset: int}
     */
    private function readSubBlocks(string $data, int $offset, string $path): array
    {
        $payload = '';

        while (true) {
            $blockLength = ord(($data[$offset] ?? "\0")[0]);
            $offset++;

            if ($blockLength === 0) {
                return [
                    'payload' => $payload,
                    'nextOffset' => $offset,
                ];
            }

            $block = substr($data, $offset, $blockLength);

            if (strlen($block) !== $blockLength) {
                throw new InvalidArgumentException(sprintf(
                    "GIF image '%s' contains truncated image sub-blocks.",
                    $path,
                ));
            }

            $payload .= $block;
            $offset += $blockLength;
        }
    }

    private function skipSubBlocks(string $data, int $offset, string $path): int
    {
        return $this->readSubBlocks($data, $offset, $path)['nextOffset'];
    }

    private function readUint16(string $bytes): int
    {
        $value = unpack('vvalue', $bytes);

        return is_array($value) && isset($value['value']) && is_int($value['value'])
            ? $value['value']
            : 0;
    }
}
