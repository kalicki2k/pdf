<?php

declare(strict_types=1);

namespace Kalle\Pdf\Image;

use function array_map;
use function count;
use function file_get_contents;
use function get_debug_type;
use function hash;
use function implode;
use function is_file;
use function is_scalar;
use function json_encode;
use function strlen;

use InvalidArgumentException;
use RuntimeException;

final readonly class ImageSource
{
    public int $width;
    public int $height;
    public ImageColorSpace $colorSpace;
    public int $bitsPerComponent;
    public string $data;
    public ?string $colorSpaceDefinition;
    public ?string $filter;
    public ?self $softMask;
    /**
     * @var list<string>
     */
    public array $additionalDictionaryEntries;
    /**
     * @var list<PdfFilter>
     */
    public array $filters;

    /**
     * @param list<string> $additionalDictionaryEntries
     * @param list<PdfFilter> $filters
     */
    public function __construct(
        int $width,
        int $height,
        ImageColorSpace $colorSpace,
        int $bitsPerComponent,
        string $data,
        ?string $colorSpaceDefinition = null,
        ?string $filter = null,
        ?self $softMask = null,
        array $additionalDictionaryEntries = [],
        array $filters = [],
    ) {
        if ($filters !== [] && $filter !== null) {
            throw new InvalidArgumentException('Image sources must define either a legacy filter or filter objects, not both.');
        }

        if ($filters === [] && $filter !== null) {
            $filters = [PdfFilter::named($filter)];
        }

        $this->width = $width;
        $this->height = $height;
        $this->colorSpace = $colorSpace;
        $this->bitsPerComponent = $bitsPerComponent;
        $this->data = $data;
        $this->colorSpaceDefinition = $colorSpaceDefinition;
        $this->softMask = $softMask;
        $this->additionalDictionaryEntries = $additionalDictionaryEntries;
        $this->filters = array_values($filters);
        $this->filter = count($this->filters) === 1 ? $this->filters[0]->name : null;

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
            filters: [PdfFilter::dct()],
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
            IMAGETYPE_PNG => new PngImageDecoder()->decode($data, $path),
            IMAGETYPE_TIFF_II, IMAGETYPE_TIFF_MM => new TiffImageDecoder()->decode($data, $path),
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
            softMask: $softMask,
            filters: [PdfFilter::flate()],
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
            filters: [PdfFilter::flate()],
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
            filters: [PdfFilter::flate()],
        );
    }

    public static function lzw(
        string $data,
        int $width,
        int $height,
        ImageColorSpace $colorSpace,
        int $bitsPerComponent = 8,
        array $decodeParameters = ['EarlyChange' => 1],
        ?self $softMask = null,
    ): self {
        return new self(
            width: $width,
            height: $height,
            colorSpace: $colorSpace,
            colorSpaceDefinition: null,
            bitsPerComponent: $bitsPerComponent,
            data: $data,
            softMask: $softMask,
            filters: [PdfFilter::lzw($decodeParameters)],
        );
    }

    public static function lzwCompressed(
        string $data,
        int $width,
        int $height,
        ImageColorSpace $colorSpace,
        int $bitsPerComponent = 8,
        ?self $softMask = null,
    ): self {
        return self::lzw(
            data: (new LzwEncoder())->encode($data),
            width: $width,
            height: $height,
            colorSpace: $colorSpace,
            bitsPerComponent: $bitsPerComponent,
            softMask: $softMask,
        );
    }

    public static function runLength(
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
            softMask: $softMask,
            filters: [PdfFilter::runLength()],
        );
    }

    public static function runLengthCompressed(
        string $data,
        int $width,
        int $height,
        ImageColorSpace $colorSpace,
        int $bitsPerComponent = 8,
        ?self $softMask = null,
    ): self {
        return self::runLength(
            data: (new RunLengthEncoder())->encode($data),
            width: $width,
            height: $height,
            colorSpace: $colorSpace,
            bitsPerComponent: $bitsPerComponent,
            softMask: $softMask,
        );
    }

    public static function ccittFax(
        string $data,
        int $width,
        int $height,
        int $k = 0,
        bool $blackIs1 = false,
        bool $encodedByteAlign = false,
        bool $endOfLine = false,
        bool $endOfBlock = true,
    ): self {
        return new self(
            width: $width,
            height: $height,
            colorSpace: ImageColorSpace::GRAY,
            colorSpaceDefinition: null,
            bitsPerComponent: 1,
            data: $data,
            filters: [
                PdfFilter::ccittFax(
                    columns: $width,
                    rows: $height,
                    k: $k,
                    blackIs1: $blackIs1,
                    encodedByteAlign: $encodedByteAlign,
                    endOfLine: $endOfLine,
                    endOfBlock: $endOfBlock,
                ),
            ],
        );
    }

    public static function compressed(
        string $data,
        int $width,
        int $height,
        ImageColorSpace $colorSpace,
        int $bitsPerComponent = 8,
        ?self $softMask = null,
    ): self {
        return (new ImageCompressionSelector())->select(
            data: $data,
            width: $width,
            height: $height,
            colorSpace: $colorSpace,
            bitsPerComponent: $bitsPerComponent,
            softMask: $softMask,
        );
    }

    /**
     * @param list<string> $rows
     */
    public static function monochrome(array $rows): self
    {
        $bitmap = (new MonochromeBitmapEncoder())->encodeRows($rows);

        return self::compressed(
            data: $bitmap->data,
            width: $bitmap->width,
            height: $bitmap->height,
            colorSpace: ImageColorSpace::GRAY,
            bitsPerComponent: 1,
        );
    }

    /**
     * @param list<string> $rows
     */
    public static function monochromeCcitt(array $rows): self
    {
        $bitmap = (new MonochromeBitmapEncoder())->encodeRows($rows);

        return self::ccittFax(
            data: (new CcittFaxEncoder())->encodeBitmap($bitmap->data, $bitmap->width, $bitmap->height),
            width: $bitmap->width,
            height: $bitmap->height,
            k: 0,
            blackIs1: true,
            endOfLine: true,
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
            json_encode(array_map(
                static fn (PdfFilter $filter): array => [
                    'name' => $filter->name,
                    'decodeParameters' => $filter->decodeParameters,
                ],
                $this->filters,
            )) ?: '',
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

        $filterEntry = PdfFilter::pdfFilterEntry($this->filters);

        if ($filterEntry !== null) {
            $entries[] = '/Filter ' . $filterEntry;
        }

        $decodeParmsEntry = PdfFilter::pdfDecodeParmsEntry($this->filters);

        if ($decodeParmsEntry !== null) {
            $entries[] = '/DecodeParms ' . $decodeParmsEntry;
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
