<?php

declare(strict_types=1);

namespace Kalle\Pdf\Image;

use DeflateContext;
use InvalidArgumentException;
use Kalle\Pdf\Binary\BinaryData;
use RuntimeException;

final class PngAlphaChannelSplitter
{
    private const READ_CHUNK_BYTES = 8192;

    /**
     * @return array{0:BinaryData,1:BinaryData}
     */
    public static function split(string $path, BinaryData $compressedData, int $width, int $height, int $colors): array
    {
        $channels = $colors + 1;
        $bytesPerPixel = $channels;
        $scanlineLength = 1 + ($width * $channels);
        $expectedLength = $scanlineLength * $height;
        $inflater = inflate_init(ZLIB_ENCODING_DEFLATE);

        if ($inflater === false) {
            throw new InvalidArgumentException("Unable to decompress PNG image data for '$path'.");
        }

        $colorCompressor = self::createCompressor();
        $alphaCompressor = self::createCompressor();
        $rowBuffer = '';
        $processedRows = 0;
        $writtenBytes = 0;
        $previousRow = array_fill(0, $width * $channels, 0);

        for ($offset = 0, $length = $compressedData->length(); $offset < $length; $offset += self::READ_CHUNK_BYTES) {
            $chunk = $compressedData->slice($offset, min(self::READ_CHUNK_BYTES, $length - $offset));
            $decodedChunk = @inflate_add($inflater, $chunk, ZLIB_SYNC_FLUSH);

            if ($decodedChunk === false) {
                throw new InvalidArgumentException("Unable to decompress PNG image data for '$path'.");
            }

            $rowBuffer .= $decodedChunk;
            $processedRows = self::processCompleteRows(
                $path,
                $rowBuffer,
                $scanlineLength,
                $bytesPerPixel,
                $colors,
                $height,
                $processedRows,
                $previousRow,
                $colorCompressor,
                $alphaCompressor,
                $writtenBytes,
            );
        }

        $decodedTail = @inflate_add($inflater, '', ZLIB_FINISH);

        if ($decodedTail === false) {
            throw new InvalidArgumentException("Unable to decompress PNG image data for '$path'.");
        }

        $rowBuffer .= $decodedTail;
        $processedRows = self::processCompleteRows(
            $path,
            $rowBuffer,
            $scanlineLength,
            $bytesPerPixel,
            $colors,
            $height,
            $processedRows,
            $previousRow,
            $colorCompressor,
            $alphaCompressor,
            $writtenBytes,
        );

        if ($writtenBytes !== $expectedLength || $processedRows !== $height || $rowBuffer !== '') {
            throw new InvalidArgumentException("Unexpected PNG alpha image data length for '$path'.");
        }

        return [
            self::finishCompression($path, $colorCompressor),
            self::finishCompression($path, $alphaCompressor),
        ];
    }

    /**
     * @param list<int> $rowBytes
     * @param list<int> $previousRow
     * @return list<int>
     */
    public static function unfilterScanline(
        array $rowBytes,
        array $previousRow,
        int $filterType,
        int $bytesPerPixel,
        string $path,
    ): array {
        $result = [];

        foreach ($rowBytes as $index => $value) {
            $left = $index >= $bytesPerPixel ? $result[$index - $bytesPerPixel] : 0;
            $up = $previousRow[$index] ?? 0;
            $upperLeft = $index >= $bytesPerPixel ? ($previousRow[$index - $bytesPerPixel] ?? 0) : 0;

            $result[] = match ($filterType) {
                0 => $value,
                1 => ($value + $left) & 0xFF,
                2 => ($value + $up) & 0xFF,
                3 => ($value + intdiv($left + $up, 2)) & 0xFF,
                4 => ($value + self::paethPredictor($left, $up, $upperLeft)) & 0xFF,
                default => throw new InvalidArgumentException("Unsupported PNG filter type '$filterType' in '$path'."),
            };
        }

        return $result;
    }

    public static function paethPredictor(int $left, int $up, int $upperLeft): int
    {
        $prediction = $left + $up - $upperLeft;
        $distanceLeft = abs($prediction - $left);
        $distanceUp = abs($prediction - $up);
        $distanceUpperLeft = abs($prediction - $upperLeft);

        if ($distanceLeft <= $distanceUp && $distanceLeft <= $distanceUpperLeft) {
            return $left;
        }

        if ($distanceUp <= $distanceUpperLeft) {
            return $up;
        }

        return $upperLeft;
    }

