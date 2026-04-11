<?php

declare(strict_types=1);

namespace Kalle\Pdf\Image;

use InvalidArgumentException;

use function hash;
use function implode;
use function strlen;

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
            bitsPerComponent: 8,
            data: $data,
            filter: '/DCTDecode',
        );
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
            bitsPerComponent: $bitsPerComponent,
            data: $data,
            filter: '/FlateDecode',
        );
    }

    public function key(): string
    {
        return hash('sha256', implode("\0", [
            (string) $this->width,
            (string) $this->height,
            $this->colorSpace->value,
            (string) $this->bitsPerComponent,
            $this->filter ?? '',
            $this->data,
            $this->softMask?->key() ?? '',
            implode("\0", $this->additionalDictionaryEntries),
        ]));
    }

    public function pdfObjectContents(?int $softMaskObjectId = null): string
    {
        $entries = [
            '/Type /XObject',
            '/Subtype /Image',
            '/Width ' . $this->width,
            '/Height ' . $this->height,
            '/ColorSpace ' . $this->colorSpace->pdfName(),
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

        return '<< ' . implode(' ', $entries) . " >>\nstream\n"
            . $this->data
            . "\nendstream";
    }
}
