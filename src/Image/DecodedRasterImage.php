<?php

declare(strict_types=1);

namespace Kalle\Pdf\Image;

use function gzcompress;
use function is_string;
use function sprintf;

use InvalidArgumentException;
use RuntimeException;

final readonly class DecodedRasterImage
{
    public function __construct(
        public int $width,
        public int $height,
        public ImageColorSpace $colorSpace,
        public int $bitsPerComponent,
        public string $pixelData,
        public ?string $alphaData = null,
        public ?string $lookupTable = null,
    ) {
        if ($this->width <= 0 || $this->height <= 0) {
            throw new InvalidArgumentException('Decoded raster dimensions must be greater than 0.');
        }

        if ($this->bitsPerComponent <= 0) {
            throw new InvalidArgumentException('Decoded raster bits per component must be greater than 0.');
        }

        if ($this->lookupTable !== null && $this->lookupTable === '') {
            throw new InvalidArgumentException('Decoded indexed rasters require a non-empty lookup table.');
        }
    }

    public function toImageSource(string $path = 'memory'): ImageSource
    {
        $compressedPixelData = gzcompress($this->pixelData);

        if (!is_string($compressedPixelData)) {
            throw new RuntimeException(sprintf(
                "Unable to compress decoded raster image '%s'.",
                $path,
            ));
        }

        $softMask = null;

        if ($this->alphaData !== null) {
            $compressedAlphaData = gzcompress($this->alphaData);

            if (!is_string($compressedAlphaData)) {
                throw new RuntimeException(sprintf(
                    "Unable to compress decoded raster alpha channel for '%s'.",
                    $path,
                ));
            }

            $softMask = ImageSource::alphaMask(
                data: $compressedAlphaData,
                width: $this->width,
                height: $this->height,
                bitsPerComponent: $this->bitsPerComponent,
            );
        }

        if ($this->lookupTable !== null) {
            return ImageSource::indexed(
                data: $compressedPixelData,
                width: $this->width,
                height: $this->height,
                bitsPerComponent: $this->bitsPerComponent,
                lookupTable: $this->lookupTable,
                softMask: $softMask,
            );
        }

        return ImageSource::flate(
            data: $compressedPixelData,
            width: $this->width,
            height: $this->height,
            colorSpace: $this->colorSpace,
            bitsPerComponent: $this->bitsPerComponent,
            softMask: $softMask,
        );
    }
}
