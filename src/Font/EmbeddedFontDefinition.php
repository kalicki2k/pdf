<?php

declare(strict_types=1);

namespace Kalle\Pdf\Font;

use InvalidArgumentException;

final readonly class EmbeddedFontDefinition
{
    private function __construct(
        public EmbeddedFontSource $source,
        public OpenTypeFontParser $parser,
        public EmbeddedFontMetadata $metadata,
    ) {
        if ($this->metadata->outlineType !== OpenTypeOutlineType::TRUE_TYPE) {
            throw new InvalidArgumentException('Phase 1 only supports embedded TrueType outlines.');
        }
    }

    public static function fromSource(EmbeddedFontSource $source): self
    {
        $parser = new OpenTypeFontParser($source);

        return new self(
            source: $source,
            parser: $parser,
            metadata: $parser->metadata(),
        );
    }

    public function supportsText(string $text): bool
    {
        $encoded = mb_convert_encoding($text, 'Windows-1252', 'UTF-8');
        $roundTrip = mb_convert_encoding($encoded, 'UTF-8', 'Windows-1252');

        if ($roundTrip !== $text) {
            return false;
        }

        foreach (preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $character) {
            if ($this->parser->getGlyphIdForCharacter($character) === 0) {
                return false;
            }
        }

        return true;
    }

    public function encodeText(string $text): string
    {
        if (!$this->supportsText($text)) {
            throw new InvalidArgumentException(sprintf(
                "Text cannot be encoded with embedded font '%s'.",
                $this->metadata->postScriptName,
            ));
        }

        return mb_convert_encoding($text, 'Windows-1252', 'UTF-8');
    }

    public function measureTextWidth(string $text, float $fontSize): float
    {
        if ($text === '') {
            return 0.0;
        }

        $width = 0;

        foreach (preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $character) {
            $glyphId = $this->parser->getGlyphIdForCharacter($character);
            $width += $this->parser->getAdvanceWidthForGlyphId($glyphId);
        }

        return ($width / $this->metadata->unitsPerEm) * $fontSize;
    }

    /**
     * @return list<?string>
     */
    public function glyphNamesForText(string $text): array
    {
        return [];
    }

    public function kerningValue(string $leftGlyph, string $rightGlyph): int
    {
        return 0;
    }

    /**
     * @return array<int, int>
     */
    public function widthsByCharacterCode(): array
    {
        $widths = [];

        for ($code = 32; $code <= 255; $code++) {
            $character = mb_convert_encoding(chr($code), 'UTF-8', 'Windows-1252');
            $glyphId = $this->parser->getGlyphIdForCharacter($character);
            $widths[$code] = $this->parser->getAdvanceWidthForGlyphId($glyphId);
        }

        return $widths;
    }

    public function fontFileStreamContents(): string
    {
        $data = $this->source->data;

        return '<< /Length ' . strlen($data) . ' /Length1 ' . strlen($data) . " >>\nstream\n"
            . $data
            . "\nendstream";
    }

    public function fontDescriptorContents(int $fontFileObjectId): string
    {
        $flags = 32 | ($this->metadata->italicAngle !== 0.0 ? 64 : 0);
        $bbox = $this->metadata->fontBoundingBox;

        return '<< /Type /FontDescriptor'
            . ' /FontName /' . $this->metadata->postScriptName
            . ' /Flags ' . $flags
            . ' /FontBBox [' . $bbox->left . ' ' . $bbox->bottom . ' ' . $bbox->right . ' ' . $bbox->top . ']'
            . ' /ItalicAngle ' . $this->formatNumber($this->metadata->italicAngle)
            . ' /Ascent ' . $this->metadata->ascent
            . ' /Descent ' . $this->metadata->descent
            . ' /CapHeight ' . $this->metadata->capHeight
            . ' /StemV 80'
            . ' /FontFile2 ' . $fontFileObjectId . ' 0 R'
            . ' >>';
    }

    public function fontObjectContents(int $fontDescriptorObjectId): string
    {
        $widths = [];

        foreach ($this->widthsByCharacterCode() as $code => $width) {
            if ($code < 32) {
                continue;
            }

            $widths[] = (string) $width;
        }

        return '<< /Type /Font'
            . ' /Subtype /TrueType'
            . ' /BaseFont /' . $this->metadata->postScriptName
            . ' /FirstChar 32'
            . ' /LastChar 255'
            . ' /Widths [' . implode(' ', $widths) . ']'
            . ' /Encoding /WinAnsiEncoding'
            . ' /FontDescriptor ' . $fontDescriptorObjectId . ' 0 R'
            . ' >>';
    }

    private function formatNumber(float $value): string
    {
        $formatted = number_format($value, 3, '.', '');

        return rtrim(rtrim($formatted, '0'), '.');
    }
}
