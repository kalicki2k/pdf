<?php

declare(strict_types=1);

namespace Kalle\Pdf\Image;

use function count;
use function strlen;
use function substr;
use function unpack;

use InvalidArgumentException;

final readonly class TiffImageDecoder
{
    private const string LITTLE_ENDIAN = 'II';
    private const string BIG_ENDIAN = 'MM';

    private const int TYPE_SHORT = 3;
    private const int TYPE_LONG = 4;

    private const int TAG_IMAGE_WIDTH = 256;
    private const int TAG_IMAGE_LENGTH = 257;
    private const int TAG_BITS_PER_SAMPLE = 258;
    private const int TAG_COMPRESSION = 259;
    private const int TAG_PHOTOMETRIC_INTERPRETATION = 262;
    private const int TAG_PLANAR_CONFIGURATION = 284;
    private const int TAG_PREDICTOR = 317;
    private const int TAG_STRIP_OFFSETS = 273;
    private const int TAG_SAMPLES_PER_PIXEL = 277;
    private const int TAG_ROWS_PER_STRIP = 278;
    private const int TAG_STRIP_BYTE_COUNTS = 279;
    private const int COMPRESSION_NONE = 1;
    private const int COMPRESSION_CCITT_GROUP_3 = 3;
    private const int COMPRESSION_CCITT_GROUP_4 = 4;
    private const int COMPRESSION_LZW = 5;
    private const int COMPRESSION_PACKBITS = 32773;

    public function decode(string $data, string $path = 'memory'): ImageSource
    {
        $byteOrder = substr($data, 0, 2);

        if ($byteOrder !== self::LITTLE_ENDIAN && $byteOrder !== self::BIG_ENDIAN) {
            throw new InvalidArgumentException(sprintf(
                "TIFF image '%s' has an unsupported byte order.",
                $path,
            ));
        }

        if ($this->readUint16(substr($data, 2, 2), $byteOrder) !== 42) {
            throw new InvalidArgumentException(sprintf(
                "TIFF image '%s' has an invalid TIFF signature.",
                $path,
            ));
        }

        $ifdOffset = $this->readUint32(substr($data, 4, 4), $byteOrder);
        $entryCount = $this->readUint16(substr($data, $ifdOffset, 2), $byteOrder);
        $entriesOffset = $ifdOffset + 2;
        $entries = [];

        for ($index = 0; $index < $entryCount; $index++) {
            $entryOffset = $entriesOffset + ($index * 12);
            $tag = $this->readUint16(substr($data, $entryOffset, 2), $byteOrder);
            $type = $this->readUint16(substr($data, $entryOffset + 2, 2), $byteOrder);
            $count = $this->readUint32(substr($data, $entryOffset + 4, 4), $byteOrder);
            $valueOffset = substr($data, $entryOffset + 8, 4);
            $entries[$tag] = $this->readEntryValue($data, $byteOrder, $type, $count, $valueOffset, $path);
        }

        $width = $this->requiredSingleInt($entries, self::TAG_IMAGE_WIDTH, $path);
        $height = $this->requiredSingleInt($entries, self::TAG_IMAGE_LENGTH, $path);
        $bitsPerSample = $this->requiredIntList($entries, self::TAG_BITS_PER_SAMPLE, $path);
        $compression = $this->requiredSingleInt($entries, self::TAG_COMPRESSION, $path);
        $photometricInterpretation = $this->requiredSingleInt($entries, self::TAG_PHOTOMETRIC_INTERPRETATION, $path);
        $stripOffsets = $this->requiredIntList($entries, self::TAG_STRIP_OFFSETS, $path);
        $stripByteCounts = $this->requiredIntList($entries, self::TAG_STRIP_BYTE_COUNTS, $path);
        $rowsPerStrip = $this->requiredSingleInt($entries, self::TAG_ROWS_PER_STRIP, $path);
        $samplesPerPixel = $this->optionalSingleInt($entries, self::TAG_SAMPLES_PER_PIXEL) ?? 1;
        $planarConfiguration = $this->optionalSingleInt($entries, self::TAG_PLANAR_CONFIGURATION) ?? 1;
        $predictor = $this->optionalSingleInt($entries, self::TAG_PREDICTOR) ?? 1;
        $nextIfdOffset = $this->readUint32(substr($data, $entriesOffset + ($entryCount * 12), 4), $byteOrder);

        if ($nextIfdOffset !== 0) {
            throw new InvalidArgumentException(sprintf(
                "TIFF image '%s' uses multiple image directories, which are not supported.",
                $path,
            ));
        }

        if (count($stripOffsets) !== count($stripByteCounts)) {
            throw new InvalidArgumentException(sprintf(
                "TIFF image '%s' has mismatching strip offset and byte-count tables.",
                $path,
            ));
        }

        if ($compression === self::COMPRESSION_CCITT_GROUP_3 || $compression === self::COMPRESSION_CCITT_GROUP_4) {
            return $this->decodeCcittBilevel(
                $data,
                $path,
                $width,
                $height,
                $photometricInterpretation,
                $stripOffsets,
                $stripByteCounts,
                $rowsPerStrip,
                $compression === self::COMPRESSION_CCITT_GROUP_3 ? 0 : -1,
                $bitsPerSample,
                $samplesPerPixel,
            );
        }

        if ($compression === self::COMPRESSION_NONE) {
            return $this->decodeUncompressed(
                $data,
                $path,
                $width,
                $height,
                $photometricInterpretation,
                $stripOffsets,
                $stripByteCounts,
                $rowsPerStrip,
                $bitsPerSample,
                $samplesPerPixel,
                $planarConfiguration,
            );
        }

        if ($compression === self::COMPRESSION_PACKBITS || $compression === self::COMPRESSION_LZW) {
            return $this->decodeCompressedRaster(
                $data,
                $path,
                $width,
                $height,
                $photometricInterpretation,
                $stripOffsets,
                $stripByteCounts,
                $rowsPerStrip,
                $bitsPerSample,
                $samplesPerPixel,
                $planarConfiguration,
                $predictor,
                $compression === self::COMPRESSION_PACKBITS
                    ? static fn (string $strip): string => (new PackBitsDecoder())->decode($strip)
                    : static fn (string $strip): string => (new LzwDecoder())->decode($strip),
            );
        }

        throw new InvalidArgumentException(sprintf(
            "TIFF image '%s' uses unsupported TIFF compression %d.",
            $path,
            $compression,
        ));
    }

    /**
     * @param list<int> $stripOffsets
     * @param list<int> $stripByteCounts
     * @param list<int> $bitsPerSample
     * @param callable(string): string $decodeStrip
     */
    private function decodeCompressedRaster(
        string $data,
        string $path,
        int $width,
        int $height,
        int $photometricInterpretation,
        array $stripOffsets,
        array $stripByteCounts,
        int $rowsPerStrip,
        array $bitsPerSample,
        int $samplesPerPixel,
        int $planarConfiguration,
        int $predictor,
        callable $decodeStrip,
    ): ImageSource {
        if ($planarConfiguration !== 1) {
            throw new InvalidArgumentException(sprintf(
                "TIFF image '%s' uses unsupported PlanarConfiguration %d.",
                $path,
                $planarConfiguration,
            ));
        }

        if ($predictor !== 1) {
            throw new InvalidArgumentException(sprintf(
                "TIFF image '%s' uses unsupported TIFF predictor %d.",
                $path,
                $predictor,
            ));
        }

        $decompressedStrips = $this->collectDecodedStripData(
            $data,
            $path,
            $stripOffsets,
            $stripByteCounts,
            $decodeStrip,
        );

        if ($samplesPerPixel === 1 && $bitsPerSample === [1]) {
            return $this->decodeRasterBilevel(
                $path,
                $width,
                $height,
                $photometricInterpretation,
                $decompressedStrips,
                $rowsPerStrip,
            );
        }

        if ($samplesPerPixel === 1 && $bitsPerSample === [8]) {
            return $this->decodeRasterGrayscale(
                $path,
                $width,
                $height,
                $photometricInterpretation,
                $decompressedStrips,
                $rowsPerStrip,
            );
        }

        if ($samplesPerPixel === 3 && $bitsPerSample === [8, 8, 8]) {
            return $this->decodeRasterRgb(
                $path,
                $width,
                $height,
                $photometricInterpretation,
                $decompressedStrips,
                $rowsPerStrip,
            );
        }

        throw new InvalidArgumentException(sprintf(
            "TIFF image '%s' uses unsupported BitsPerSample/SamplesPerPixel combination.",
            $path,
        ));
    }

    /**
     * @param list<int> $stripOffsets
     * @param list<int> $stripByteCounts
     * @param list<int> $bitsPerSample
     */
    private function decodeUncompressed(
        string $data,
        string $path,
        int $width,
        int $height,
        int $photometricInterpretation,
        array $stripOffsets,
        array $stripByteCounts,
        int $rowsPerStrip,
        array $bitsPerSample,
        int $samplesPerPixel,
        int $planarConfiguration,
    ): ImageSource {
        if ($planarConfiguration !== 1) {
            throw new InvalidArgumentException(sprintf(
                "TIFF image '%s' uses unsupported PlanarConfiguration %d.",
                $path,
                $planarConfiguration,
            ));
        }

        if ($samplesPerPixel === 1 && $bitsPerSample === [1]) {
            return $this->decodeUncompressedBilevel(
                $data,
                $path,
                $width,
                $height,
                $photometricInterpretation,
                $stripOffsets,
                $stripByteCounts,
                $rowsPerStrip,
            );
        }

        if ($samplesPerPixel === 1 && $bitsPerSample === [8]) {
            return $this->decodeUncompressedGrayscale(
                $data,
                $path,
                $width,
                $height,
                $photometricInterpretation,
                $stripOffsets,
                $stripByteCounts,
                $rowsPerStrip,
            );
        }

        if ($samplesPerPixel === 3 && $bitsPerSample === [8, 8, 8]) {
            return $this->decodeUncompressedRgb(
                $data,
                $path,
                $width,
                $height,
                $photometricInterpretation,
                $stripOffsets,
                $stripByteCounts,
                $rowsPerStrip,
            );
        }

        throw new InvalidArgumentException(sprintf(
            "TIFF image '%s' uses unsupported BitsPerSample/SamplesPerPixel combination.",
            $path,
        ));
    }

    /**
     * @param list<int> $stripOffsets
     * @param list<int> $stripByteCounts
     */
    private function decodeUncompressedBilevel(
        string $data,
        string $path,
        int $width,
        int $height,
        int $photometricInterpretation,
        array $stripOffsets,
        array $stripByteCounts,
        int $rowsPerStrip,
    ): ImageSource {
        return $this->decodeRasterBilevel(
            $path,
            $width,
            $height,
            $photometricInterpretation,
            $this->collectUncompressedStripData(
                $data,
                $path,
                $width,
                $height,
                $stripOffsets,
                $stripByteCounts,
                $rowsPerStrip,
                intdiv($width + 7, 8),
            ),
            $rowsPerStrip,
        );
    }

    /**
     * @param list<int> $stripOffsets
     * @param list<int> $stripByteCounts
     */
    private function decodeUncompressedGrayscale(
        string $data,
        string $path,
        int $width,
        int $height,
        int $photometricInterpretation,
        array $stripOffsets,
        array $stripByteCounts,
        int $rowsPerStrip,
    ): ImageSource {
        if ($photometricInterpretation !== 0 && $photometricInterpretation !== 1) {
            throw new InvalidArgumentException(sprintf(
                "TIFF image '%s' uses unsupported grayscale PhotometricInterpretation %d.",
                $path,
                $photometricInterpretation,
            ));
        }

        return $this->decodeRasterGrayscale(
            $path,
            $width,
            $height,
            $photometricInterpretation,
            $this->collectUncompressedStripData(
                $data,
                $path,
                $width,
                $height,
                $stripOffsets,
                $stripByteCounts,
                $rowsPerStrip,
                $width,
            ),
            $rowsPerStrip,
        );
    }

    /**
     * @param list<int> $stripOffsets
     * @param list<int> $stripByteCounts
     */
    private function decodeUncompressedRgb(
        string $data,
        string $path,
        int $width,
        int $height,
        int $photometricInterpretation,
        array $stripOffsets,
        array $stripByteCounts,
        int $rowsPerStrip,
    ): ImageSource {
        if ($photometricInterpretation !== 2) {
            throw new InvalidArgumentException(sprintf(
                "TIFF image '%s' uses unsupported RGB PhotometricInterpretation %d.",
                $path,
                $photometricInterpretation,
            ));
        }

        return $this->decodeRasterRgb(
            $path,
            $width,
            $height,
            $photometricInterpretation,
            $this->collectUncompressedStripData(
                $data,
                $path,
                $width,
                $height,
                $stripOffsets,
                $stripByteCounts,
                $rowsPerStrip,
                $width * 3,
            ),
            $rowsPerStrip,
        );
    }

    /**
     * @param list<int> $stripOffsets
     * @param list<int> $stripByteCounts
     * @param list<int> $bitsPerSample
     */
    private function decodeCcittBilevel(
        string $data,
        string $path,
        int $width,
        int $height,
        int $photometricInterpretation,
        array $stripOffsets,
        array $stripByteCounts,
        int $rowsPerStrip,
        int $k,
        array $bitsPerSample,
        int $samplesPerPixel,
    ): ImageSource {
        if ($bitsPerSample !== [1]) {
            throw new InvalidArgumentException(sprintf(
                "TIFF image '%s' uses unsupported BitsPerSample for CCITT TIFF import.",
                $path,
            ));
        }

        if ($samplesPerPixel !== 1) {
            throw new InvalidArgumentException(sprintf(
                "TIFF image '%s' uses unsupported SamplesPerPixel %d for CCITT TIFF import.",
                $path,
                $samplesPerPixel,
            ));
        }

        if (count($stripOffsets) !== 1) {
            throw new InvalidArgumentException(sprintf(
                "TIFF image '%s' uses multiple compressed strips, which are not yet supported for CCITT TIFF import.",
                $path,
            ));
        }

        return ImageSource::ccittFax(
            data: $this->readStripData($data, $path, $stripOffsets[0], $stripByteCounts[0]),
            width: $width,
            height: $height,
            k: $k,
            blackIs1: $photometricInterpretation === 1,
            endOfLine: $k === 0,
            endOfBlock: $rowsPerStrip === $height,
        );
    }

    /**
     * @return list<int>
     */
    private function readEntryValue(
        string $data,
        string $byteOrder,
        int $type,
        int $count,
        string $valueOffset,
        string $path,
    ): array {
        $typeSize = match ($type) {
            self::TYPE_SHORT => 2,
            self::TYPE_LONG => 4,
            default => throw new InvalidArgumentException(sprintf(
                "TIFF image '%s' uses unsupported field type %d.",
                $path,
                $type,
            )),
        };

        $byteLength = $count * $typeSize;
        $rawValue = $byteLength <= 4
            ? substr($valueOffset, 0, $byteLength)
            : substr($data, $this->readUint32($valueOffset, $byteOrder), $byteLength);

        if (strlen($rawValue) !== $byteLength) {
            throw new InvalidArgumentException(sprintf(
                "TIFF image '%s' contains truncated directory data.",
                $path,
            ));
        }

        $values = [];

        for ($offset = 0; $offset < $byteLength; $offset += $typeSize) {
            $chunk = substr($rawValue, $offset, $typeSize);
            $values[] = $type === self::TYPE_SHORT
                ? $this->readUint16($chunk, $byteOrder)
                : $this->readUint32($chunk, $byteOrder);
        }

        return $values;
    }

    /**
     * @param array<int, list<int>> $entries
     */
    private function requiredSingleInt(array $entries, int $tag, string $path): int
    {
        $value = $this->optionalSingleInt($entries, $tag);

        if ($value === null) {
            throw new InvalidArgumentException(sprintf(
                "TIFF image '%s' is missing required tag %d.",
                $path,
                $tag,
            ));
        }

        return $value;
    }

    /**
     * @param array<int, list<int>> $entries
     */
    private function optionalSingleInt(array $entries, int $tag): ?int
    {
        $values = $entries[$tag] ?? null;

        if ($values === null) {
            return null;
        }

        if (count($values) !== 1) {
            return null;
        }

        return $values[0];
    }

    /**
     * @param array<int, list<int>> $entries
     * @return list<int>
     */
    private function requiredIntList(array $entries, int $tag, string $path): array
    {
        $values = $entries[$tag] ?? null;

        if ($values === null || $values === []) {
            throw new InvalidArgumentException(sprintf(
                "TIFF image '%s' is missing required tag %d.",
                $path,
                $tag,
            ));
        }

        return $values;
    }

    private function readStripData(string $data, string $path, int $offset, int $length): string
    {
        $stripData = substr($data, $offset, $length);

        if (strlen($stripData) !== $length) {
            throw new InvalidArgumentException(sprintf(
                "TIFF image '%s' strip data is truncated.",
                $path,
            ));
        }

        return $stripData;
    }

    private function decodeRasterBilevel(
        string $path,
        int $width,
        int $height,
        int $photometricInterpretation,
        string $bitmap,
        int $rowsPerStrip,
    ): ImageSource {
        if ($photometricInterpretation !== 0 && $photometricInterpretation !== 1) {
            throw new InvalidArgumentException(sprintf(
                "TIFF image '%s' uses unsupported bilevel PhotometricInterpretation %d.",
                $path,
                $photometricInterpretation,
            ));
        }

        $expectedByteCount = intdiv($width + 7, 8) * $height;

        if (strlen($bitmap) !== $expectedByteCount) {
            throw new InvalidArgumentException(sprintf(
                "TIFF image '%s' uses unsupported uncompressed strip layout.",
                $path,
            ));
        }

        if ($photometricInterpretation === 1) {
            $bitmap = $this->invertPackedBitmap($bitmap);
        }

        return ImageSource::compressed($bitmap, $width, $height, ImageColorSpace::GRAY, 1);
    }

    private function decodeRasterGrayscale(
        string $path,
        int $width,
        int $height,
        int $photometricInterpretation,
        string $gray,
        int $rowsPerStrip,
    ): ImageSource {
        if ($photometricInterpretation !== 0 && $photometricInterpretation !== 1) {
            throw new InvalidArgumentException(sprintf(
                "TIFF image '%s' uses unsupported grayscale PhotometricInterpretation %d.",
                $path,
                $photometricInterpretation,
            ));
        }

        if (strlen($gray) !== $width * $height) {
            throw new InvalidArgumentException(sprintf(
                "TIFF image '%s' uses unsupported uncompressed strip layout.",
                $path,
            ));
        }

        if ($photometricInterpretation === 0) {
            $gray = $this->invert8BitSamples($gray);
        }

        return ImageSource::compressed($gray, $width, $height, ImageColorSpace::GRAY, 8);
    }

    private function decodeRasterRgb(
        string $path,
        int $width,
        int $height,
        int $photometricInterpretation,
        string $rgb,
        int $rowsPerStrip,
    ): ImageSource {
        if ($photometricInterpretation !== 2) {
            throw new InvalidArgumentException(sprintf(
                "TIFF image '%s' uses unsupported RGB PhotometricInterpretation %d.",
                $path,
                $photometricInterpretation,
            ));
        }

        if (strlen($rgb) !== $width * $height * 3) {
            throw new InvalidArgumentException(sprintf(
                "TIFF image '%s' uses unsupported uncompressed strip layout.",
                $path,
            ));
        }

        return ImageSource::compressed($rgb, $width, $height, ImageColorSpace::RGB, 8);
    }

    /**
     * @param list<int> $stripOffsets
     * @param list<int> $stripByteCounts
     */
    private function collectUncompressedStripData(
        string $data,
        string $path,
        int $width,
        int $height,
        array $stripOffsets,
        array $stripByteCounts,
        int $rowsPerStrip,
        int $rowByteLength,
    ): string {
        $expectedRows = 0;
        $bytes = '';

        foreach ($stripOffsets as $index => $stripOffset) {
            $stripByteCount = $stripByteCounts[$index];
            $stripData = substr($data, $stripOffset, $stripByteCount);

            if (strlen($stripData) !== $stripByteCount) {
                throw new InvalidArgumentException(sprintf(
                    "TIFF image '%s' strip data is truncated.",
                    $path,
                ));
            }

            $rowsInStrip = min($rowsPerStrip, $height - $expectedRows);
            $expectedByteCount = $rowByteLength * $rowsInStrip;

            if ($stripByteCount !== $expectedByteCount) {
                throw new InvalidArgumentException(sprintf(
                    "TIFF image '%s' uses unsupported uncompressed strip layout.",
                    $path,
                ));
            }

            $bytes .= $stripData;
            $expectedRows += $rowsInStrip;
        }

        if ($expectedRows !== $height) {
            throw new InvalidArgumentException(sprintf(
                "TIFF image '%s' strip table does not cover the declared image height.",
                $path,
            ));
        }

        return $bytes;
    }

    /**
     * @param list<int> $stripOffsets
     * @param list<int> $stripByteCounts
     * @param callable(string): string $decodeStrip
     */
    private function collectDecodedStripData(
        string $data,
        string $path,
        array $stripOffsets,
        array $stripByteCounts,
        callable $decodeStrip,
    ): string {
        $bytes = '';

        foreach ($stripOffsets as $index => $stripOffset) {
            $encodedStrip = $this->readStripData($data, $path, $stripOffset, $stripByteCounts[$index]);
            $bytes .= $decodeStrip($encodedStrip);
        }

        return $bytes;
    }

    private function invert8BitSamples(string $samples): string
    {
        $inverted = '';

        for ($index = 0; $index < strlen($samples); $index++) {
            $inverted .= chr((0xFF - ord($samples[$index])) & 0xFF);
        }

        return $inverted;
    }

    private function invertPackedBitmap(string $bitmap): string
    {
        $inverted = '';

        for ($index = 0; $index < strlen($bitmap); $index++) {
            $inverted .= chr((ord($bitmap[$index]) ^ 0xFF) & 0xFF);
        }

        return $inverted;
    }

    private function readUint16(string $bytes, string $byteOrder): int
    {
        $value = unpack($byteOrder === self::LITTLE_ENDIAN ? 'vvalue' : 'nvalue', $bytes);

        return is_array($value) && isset($value['value']) && is_int($value['value'])
            ? $value['value']
            : 0;
    }

    private function readUint32(string $bytes, string $byteOrder): int
    {
        $value = unpack($byteOrder === self::LITTLE_ENDIAN ? 'Vvalue' : 'Nvalue', $bytes);

        return is_array($value) && isset($value['value']) && is_int($value['value'])
            ? $value['value']
            : 0;
    }
}