    /**
     * @return array{stream:resource,context:DeflateContext,length:int}
     */
    private static function createCompressor(): array
    {
        $stream = fopen('php://temp', 'w+b');

        if ($stream === false) {
            throw new RuntimeException('Unable to allocate a temporary stream for PNG channel compression.');
        }

        $context = deflate_init(ZLIB_ENCODING_DEFLATE);

        if ($context === false) {
            fclose($stream);

            throw new InvalidArgumentException('Failed to recompress PNG image data.');
        }

        return [
            'stream' => $stream,
            'context' => $context,
            'length' => 0,
        ];
    }

    /**
     * @param array{stream:resource,context:DeflateContext,length:int} $colorCompressor
     * @param array{stream:resource,context:DeflateContext,length:int} $alphaCompressor
     * @param list<int> $previousRow
     */
    private static function processCompleteRows(
        string $path,
        string &$rowBuffer,
        int $scanlineLength,
        int $bytesPerPixel,
        int $colors,
        int $height,
        int $processedRows,
        array &$previousRow,
        array &$colorCompressor,
        array &$alphaCompressor,
        int &$writtenBytes,
    ): int {
        while (strlen($rowBuffer) >= $scanlineLength) {
            if ($processedRows >= $height) {
                break;
            }

            $scanline = substr($rowBuffer, 0, $scanlineLength);
            $rowBuffer = substr($rowBuffer, $scanlineLength);
            $writtenBytes += $scanlineLength;
            $filterType = ord($scanline[0]);
            $rowBytes = self::decodeRowBytes(substr($scanline, 1));
            $unfilteredRow = self::unfilterScanline($rowBytes, $previousRow, $filterType, $bytesPerPixel, $path);
            $previousRow = $unfilteredRow;

            self::appendCompressedRow($colorCompressor, self::buildColorRow($unfilteredRow, $colors));
            self::appendCompressedRow($alphaCompressor, self::buildAlphaRow($unfilteredRow, $colors));
            $processedRows++;
        }

        return $processedRows;
    }

    /**
     * @param list<int> $unfilteredRow
     */
    private static function buildColorRow(array $unfilteredRow, int $colors): string
    {
        $row = "\x00";
        $channels = $colors + 1;

        for ($pixelOffset = 0, $count = count($unfilteredRow); $pixelOffset < $count; $pixelOffset += $channels) {
            for ($channelIndex = 0; $channelIndex < $colors; $channelIndex++) {
                $row .= chr($unfilteredRow[$pixelOffset + $channelIndex]);
            }
        }

        return $row;
    }

    /**
     * @param list<int> $unfilteredRow
     */
    private static function buildAlphaRow(array $unfilteredRow, int $colors): string
    {
        $row = "\x00";
        $channels = $colors + 1;

        for ($pixelOffset = 0, $count = count($unfilteredRow); $pixelOffset < $count; $pixelOffset += $channels) {
            $row .= chr($unfilteredRow[$pixelOffset + $colors]);
        }

        return $row;
    }

    /**
     * @param array{stream:resource,context:DeflateContext,length:int} $compressor
     */
    private static function appendCompressedRow(array &$compressor, string $row): void
    {
        $chunk = deflate_add($compressor['context'], $row, ZLIB_NO_FLUSH);

        if ($chunk === false || fwrite($compressor['stream'], $chunk) === false) {
            throw new InvalidArgumentException('Failed to recompress PNG image data.');
        }

        $compressor['length'] += strlen($chunk);
    }

    /**
     * @param array{stream:resource,context:DeflateContext,length:int} $compressor
     */
    private static function finishCompression(string $path, array $compressor): BinaryData
    {
        $tail = deflate_add($compressor['context'], '', ZLIB_FINISH);

        if ($tail === false || fwrite($compressor['stream'], $tail) === false) {
            fclose($compressor['stream']);

            throw new InvalidArgumentException('Failed to recompress PNG image data.');
        }

        $compressor['length'] += strlen($tail);

        if (rewind($compressor['stream']) === false) {
            fclose($compressor['stream']);

            throw new RuntimeException("Unable to rewind recompressed PNG channel stream for '$path'.");
        }

        return BinaryData::fromStream($compressor['stream'], $compressor['length'], closeOnDestruct: true);
    }

    /**
     * @return list<int>
     */
    private static function decodeRowBytes(string $row): array
    {
        $unpacked = unpack('C*', $row);

        if ($unpacked === false) {
            return [];
        }

        $bytes = [];

        foreach (array_values($unpacked) as $value) {
            if (!is_int($value)) {
                throw new RuntimeException('Unable to decode PNG scanline bytes.');
            }

            $bytes[] = $value;
        }

        /** @var list<int> $bytes */
        return $bytes;
    }
}
