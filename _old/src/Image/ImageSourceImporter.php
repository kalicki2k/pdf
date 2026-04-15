<?php

declare(strict_types=1);

namespace Kalle\Pdf\Image;

use function file_get_contents;
use function is_file;
use function is_string;
use function sprintf;

use InvalidArgumentException;
use RuntimeException;

final readonly class ImageSourceImporter
{
    public function __construct(
        private ImageFormatSniffer $formatSniffer = new ImageFormatSniffer(),
        private JpegImageDecoder $jpegDecoder = new JpegImageDecoder(),
        private PngImageDecoder $pngDecoder = new PngImageDecoder(),
        private TiffImageDecoder $tiffDecoder = new TiffImageDecoder(),
        private GifImageDecoder $gifDecoder = new GifImageDecoder(),
        private BmpImageDecoder $bmpDecoder = new BmpImageDecoder(),
        private WebpImageDecoder $webpDecoder = new WebpImageDecoder(),
    ) {
    }

    public function fromPath(string $path): ImageSource
    {
        if ($path === '' || !is_file($path)) {
            throw new InvalidArgumentException(sprintf(
                "Image path '%s' does not point to a readable file.",
                $path,
            ));
        }

        $data = file_get_contents($path);

        if (!is_string($data)) {
            throw new RuntimeException(sprintf(
                "Unable to read image data from '%s'.",
                $path,
            ));
        }

        ['format' => $format, 'imageInfo' => $imageInfo] = $this->formatSniffer->sniff($data, $path);

        return match ($format) {
            ImageFormat::JPEG => $this->jpegDecoder->decode($data, $imageInfo, $path),
            ImageFormat::PNG => $this->pngDecoder->decode($data, $path),
            ImageFormat::TIFF => $this->tiffDecoder->decode($data, $path),
            ImageFormat::GIF => $this->gifDecoder->decode($data, $path),
            ImageFormat::BMP => $this->bmpDecoder->decode($data, $path),
            ImageFormat::WEBP => $this->webpDecoder->decode($data, $path),
        };
    }
}
