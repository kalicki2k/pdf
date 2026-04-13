<?php

declare(strict_types=1);

namespace Kalle\Pdf\Image;

use function array_key_exists;

use function gzcompress;
use function gzuncompress;

use InvalidArgumentException;

use function ord;

use RuntimeException;

use function strlen;
use function substr;

use function unpack;

final readonly class PngImageDecoder
{
    private const string SIGNATURE = "\x89PNG\r\n\x1a\n";

    public function decode(string $data, string $path = 'memory'): ImageSource
    {
        if (!str_starts_with($data, self::SIGNATURE)) {
            throw new InvalidArgumentException(sprintf(
                "PNG image '%s' has an invalid signature.",
                $path,
            ));
        }

        $offset = strlen(self::SIGNATURE);
        $header = null;
        $idatData = '';
        $palette = null;
        $transparency = null;

        while ($offset < strlen($data)) {
            $length = $this->readUint32(substr($data, $offset, 4));
            $offset += 4;
            $chunkType = substr($data, $offset, 4);
            $offset += 4;
            $chunkData = substr($data, $offset, $length);
            $offset += $length + 4;

            if ($chunkType === 'IHDR') {
                $header = $this->parseHeader($chunkData, $path);

                continue;
            }

            if ($chunkType === 'IDAT') {
                $idatData .= $chunkData;

                continue;
            }

            if ($chunkType === 'PLTE') {
                $palette = $chunkData;

                continue;
            }

            if ($chunkType === 'tRNS') {
                $transparency = $chunkData;

                continue;
            }

            if ($chunkType === 'IEND') {
                break;
            }
        }

        if ($header === null || $idatData === '') {
            throw new InvalidArgumentException(sprintf(
                "PNG image '%s' is missing required image data.",
                $path,
            ));
        }

        if ($header['colorType'] === 3 && ($palette === null || $palette === '')) {
            throw new InvalidArgumentException(sprintf(
                "PNG image '%s' is missing a required palette.",
                $path,
            ));
        }

        $indexedPalette = $palette;

        $inflated = gzuncompress($idatData);

        if (!is_string($inflated)) {
            throw new RuntimeException(sprintf(
                "Unable to inflate PNG image '%s'.",
                $path,
            ));
        }

        $decodedRows = $this->decodeRows($inflated, $header, $path);

        $colorBytes = '';
        $alphaBytes = '';

        foreach ($decodedRows as $row) {
            if ($header['hasAlpha']) {
                [$rowColorBytes, $rowAlphaBytes] = $this->splitAlphaChannels($row, $header);
                $colorBytes .= $rowColorBytes;
                $alphaBytes .= $rowAlphaBytes;

                continue;
            }

            if ($header['colorType'] === 3 && $transparency !== null && $transparency !== '') {
                $colorBytes .= $row;
                $alphaBytes .= $this->indexedAlphaBytesForRow($row, $transparency);

                continue;
            }

            $colorBytes .= $row;
        }

        $compressedColorBytes = gzcompress($colorBytes);

        if (!is_string($compressedColorBytes)) {
            throw new RuntimeException(sprintf(
                "Unable to compress PNG image '%s'.",
                $path,
            ));
        }

        $softMask = null;

        if ($alphaBytes !== '') {
            $compressedAlphaBytes = gzcompress($alphaBytes);

            if (!is_string($compressedAlphaBytes)) {
                throw new RuntimeException(sprintf(
                    "Unable to compress PNG alpha channel for '%s'.",
                    $path,
                ));
            }

            $softMask = ImageSource::alphaMask(
                data: $compressedAlphaBytes,
                width: $header['width'],
                height: $header['height'],
                bitsPerComponent: $header['bitsPerComponent'],
            );
        }

        if ($header['colorType'] === 3) {
            if (!is_string($indexedPalette) || $indexedPalette === '') {
                throw new RuntimeException(sprintf(
                    "PNG image '%s' palette validation failed unexpectedly.",
                    $path,
                ));
            }

            return ImageSource::indexed(
                data: $compressedColorBytes,
                width: $header['width'],
                height: $header['height'],
                bitsPerComponent: $header['bitsPerComponent'],
                lookupTable: $indexedPalette,
                softMask: $softMask,
            );
        }

        return ImageSource::flate(
            data: $compressedColorBytes,
            width: $header['width'],
            height: $header['height'],
            colorSpace: $header['colorSpace'],
            bitsPerComponent: $header['bitsPerComponent'],
            softMask: $softMask,
        );
    }

    /**
     * @return array{
     *   width: int,
     *   height: int,
     *   bitsPerComponent: int,
     *   colorType: int,
     *   colorSpace: ImageColorSpace,
     *   channels: int,
     *   hasAlpha: bool,
     *   bytesPerPixel: int,
     *   rowBytes: int
     * }
     */
    private function parseHeader(string $chunkData, string $path): array
    {
        if (strlen($chunkData) !== 13) {
            throw new InvalidArgumentException(sprintf(
                "PNG image '%s' has an invalid IHDR chunk.",
                $path,
            ));
        }

        $width = $this->readUint32(substr($chunkData, 0, 4));
        $height = $this->readUint32(substr($chunkData, 4, 4));
        $bitDepth = ord($chunkData[8]);
        $colorType = ord($chunkData[9]);
        $compressionMethod = ord($chunkData[10]);
        $filterMethod = ord($chunkData[11]);
        $interlaceMethod = ord($chunkData[12]);

        if ($compressionMethod !== 0 || $filterMethod !== 0) {
            throw new InvalidArgumentException(sprintf(
                "PNG image '%s' uses unsupported compression or filter settings.",
                $path,
            ));
        }

        if ($interlaceMethod !== 0) {
            throw new InvalidArgumentException(sprintf(
                "PNG image '%s' uses unsupported interlacing.",
                $path,
            ));
        }

        $supportedColorTypes = [
            0 => [ImageColorSpace::GRAY, 1, false],
            2 => [ImageColorSpace::RGB, 3, false],
            3 => [ImageColorSpace::RGB, 1, false],
            4 => [ImageColorSpace::GRAY, 2, true],
            6 => [ImageColorSpace::RGB, 4, true],
        ];

        if (!array_key_exists($colorType, $supportedColorTypes)) {
            throw new InvalidArgumentException(sprintf(
                "PNG image '%s' uses unsupported color type '%d'.",
                $path,
                $colorType,
            ));
        }

        if ($bitDepth !== 8) {
            throw new InvalidArgumentException(sprintf(
                "PNG image '%s' uses unsupported bit depth '%d'.",
                $path,
                $bitDepth,
            ));
        }

        [$colorSpace, $channels, $hasAlpha] = $supportedColorTypes[$colorType];

        return [
            'width' => $width,
            'height' => $height,
            'bitsPerComponent' => $bitDepth,
            'colorType' => $colorType,
            'colorSpace' => $colorSpace,
            'channels' => $channels,
            'hasAlpha' => $hasAlpha,
            'bytesPerPixel' => $channels,
            'rowBytes' => $width * $channels,
        ];
    }

    /**
     * @param array{
     *   width: int,
     *   height: int,
     *   bitsPerComponent: int,
     *   colorType: int,
     *   colorSpace: ImageColorSpace,
     *   channels: int,
     *   hasAlpha: bool,
     *   bytesPerPixel: int,
     *   rowBytes: int
     * } $header
     * @return list<string>
     */
    private function decodeRows(string $inflated, array $header, string $path): array
    {
        $rows = [];
        $offset = 0;
        $previousRow = str_repeat("\0", $header['rowBytes']);

        for ($rowIndex = 0; $rowIndex < $header['height']; $rowIndex++) {
            $filterType = ord($inflated[$offset] ?? "\0");
            $offset++;
            $rowData = substr($inflated, $offset, $header['rowBytes']);
            $offset += $header['rowBytes'];

            if (strlen($rowData) !== $header['rowBytes']) {
                throw new InvalidArgumentException(sprintf(
                    "PNG image '%s' ended unexpectedly while reading scanlines.",
                    $path,
                ));
            }

            $decodedRow = $this->unfilterRow($rowData, $previousRow, $filterType, $header['bytesPerPixel'], $path);
            $rows[] = $decodedRow;
            $previousRow = $decodedRow;
        }

        return $rows;
    }

    private function unfilterRow(
        string $rowData,
        string $previousRow,
        int $filterType,
        int $bytesPerPixel,
        string $path,
    ): string {
        $decoded = '';
        $rowLength = strlen($rowData);

        for ($index = 0; $index < $rowLength; $index++) {
            $raw = ord($rowData[$index]);
            $left = $index >= $bytesPerPixel ? ord($decoded[$index - $bytesPerPixel]) : 0;
            $up = ord($previousRow[$index] ?? "\0");
            $upperLeft = $index >= $bytesPerPixel ? ord($previousRow[$index - $bytesPerPixel] ?? "\0") : 0;

            $value = match ($filterType) {
                0 => $raw,
                1 => ($raw + $left) & 0xff,
                2 => ($raw + $up) & 0xff,
                3 => ($raw + intdiv($left + $up, 2)) & 0xff,
                4 => ($raw + $this->paethPredictor($left, $up, $upperLeft)) & 0xff,
                default => throw new InvalidArgumentException(sprintf(
                    "PNG image '%s' uses unsupported filter type '%d'.",
                    $path,
                    $filterType,
                )),
            };

            $decoded .= chr($value);
        }

        return $decoded;
    }

    private function paethPredictor(int $left, int $up, int $upperLeft): int
    {
        $prediction = $left + $up - $upperLeft;
        $leftDistance = abs($prediction - $left);
        $upDistance = abs($prediction - $up);
        $upperLeftDistance = abs($prediction - $upperLeft);

        if ($leftDistance <= $upDistance && $leftDistance <= $upperLeftDistance) {
            return $left;
        }

        if ($upDistance <= $upperLeftDistance) {
            return $up;
        }

        return $upperLeft;
    }

    /**
     * @param array{
     *   width: int,
     *   height: int,
     *   bitsPerComponent: int,
     *   colorType: int,
     *   colorSpace: ImageColorSpace,
     *   channels: int,
     *   hasAlpha: bool,
     *   bytesPerPixel: int,
     *   rowBytes: int
     * } $header
     * @return array{0: string, 1: string}
     */
    private function splitAlphaChannels(string $row, array $header): array
    {
        $pixelStride = $header['channels'];
        $colorStride = $header['channels'] - 1;
        $colorBytes = '';
        $alphaBytes = '';

        for ($offset = 0; $offset < strlen($row); $offset += $pixelStride) {
            $colorBytes .= substr($row, $offset, $colorStride);
            $alphaBytes .= $row[$offset + $colorStride];
        }

        return [$colorBytes, $alphaBytes];
    }

    private function indexedAlphaBytesForRow(string $row, string $transparency): string
    {
        $alphaBytes = '';
        $transparencyLength = strlen($transparency);

        for ($offset = 0; $offset < strlen($row); $offset++) {
            $paletteIndex = ord($row[$offset]);
            $alphaBytes .= $paletteIndex < $transparencyLength
                ? $transparency[$paletteIndex]
                : "\xff";
        }

        return $alphaBytes;
    }

    private function readUint32(string $bytes): int
    {
        $value = unpack('Nvalue', $bytes);

        if (!is_array($value) || !isset($value['value']) || !is_int($value['value'])) {
            throw new RuntimeException('Unable to decode PNG chunk length.');
        }

        return $value['value'];
    }
}
