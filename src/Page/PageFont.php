<?php

declare(strict_types=1);

namespace Kalle\Pdf\Page;

use JsonException;
use function array_values;
use function chr;
use function json_encode;
use function ord;
use function preg_split;
use function sha1;
use function strlen;

use Kalle\Pdf\Font\EmbeddedFontDefinition;
use Kalle\Pdf\Font\EmbeddedFontSource;
use Kalle\Pdf\Font\StandardFontDefinition;
use Kalle\Pdf\Font\StandardFontEncoding;
use RuntimeException;

final readonly class PageFont
{
    /**
     * @param array<int, string> $differences
     * @param list<int> $unicodeCodePoints
     */
    public function __construct(
        public string $name,
        public ?StandardFontEncoding $encoding,
        public array $differences = [],
        public ?EmbeddedFontSource $embeddedSource = null,
        public bool $usesUnicodeCids = false,
        public array $unicodeCodePoints = [],
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

    /**
     * @param list<int> $unicodeCodePoints
     */
    public static function embeddedUnicode(EmbeddedFontDefinition $font, array $unicodeCodePoints): self
    {
        return new self(
            name: $font->metadata->postScriptName,
            encoding: null,
            embeddedSource: $font->source,
            usesUnicodeCids: true,
            unicodeCodePoints: self::normalizeUnicodeCodePoints($unicodeCodePoints),
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

    public function usesUnicodeCids(): bool
    {
        return $this->usesUnicodeCids;
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

    public function matchesEmbedded(EmbeddedFontDefinition $font, bool $usesUnicodeCids = false): bool
    {
        return $this->embeddedSource !== null
            && $this->name === $font->metadata->postScriptName
            && $this->embeddedSource->data === $font->source->data
            && $this->usesUnicodeCids === $usesUnicodeCids;
    }

    /**
     * @param list<int> $unicodeCodePoints
     */
    public function withAdditionalUnicodeCodePoints(array $unicodeCodePoints): self
    {
        if (!$this->usesUnicodeCids) {
            throw new RuntimeException('Cannot add Unicode CIDs to a simple page font resource.');
        }

        return new self(
            name: $this->name,
            encoding: $this->encoding,
            differences: $this->differences,
            embeddedSource: $this->embeddedSource,
            usesUnicodeCids: true,
            unicodeCodePoints: self::normalizeUnicodeCodePoints([
                ...$this->unicodeCodePoints,
                ...$unicodeCodePoints,
            ]),
        );
    }

    /**
     * Returns a stable deduplication key for font resources on pages and in the serialization plan.
     */
    public function key(): string
    {
        try {
            if ($this->embeddedSource !== null) {
                return ($this->usesUnicodeCids ? 'embedded-unicode|' : 'embedded|')
                    . $this->name . '|' . sha1($this->embeddedSource->data) . '|'
                    . json_encode($this->unicodeCodePoints, JSON_THROW_ON_ERROR);
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

    public function encodeUnicodeText(string $text): string
    {
        if (!$this->usesUnicodeCids) {
            throw new RuntimeException('Page font is not configured for Unicode CID encoding.');
        }

        $encoded = '';

        foreach (preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $character) {
            $codePoint = mb_ord($character, 'UTF-8');
            $cid = $this->unicodeCidForCodePoint($codePoint);

            if ($cid === null) {
                throw new RuntimeException('Unicode code point is not present in the page font subset.');
            }

            $encoded .= pack('n', $cid);
        }

        return $encoded;
    }

    /**
     * @return array<int, int>
     */
    public function unicodeCidMap(): array
    {
        if (!$this->usesUnicodeCids) {
            return [];
        }

        $map = [];

        foreach ($this->unicodeCodePoints as $index => $codePoint) {
            $map[$codePoint] = $index + 1;
        }

        return $map;
    }

    public function unicodeCidForCodePoint(int $codePoint): ?int
    {
        return $this->unicodeCidMap()[$codePoint] ?? null;
    }

    /**
     * @param list<int> $unicodeCodePoints
     * @return list<int>
     */
    private static function normalizeUnicodeCodePoints(array $unicodeCodePoints): array
    {
        $normalized = [];

        foreach ($unicodeCodePoints as $codePoint) {
            if (isset($normalized[$codePoint])) {
                continue;
            }

            $normalized[$codePoint] = $codePoint;
        }

        return array_values($normalized);
    }
}
