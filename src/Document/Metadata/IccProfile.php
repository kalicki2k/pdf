<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document\Metadata;

use InvalidArgumentException;

use function dirname;
use function file_get_contents;
use function is_string;
use function sprintf;
use function strlen;

final readonly class IccProfile
{
    public function __construct(
        private string $data,
        private int $colorComponents = 3,
    ) {
        if ($this->colorComponents < 1) {
            throw new InvalidArgumentException('ICC profiles require at least one color component.');
        }
    }

    public static function defaultSrgbPath(): string
    {
        return dirname(__DIR__, 3) . '/assets/color/icc/sRGB.icc';
    }

    public static function fromPath(string $path, int $colorComponents = 3): self
    {
        $data = @file_get_contents($path);

        if (!is_string($data)) {
            throw new InvalidArgumentException(sprintf("Unable to read ICC profile '%s'.", $path));
        }

        return new self($data, $colorComponents);
    }

    public function objectContents(): string
    {
        return $this->streamDictionaryContents() . "\nstream\n"
            . $this->streamContents()
            . "\nendstream";
    }

    public function streamDictionaryContents(): string
    {
        return '<< /N ' . $this->colorComponents . ' /Length ' . strlen($this->data) . ' >>';
    }

    public function streamContents(): string
    {
        return $this->data;
    }
}
