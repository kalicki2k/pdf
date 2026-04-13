<?php

declare(strict_types=1);

namespace Kalle\Pdf\Image;

use function gzcompress;
use function is_string;
use function strlen;

use RuntimeException;

final readonly class ImageCompressionSelector
{
    public function __construct(
        private RunLengthEncoder $runLengthEncoder = new RunLengthEncoder(),
        private LzwEncoder $lzwEncoder = new LzwEncoder(),
    ) {
    }

    public function select(
        string $data,
        int $width,
        int $height,
        ImageColorSpace $colorSpace,
        int $bitsPerComponent = 8,
        ?ImageSource $softMask = null,
    ): ImageSource {
        $flateData = gzcompress($data);

        if (!is_string($flateData)) {
            throw new RuntimeException('Unable to compress raster image data with Flate.');
        }

        $candidates = [
            ImageSource::flate($flateData, $width, $height, $colorSpace, $bitsPerComponent, $softMask),
            ImageSource::runLength(
                $this->runLengthEncoder->encode($data),
                $width,
                $height,
                $colorSpace,
                $bitsPerComponent,
                $softMask,
            ),
            ImageSource::lzw(
                $this->lzwEncoder->encode($data),
                $width,
                $height,
                $colorSpace,
                $bitsPerComponent,
                softMask: $softMask,
            ),
        ];

        $selected = $candidates[0];

        foreach ($candidates as $candidate) {
            if (strlen($candidate->data) < strlen($selected->data)) {
                $selected = $candidate;
            }
        }

        return $selected;
    }
}
