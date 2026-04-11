<?php

declare(strict_types=1);

namespace Kalle\Pdf\Font;

use InvalidArgumentException;

final readonly class StandardFontDefinition
{
    private function __construct(
        public string $name,
    ) {
    }

    public static function from(string|StandardFont $font): self
    {
        $fontName = $font instanceof StandardFont
            ? $font->value
            : $font;

        if (!StandardFont::isValid($fontName)) {
            throw new InvalidArgumentException(sprintf(
                "Font '%s' is not a valid PDF standard font.",
                $fontName,
            ));
        }

        return new self($fontName);
    }

    public function resolveEncoding(float $pdfVersion, ?StandardFontEncoding $preferredEncoding = null): StandardFontEncoding
    {
        return StandardFontEncoding::forFont($this->name, $pdfVersion, $preferredEncoding);
    }

    public function supportsText(string $text, float $pdfVersion, ?StandardFontEncoding $preferredEncoding = null): bool
    {
        return $this->resolveEncoding($pdfVersion, $preferredEncoding)->supportsText($text);
    }

    public function encodeText(string $text, float $pdfVersion, ?StandardFontEncoding $preferredEncoding = null): string
    {
        return $this->resolveEncoding($pdfVersion, $preferredEncoding)->encodeText($text);
    }

    public function measureTextWidth(string $text, float $fontSize): float
    {
        $width = StandardFontMetrics::measureTextWidth($this->name, $text, $fontSize);

        if ($width === null) {
            throw new InvalidArgumentException(sprintf(
                "Unable to measure text width for font '%s'.",
                $this->name,
            ));
        }

        return $width;
    }

    public function pdfEncodingObjectValue(StandardFontEncoding $encoding): string
    {
        return $encoding->pdfObjectValue($this->name);
    }
}
