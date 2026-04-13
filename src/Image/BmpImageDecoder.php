<?php

declare(strict_types=1);

namespace Kalle\Pdf\Image;

use function is_array;
use function is_int;
use function ord;
use function pack;
use function sprintf;
use function strlen;
use function substr;
use function unpack;

use InvalidArgumentException;

final readonly class BmpImageDecoder
{
    private const int COMPRESSION_RGB = 0;
    private const int COMPRESSION_BITFIELDS = 3;

    public function decode(string $data, string $path = 'memory'): ImageSource
    {
        if (!str_starts_with($data, 'BM')) {
            throw new InvalidArgumentException(sprintf(
                "BMP image '%s' has an invalid signature.",
                $path,
            ));
        }

        $pixelOffset = $this->readUint32($data, 10, $path, 'BMP pixel offset');
        $dibHeaderSize = $this->readUint32($data, 14, $path, 'BMP DIB header size');

        if ($dibHeaderSize < 40) {
            throw new InvalidArgumentException(sprintf(
                "BMP image '%s' uses unsupported DIB header size %d.",
                $path,
                $dibHeaderSize,
            ));
        }

        $width = $this->readInt32($data, 18, $path, 'BMP width');
        $signedHeight = $this->readInt32($data, 22, $path, 'BMP height');
        $planes = $this->readUint16($data, 26, $path, 'BMP planes');
        $bitsPerPixel = $this->readUint16($data, 28, $path, 'BMP bits per pixel');
        $compression = $this->readUint32($data, 30, $path, 'BMP compression');

        if ($width <= 0 || $signedHeight === 0) {
            throw new InvalidArgumentException(sprintf(
                "BMP image '%s' has invalid dimensions %d x %d.",
                $path,
                $width,
                $signedHeight,
            ));
        }

        if ($planes !== 1) {
            throw new InvalidArgumentException(sprintf(
                "BMP image '%s' uses unsupported plane count %d.",
                $path,
                $planes,
            ));
        }

        return match ($bitsPerPixel) {
            24 => $this->decode24Bit($data, $path, $width, $signedHeight, $pixelOffset, $compression),
            32 => $this->decode32Bit($data, $path, $width, $signedHeight, $pixelOffset, $compression, $dibHeaderSize),
            default => throw new InvalidArgumentException(sprintf(
                "BMP image '%s' uses unsupported bits-per-pixel value %d.",
                $path,
                $bitsPerPixel,
            )),
        };
    }

    private function decode24Bit(
        string $data,
        string $path,
        int $width,
        int $signedHeight,
        int $pixelOffset,
        int $compression,
    ): ImageSource {
        if ($compression !== self::COMPRESSION_RGB) {
            throw new InvalidArgumentException(sprintf(
                "BMP image '%s' uses unsupported compression %d for 24-bit BMP import.",
                $path,
                $compression,
            ));
        }

        $height = $signedHeight < 0 ? -$signedHeight : $signedHeight;
        $topDown = $signedHeight < 0;
        $rowStride = $this->bmpRowStride($width, 24);
        $rgb = '';

        for ($row = 0; $row < $height; $row++) {
            $sourceRow = $topDown ? $row : ($height - 1 - $row);
            $rowOffset = $pixelOffset + ($sourceRow * $rowStride);
            $rowBytes = substr($data, $rowOffset, $rowStride);

            if (strlen($rowBytes) !== $rowStride) {
                throw new InvalidArgumentException(sprintf(
                    "BMP image '%s' pixel data is truncated.",
                    $path,
                ));
            }

            for ($column = 0; $column < $width; $column++) {
                $pixelOffsetInRow = $column * 3;
                $blue = ord($rowBytes[$pixelOffsetInRow]);
                $green = ord($rowBytes[$pixelOffsetInRow + 1]);
                $red = ord($rowBytes[$pixelOffsetInRow + 2]);
                $rgb .= pack('C3', $red, $green, $blue);
            }
        }

        return (new DecodedRasterImage(
            width: $width,
            height: $height,
            colorSpace: ImageColorSpace::RGB,
            bitsPerComponent: 8,
            pixelData: $rgb,
        ))->toImageSource($path);
    }

    private function decode32Bit(
        string $data,
        string $path,
        int $width,
        int $signedHeight,
        int $pixelOffset,
        int $compression,
        int $dibHeaderSize,
    ): ImageSource {
        if (!($compression === self::COMPRESSION_RGB || $compression === self::COMPRESSION_BITFIELDS)) {
            throw new InvalidArgumentException(sprintf(
                "BMP image '%s' uses unsupported compression %d for 32-bit BMP import.",
                $path,
                $compression,
            ));
        }

        $redMask = 0x00FF0000;
        $greenMask = 0x0000FF00;
        $blueMask = 0x000000FF;
        $alphaMask = 0xFF000000;

        if ($compression === self::COMPRESSION_BITFIELDS) {
            $maskOffset = 14 + $dibHeaderSize;
            $redMask = $this->readUint32($data, $maskOffset, $path, 'BMP red mask');
            $greenMask = $this->readUint32($data, $maskOffset + 4, $path, 'BMP green mask');
            $blueMask = $this->readUint32($data, $maskOffset + 8, $path, 'BMP blue mask');
            $alphaMask = strlen(substr($data, $maskOffset + 12, 4)) === 4
                ? $this->readUint32($data, $maskOffset + 12, $path, 'BMP alpha mask')
                : 0;
        }

        if (
            !$this->isByteAlignedMask($redMask)
            || !$this->isByteAlignedMask($greenMask)
            || !$this->isByteAlignedMask($blueMask)
            || ($alphaMask !== 0 && !$this->isByteAlignedMask($alphaMask))
            || (($redMask | $greenMask | $blueMask | $alphaMask) !== ($redMask ^ $greenMask ^ $blueMask ^ $alphaMask))
        ) {
            throw new InvalidArgumentException(sprintf(
                "BMP image '%s' uses unsupported 32-bit channel masks.",
                $path,
            ));
        }

        $height = $signedHeight < 0 ? -$signedHeight : $signedHeight;
        $topDown = $signedHeight < 0;
        $rowStride = $this->bmpRowStride($width, 32);
        $rgb = '';
        $alpha = '';
        $hasAlpha = false;

        for ($row = 0; $row < $height; $row++) {
            $sourceRow = $topDown ? $row : ($height - 1 - $row);
            $rowOffset = $pixelOffset + ($sourceRow * $rowStride);
            $rowBytes = substr($data, $rowOffset, $rowStride);

            if (strlen($rowBytes) !== $rowStride) {
                throw new InvalidArgumentException(sprintf(
                    "BMP image '%s' pixel data is truncated.",
                    $path,
                ));
            }

            for ($column = 0; $column < $width; $column++) {
                $pixelOffsetInRow = $column * 4;
                $pixel = $this->readPixelUint32(substr($rowBytes, $pixelOffsetInRow, 4), $path);
                $red = $this->extractByteComponent($pixel, $redMask);
                $green = $this->extractByteComponent($pixel, $greenMask);
                $blue = $this->extractByteComponent($pixel, $blueMask);
                $alphaByte = $alphaMask !== 0 ? $this->extractByteComponent($pixel, $alphaMask) : 0xFF;
                $rgb .= pack('C3', $red, $green, $blue);
                $alpha .= chr($alphaByte & 0xFF);
                $hasAlpha = $hasAlpha || $alphaByte !== 0xFF;
            }
        }

        return (new DecodedRasterImage(
            width: $width,
            height: $height,
            colorSpace: ImageColorSpace::RGB,
            bitsPerComponent: 8,
            pixelData: $rgb,
            alphaData: $hasAlpha ? $alpha : null,
        ))->toImageSource($path);
    }

    private function bmpRowStride(int $width, int $bitsPerPixel): int
    {
        return intdiv((($width * $bitsPerPixel) + 31), 32) * 4;
    }

    private function isByteAlignedMask(int $mask): bool
    {
        return $mask === 0
            || $mask === 0x000000FF
            || $mask === 0x0000FF00
            || $mask === 0x00FF0000
            || $mask === 0xFF000000;
    }

    private function extractByteComponent(int $pixel, int $mask): int
    {
        if ($mask === 0) {
            return 0xFF;
        }

        $shift = match ($mask) {
            0x000000FF => 0,
            0x0000FF00 => 8,
            0x00FF0000 => 16,
            0xFF000000 => 24,
            default => 0,
        };

        return ($pixel & $mask) >> $shift;
    }

    private function readPixelUint32(string $bytes, string $path): int
    {
        $value = unpack('Vvalue', $bytes);

        if (!is_array($value) || !isset($value['value']) || !is_int($value['value'])) {
            throw new InvalidArgumentException(sprintf(
                "BMP image '%s' contains truncated pixel data.",
                $path,
            ));
        }

        return $value['value'];
    }

    private function readUint16(string $data, int $offset, string $path, string $field): int
    {
        $value = unpack('vvalue', substr($data, $offset, 2));

        if (!is_array($value) || !isset($value['value']) || !is_int($value['value'])) {
            throw new InvalidArgumentException(sprintf(
                "BMP image '%s' contains truncated %s data.",
                $path,
                $field,
            ));
        }

        return $value['value'];
    }

    private function readUint32(string $data, int $offset, string $path, string $field): int
    {
        $value = unpack('Vvalue', substr($data, $offset, 4));

        if (!is_array($value) || !isset($value['value']) || !is_int($value['value'])) {
            throw new InvalidArgumentException(sprintf(
                "BMP image '%s' contains truncated %s data.",
                $path,
                $field,
            ));
        }

        return $value['value'];
    }

    private function readInt32(string $data, int $offset, string $path, string $field): int
    {
        $unsigned = $this->readUint32($data, $offset, $path, $field);

        if (($unsigned & 0x80000000) === 0) {
            return $unsigned;
        }

        return -((~$unsigned & 0xFFFFFFFF) + 1);
    }
}
