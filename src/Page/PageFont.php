<?php

declare(strict_types=1);

namespace Kalle\Pdf\Page;

use JsonException;
use Kalle\Pdf\Font\EmbeddedFontDefinition;
use Kalle\Pdf\Font\EmbeddedFontSource;
use Kalle\Pdf\Font\StandardFontDefinition;
use Kalle\Pdf\Font\StandardFontEncoding;
use RuntimeException;

final readonly class PageFont
{
    /**
     * @param array<int, string> $differences
     */
    public function __construct(
        public string $name,
        public ?StandardFontEncoding $encoding,
        public array $differences = [],
        public ?EmbeddedFontSource $embeddedSource = null,
    ) {
        if ($this->embeddedSource === null) {
            StandardFontDefinition::from($this->name);
        }
    }

    public static function embedded(EmbeddedFontDefinition $font): self
    {
        return new self(
            name: $font->metadata->postScriptName,
            encoding: null,
            embeddedSource: $font->source,
        );
    }

    public function definition(): StandardFontDefinition
    {
        return StandardFontDefinition::from($this->name);
    }

    public function embeddedDefinition(): EmbeddedFontDefinition
    {
        if ($this->embeddedSource === null) {
            throw new RuntimeException('Page font does not reference an embedded font source.');
        }

        return EmbeddedFontDefinition::fromSource($this->embeddedSource);
    }

    public function isEmbedded(): bool
    {
        return $this->embeddedSource !== null;
    }

    /**
     * @param array<int, string> $differences
     */
    public function matches(string $fontName, StandardFontEncoding $encoding, array $differences = []): bool
    {
        if ($this->isEmbedded()) {
            return false;
        }

        return $this->name === $fontName
            && $this->encoding === $encoding
            && $this->differences === $differences;
    }

    public function matchesEmbedded(EmbeddedFontDefinition $font): bool
    {
        return $this->embeddedSource !== null
            && $this->name === $font->metadata->postScriptName
            && $this->embeddedSource->data === $font->source->data;
    }

    /**
     * Returns a stable deduplication key for font resources on pages and in the serialization plan.
     */
    public function key(): string
    {
        try {
            if ($this->embeddedSource !== null) {
                return 'embedded|' . $this->name . '|' . sha1($this->embeddedSource->data);
            }

            if ($this->encoding === null) {
                throw new RuntimeException('Standard page font encoding must not be null.');
            }

            return $this->name . '|' . $this->encoding->value . '|' . json_encode($this->differences, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Unable to build a stable page font key.', previous: $exception);
        }
    }

    public function pdfObjectContents(): string
    {
        if ($this->isEmbedded()) {
            throw new RuntimeException('Embedded page fonts require descriptor and font file objects.');
        }

        $font = $this->definition();

        return '<< /Type /Font /Subtype /Type1 /BaseFont /' . $font->name
            . ' /Encoding ' . $this->encoding?->pdfObjectValueWithDifferences($font->name, $this->differences)
            . ' >>';
    }
}
