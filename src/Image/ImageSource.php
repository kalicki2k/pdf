<?php

declare(strict_types=1);

namespace Kalle\Pdf\Image;

use InvalidArgumentException;

use function file_get_contents;
use function get_debug_type;
use function hash;
use function implode;
use function is_file;
use function is_scalar;
use function strlen;

use RuntimeException;

final readonly class ImageSource
{
    /**
     * @param list<string> $additionalDictionaryEntries
     */
    public function __construct(
        public int $width,
        public int $height,
        public ImageColorSpace $colorSpace,
        public int $bitsPerComponent,
        public string $data,
        public ?string $colorSpaceDefinition = null,
        public ?string $filter = null,
        public ?self $softMask = null,
        public array $additionalDictionaryEntries = [],
    ) {
        if ($this->width <= 0) {
            throw new InvalidArgumentException('Image width must be greater than 0.');
        }

        if ($this->height <= 0) {
            throw new InvalidArgumentException('Image height must be greater than 0.');
        }

        if ($this->bitsPerComponent <= 0) {
            throw new InvalidArgumentException('Bits per component must be greater than 0.');
        }

        if ($this->softMask !== null && $this->softMask->colorSpace !== ImageColorSpace::GRAY) {
            throw new InvalidArgumentException('Soft mask images must use the gray color space.');
        }
    }

    public static function jpeg(
        string $data,
        int $width,
        int $height,
        ImageColorSpace $colorSpace = ImageColorSpace::RGB,
    ): self {
        return new self(
            width: $width,
            height: $height,
            colorSpace: $colorSpace,
            colorSpaceDefinition: null,
            bitsPerComponent: 8,
            data: $data,
            filter: '/DCTDecode',
        );
    }

    public static function fromPath(string $path): self
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

        $imageInfo = getimagesizefromstring($data);

        if ($imageInfo === false) {
            throw new InvalidArgumentException(sprintf(
                "Image path '%s' uses an unsupported image format.",
                $path,
            ));
        }

        return match ($imageInfo[2]) {
            IMAGETYPE_JPEG => self::jpeg(
                data: $data,
                width: $imageInfo[0],
                height: $imageInfo[1],
                colorSpace: self::jpegColorSpaceFromImageInfo($path, $imageInfo),
            ),
            IMAGETYPE_PNG => (new PngImageDecoder())->decode($data, $path),
            default => throw new InvalidArgumentException(sprintf(
                "Image path '%s' uses an unsupported image format.",
                $path,
            )),
        };
    }

    public static function flate(
        string $data,
        int $width,
        int $height,
        ImageColorSpace $colorSpace,
        int $bitsPerComponent = 8,
        ?self $softMask = null,
    ): self {
        return new self(
            width: $width,
            height: $height,
            colorSpace: $colorSpace,
            colorSpaceDefinition: null,
            bitsPerComponent: $bitsPerComponent,
            data: $data,
            filter: '/FlateDecode',
            softMask: $softMask,
        );
    }

    public static function alphaMask(string $data, int $width, int $height, int $bitsPerComponent = 8): self
    {
        return new self(
            width: $width,
            height: $height,
            colorSpace: ImageColorSpace::GRAY,
            colorSpaceDefinition: null,
            bitsPerComponent: $bitsPerComponent,
            data: $data,
            filter: '/FlateDecode',
        );
    }

    public static function indexed(
        string $data,
        int $width,
        int $height,
        int $bitsPerComponent,
        string $lookupTable,
        ?self $softMask = null,
    ): self {
        $paletteEntryCount = intdiv(strlen($lookupTable), 3);

        if ($lookupTable === '' || ($paletteEntryCount * 3) !== strlen($lookupTable)) {
            throw new InvalidArgumentException('Indexed image lookup tables must contain RGB triplets.');
        }

        return new self(
            width: $width,
            height: $height,
            colorSpace: ImageColorSpace::RGB,
            colorSpaceDefinition: '[/Indexed /DeviceRGB ' . ($paletteEntryCount - 1) . ' ' . self::pdfHexString($lookupTable) . ']',
            bitsPerComponent: $bitsPerComponent,
            data: $data,
            softMask: $softMask,
            filter: '/FlateDecode',
        );
    }

    public function key(): string
    {
        return hash('sha256', implode("\0", [
            (string) $this->width,
            (string) $this->height,
            $this->colorSpace->value,
            $this->colorSpaceDefinition ?? '',
            (string) $this->bitsPerComponent,
            $this->filter ?? '',
            $this->data,
            $this->softMask?->key() ?? '',
            implode("\0", $this->additionalDictionaryEntries),
        ]));
    }

    public function pdfObjectDictionaryContents(?int $softMaskObjectId = null): string
    {
        $entries = [
            '/Type /XObject',
            '/Subtype /Image',
            '/Width ' . $this->width,
            '/Height ' . $this->height,
            '/ColorSpace ' . ($this->colorSpaceDefinition ?? $this->colorSpace->pdfName()),
            '/BitsPerComponent ' . $this->bitsPerComponent,
        ];

        if ($this->filter !== null) {
            $entries[] = '/Filter ' . $this->filter;
        }

        if ($softMaskObjectId !== null) {
            $entries[] = '/SMask ' . $softMaskObjectId . ' 0 R';
        }

        foreach ($this->additionalDictionaryEntries as $entry) {
            $entries[] = $entry;
        }

        $entries[] = '/Length ' . strlen($this->data);

        return '<< ' . implode(' ', $entries) . ' >>';
    }

    public function pdfObjectStreamContents(): string
    {
        return $this->data;
    }

    public function pdfObjectContents(?int $softMaskObjectId = null): string
    {
        return $this->pdfObjectDictionaryContents($softMaskObjectId)
            . "\nstream\n"
            . $this->pdfObjectStreamContents()
            . "\nendstream";
    }

    /**
     * @param array<string|int, mixed> $imageInfo
     */
    private static function jpegColorSpaceFromImageInfo(string $path, array $imageInfo): ImageColorSpace
    {
        $channels = $imageInfo['channels'] ?? null;

        return match ($channels) {
            1 => ImageColorSpace::GRAY,
            3, null => ImageColorSpace::RGB,
            4 => ImageColorSpace::CMYK,
            default => throw new InvalidArgumentException(sprintf(
                "JPEG image '%s' uses unsupported channel count '%s'.",
                $path,
                is_scalar($channels) ? (string) $channels : get_debug_type($channels),
            )),
        };
    }

    private static function pdfHexString(string $bytes): string
    {
        return '<' . strtoupper(bin2hex($bytes)) . '>';
    }
}
