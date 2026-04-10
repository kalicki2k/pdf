<?php

declare(strict_types=1);

namespace Kalle\Pdf\Image;

use DeflateContext;
use InvalidArgumentException;
use Kalle\Pdf\Binary\BinaryData;
use Kalle\Pdf\Binary\BinaryDataSource;
use Kalle\Pdf\Render\PdfOutput;
use RuntimeException;

final class PngAlphaChannelBinaryDataSource implements BinaryDataSource
{
    private const READ_CHUNK_BYTES = 8192;

    public function __construct(
        private readonly string $path,
        private readonly BinaryData $compressedData,
        private readonly int $width,
        private readonly int $height,
        private readonly int $colors,
        private readonly bool $isAlphaChannel,
    ) {
    }

    public function length(): int
    {
        throw new RuntimeException(sprintf(
            'PNG alpha channel source %s is stream-only and does not support direct length inspection.',
            static::class,
        ));
    }

    public function slice(int $offset, int $length): string
    {
        throw new RuntimeException(sprintf(
            'PNG alpha channel source %s is stream-only and does not support random-access slicing.',
            static::class,
        ));
    }

    public function writeTo(PdfOutput $output): void
    {
        $channels = $this->colors + 1;
        $scanlineLength = 1 + ($this->width * $channels);
        $expectedLength = $scanlineLength * $this->height;
        $inflater = inflate_init(ZLIB_ENCODING_DEFLATE);

        if ($inflater === false) {
            throw new InvalidArgumentException("Unable to decompress PNG image data for '$this->path'.");
        }

        $deflater = deflate_init(ZLIB_ENCODING_DEFLATE);

        if ($deflater === false) {
            throw new InvalidArgumentException('Failed to recompress PNG image data.');
        }

        $rowBuffer = '';
        $processedRows = 0;
        $writtenBytes = 0;
        $previousRow = array_fill(0, $this->width * $channels, 0);

        for ($offset = 0, $length = $this->compressedData->length(); $offset < $length; $offset += self::READ_CHUNK_BYTES) {
            $chunk = $this->compressedData->slice($offset, min(self::READ_CHUNK_BYTES, $length - $offset));
            $decodedChunk = @inflate_add($inflater, $chunk, ZLIB_SYNC_FLUSH);

            if ($decodedChunk === false) {
                throw new InvalidArgumentException("Unable to decompress PNG image data for '$this->path'.");
            }

            $rowBuffer .= $decodedChunk;
            $processedRows = $this->writeCompleteRows(
                $output,
                $deflater,
                $rowBuffer,
                $scanlineLength,
                $channels,
                $processedRows,
                $previousRow,
                $writtenBytes,
            );
        }

        $decodedTail = @inflate_add($inflater, '', ZLIB_FINISH);

        if ($decodedTail === false) {
            throw new InvalidArgumentException("Unable to decompress PNG image data for '$this->path'.");
        }

        $rowBuffer .= $decodedTail;
        $processedRows = $this->writeCompleteRows(
            $output,
            $deflater,
            $rowBuffer,
            $scanlineLength,
            $channels,
            $processedRows,
            $previousRow,
            $writtenBytes,
        );

        if ($writtenBytes !== $expectedLength || $processedRows !== $this->height || $rowBuffer !== '') {
            throw new InvalidArgumentException("Unexpected PNG alpha image data length for '$this->path'.");
        }

        $tail = deflate_add($deflater, '', ZLIB_FINISH);

        if ($tail === false) {
            throw new InvalidArgumentException('Failed to recompress PNG image data.');
        }

        if ($tail !== '') {
            $output->write($tail);
        }
    }

    public function close(): void
    {
    }

    /**
     * @param list<int> $previousRow
     */
    private function writeCompleteRows(
        PdfOutput $output,
        DeflateContext $deflater,
        string &$rowBuffer,
        int $scanlineLength,
        int $bytesPerPixel,
        int $processedRows,
        array &$previousRow,
        int &$writtenBytes,
    ): int {
        while (strlen($rowBuffer) >= $scanlineLength) {
            if ($processedRows >= $this->height) {
                break;
            }

            $scanline = substr($rowBuffer, 0, $scanlineLength);
            $rowBuffer = substr($rowBuffer, $scanlineLength);
            $writtenBytes += $scanlineLength;
            $filterType = ord($scanline[0]);
            $rowBytes = self::decodeRowBytes(substr($scanline, 1));
            $unfilteredRow = PngAlphaChannelSplitter::unfilterScanline(
                $rowBytes,
                $previousRow,
                $filterType,
                $bytesPerPixel,
                $this->path,
            );
            $previousRow = $unfilteredRow;

            $chunk = deflate_add($deflater, $this->buildChannelRow($unfilteredRow), ZLIB_NO_FLUSH);

            if ($chunk === false) {
                throw new InvalidArgumentException('Failed to recompress PNG image data.');
            }

            if ($chunk !== '') {
                $output->write($chunk);
            }

            $processedRows++;
        }

        return $processedRows;
    }

    /**
     * @param list<int> $unfilteredRow
     */
    private function buildChannelRow(array $unfilteredRow): string
    {
        $row = "\x00";
        $channels = $this->colors + 1;

        for ($pixelOffset = 0, $count = count($unfilteredRow); $pixelOffset < $count; $pixelOffset += $channels) {
            if ($this->isAlphaChannel) {
                $row .= chr($unfilteredRow[$pixelOffset + $this->colors]);

                continue;
            }

            for ($channelIndex = 0; $channelIndex < $this->colors; $channelIndex++) {
                $row .= chr($unfilteredRow[$pixelOffset + $channelIndex]);
            }
        }

        return $row;
    }

    /**
     * @return list<int>
     */
    private static function decodeRowBytes(string $bytes): array
    {
        $decoded = [];

        for ($index = 0, $length = strlen($bytes); $index < $length; $index++) {
            $decoded[] = ord($bytes[$index]);
        }

        return $decoded;
    }
}
