<?php

declare(strict_types=1);

namespace Kalle\Pdf\Page;

use JsonException;
use Kalle\Pdf\Font\StandardFontDefinition;
use Kalle\Pdf\Font\StandardFontEncoding;

final readonly class PageFont
{
    /**
     * @param array<int, string> $differences
     */
    public function __construct(
        public string $name,
        public StandardFontEncoding $encoding,
        public array $differences = [],
    ) {
        StandardFontDefinition::from($this->name);
    }

    public function definition(): StandardFontDefinition
    {
        return StandardFontDefinition::from($this->name);
    }

    public function matches(string $fontName, StandardFontEncoding $encoding, array $differences = []): bool
    {
        return $this->name === $fontName
            && $this->encoding === $encoding
            && $this->differences === $differences;
    }

    /**
     * Returns a stable deduplication key for font resources on pages and in the serialization plan.
     */
    public function key(): string
    {
        try {
            return $this->name . '|' . $this->encoding->value . '|' . json_encode($this->differences, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new \RuntimeException('Unable to build a stable page font key.', previous: $exception);
        }
    }

    public function pdfObjectContents(): string
    {
        $font = $this->definition();

        return '<< /Type /Font /Subtype /Type1 /BaseFont /' . $font->name
            . ' /Encoding ' . $this->encoding->pdfObjectValueWithDifferences($font->name, $this->differences)
            . ' >>';
    }
}
