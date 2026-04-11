<?php

declare(strict_types=1);

namespace Kalle\Pdf\Font;

use InvalidArgumentException;

use function array_key_exists;
use function array_values;
use function bin2hex;
use function count;
use function dechex;
use function implode;
use function mb_chr;
use function mb_convert_encoding;
use function mb_ord;
use function preg_split;
use function sprintf;
use function str_pad;
use function strlen;
use function strtoupper;

final readonly class EmbeddedFontDefinition
{
    private function __construct(
        public EmbeddedFontSource $source,
        public OpenTypeFontParser $parser,
        public EmbeddedFontMetadata $metadata,
    ) {
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

    public function supportsUnicodeText(string $text): bool
    {
        foreach (preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $character) {
            $codePoint = mb_ord($character, 'UTF-8');

            if ($this->parser->getGlyphIdForCodePoint($codePoint) === 0) {
                return false;
            }
        }

        return true;
    }

    public function encodeUnicodeText(string $text): string
    {
        if (!$this->supportsUnicodeText($text)) {
            throw new InvalidArgumentException(sprintf(
                "Text cannot be encoded as Unicode with embedded font '%s'.",
                $this->metadata->postScriptName,
            ));
        }

        return mb_convert_encoding($text, 'UTF-16BE', 'UTF-8');
    }

    public function encodeUnicodeGlyphIdsText(string $text): string
    {
        if (!$this->supportsUnicodeText($text)) {
            throw new InvalidArgumentException(sprintf(
                "Text cannot be encoded as Unicode with embedded font '%s'.",
                $this->metadata->postScriptName,
            ));
        }

        $encoded = '';

        foreach (preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $character) {
            $encoded .= pack('n', $this->parser->getGlyphIdForCharacter($character));
        }

        return $encoded;
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
     * @return list<int>
     */
    public function unicodeCodePointsForText(string $text): array
    {
        $codePoints = [];

        foreach (preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $character) {
            $codePoint = mb_ord($character, 'UTF-8');

            if (!array_key_exists($codePoint, $codePoints)) {
                $codePoints[$codePoint] = $codePoint;
            }
        }

        return array_values($codePoints);
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

        return match ($this->metadata->outlineType) {
            OpenTypeOutlineType::TRUE_TYPE => '<< /Length ' . strlen($data) . ' /Length1 ' . strlen($data) . " >>\nstream\n"
                . $data
                . "\nendstream",
            OpenTypeOutlineType::CFF => '<< /Length ' . strlen($data) . ' /Subtype /OpenType >>' . "\nstream\n"
                . $data
                . "\nendstream",
        };
    }

    /**
     * @param list<int> $codePoints
     */
    public function unicodeSubsetFontFileStreamContents(array $codePoints): string
    {
        if ($this->metadata->outlineType === OpenTypeOutlineType::CFF) {
            $data = new OpenTypeCffSubsetter($this->parser)->subset(
                $codePoints,
                $this->subsetPostScriptName($codePoints),
            );

            return '<< /Length ' . strlen($data) . ' /Subtype /OpenType >>' . "\nstream\n"
                . $data
                . "\nendstream";
        }

        $glyphIds = [0];

        foreach ($codePoints as $codePoint) {
            $glyphIds[] = $this->parser->getGlyphIdForCodePoint($codePoint);
        }

        $data = new OpenTypeTrueTypeSubsetter($this->parser)->subset($glyphIds);

        return '<< /Length ' . strlen($data) . ' /Length1 ' . strlen($data) . " >>\nstream\n"
            . $data
            . "\nendstream";
    }

    public function fontDescriptorContents(int $fontFileObjectId, ?string $fontName = null): string
    {
        $flags = 32 | ($this->metadata->italicAngle !== 0.0 ? 64 : 0);
        $bbox = $this->metadata->fontBoundingBox;

        return '<< /Type /FontDescriptor'
            . ' /FontName /' . ($fontName ?? $this->metadata->postScriptName)
            . ' /Flags ' . $flags
            . ' /FontBBox [' . $bbox->left . ' ' . $bbox->bottom . ' ' . $bbox->right . ' ' . $bbox->top . ']'
            . ' /ItalicAngle ' . $this->formatNumber($this->metadata->italicAngle)
            . ' /Ascent ' . $this->metadata->ascent
            . ' /Descent ' . $this->metadata->descent
            . ' /CapHeight ' . $this->metadata->capHeight
            . ' /StemV 80'
            . ' /' . ($this->metadata->outlineType === OpenTypeOutlineType::CFF ? 'FontFile3' : 'FontFile2')
            . ' ' . $fontFileObjectId . ' 0 R'
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
            . ' /Subtype /' . ($this->metadata->outlineType === OpenTypeOutlineType::CFF ? 'Type1' : 'TrueType')
            . ' /BaseFont /' . $this->metadata->postScriptName
            . ' /FirstChar 32'
            . ' /LastChar 255'
            . ' /Widths [' . implode(' ', $widths) . ']'
            . ' /Encoding /WinAnsiEncoding'
            . ' /FontDescriptor ' . $fontDescriptorObjectId . ' 0 R'
            . ' >>';
    }

    /**
     * @param list<int> $codePoints
     */
    public function unicodeType0FontObjectContents(int $cidFontObjectId, int $toUnicodeObjectId, array $codePoints): string
    {
        return '<< /Type /Font'
            . ' /Subtype /Type0'
            . ' /BaseFont /' . $this->unicodeBaseFontName($codePoints)
            . ' /Encoding /Identity-H'
            . ' /DescendantFonts [' . $cidFontObjectId . ' 0 R]'
            . ' /ToUnicode ' . $toUnicodeObjectId . ' 0 R'
            . ' >>';
    }

    /**
     * @param list<int> $codePoints
     */
    public function unicodeCidFontObjectContents(int $fontDescriptorObjectId, ?int $cidToGidMapObjectId, array $codePoints): string
    {
        $contents = '<< /Type /Font'
            . ' /Subtype /' . ($this->metadata->outlineType === OpenTypeOutlineType::CFF ? 'CIDFontType0' : 'CIDFontType2')
            . ' /BaseFont /' . $this->unicodeBaseFontName($codePoints)
            . ' /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >>'
            . ' /FontDescriptor ' . $fontDescriptorObjectId . ' 0 R'
            . ' /DW ' . $this->parser->getAdvanceWidthForGlyphId(0)
            . ' /W ' . $this->unicodeWidthsArray($codePoints);

        if ($this->metadata->outlineType === OpenTypeOutlineType::TRUE_TYPE) {
            if ($cidToGidMapObjectId === null) {
                throw new InvalidArgumentException('TrueType CID fonts require a CIDToGIDMap object.');
            }

            $contents .= ' /CIDToGIDMap ' . $cidToGidMapObjectId . ' 0 R';
        }

        return $contents . ' >>';
    }

    /**
     * @param list<int> $codePoints
     */
    public function unicodeCidToGidMapStreamContents(array $codePoints): string
    {
        if ($this->metadata->outlineType === OpenTypeOutlineType::CFF) {
            throw new InvalidArgumentException('CIDToGIDMap is only used for TrueType CID fonts.');
        }

        if ($codePoints === []) {
            return "<< /Length 0 >>\nstream\nendstream";
        }

        $map = '';

        $map .= pack('n', 0);

        foreach ($codePoints as $codePoint) {
            $glyphId = $this->parser->getGlyphIdForCodePoint($codePoint);
            $map .= pack('n', $glyphId);
        }

        return '<< /Length ' . strlen($map) . " >>\nstream\n"
            . $map
            . "\nendstream";
    }

    /**
     * @param list<int> $codePoints
     */
    public function unicodeToUnicodeStreamContents(array $codePoints): string
    {
        $lines = [
            '/CIDInit /ProcSet findresource begin',
            '12 dict begin',
            'begincmap',
            '/CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> def',
            '/CMapName /Adobe-Identity-UCS def',
            '/CMapType 2 def',
            '1 begincodespacerange',
            '<0000> <FFFF>',
            'endcodespacerange',
            count($codePoints) . ' beginbfchar',
        ];

        foreach ($codePoints as $codePoint) {
            $cid = strtoupper(str_pad(dechex($this->unicodeCharCodeForCodePoint($codePoints, $codePoint)), 4, '0', STR_PAD_LEFT));
            $unicode = strtoupper(bin2hex(mb_convert_encoding(mb_chr($codePoint, 'UTF-8'), 'UTF-16BE', 'UTF-8')));
            $lines[] = '<' . $cid . '> <' . $unicode . '>';
        }

        $lines = [
            ...$lines,
            'endbfchar',
            'endcmap',
            'CMapName currentdict /CMap defineresource pop',
            'end',
            'end',
        ];

        $contents = implode("\n", $lines) . "\n";

        return '<< /Length ' . strlen($contents) . " >>\nstream\n"
            . $contents
            . 'endstream';
    }

    /**
     * @param list<int> $codePoints
     */
    private function unicodeWidthsArray(array $codePoints): string
    {
        if ($codePoints === []) {
            return '[]';
        }

        $entries = [];

        foreach ($codePoints as $index => $codePoint) {
            $entries[] = (string) $this->unicodeCharCodeForCodePoint($codePoints, $codePoint) . ' [' . $this->parser->getAdvanceWidthForGlyphId(
                $this->parser->getGlyphIdForCodePoint($codePoint),
            ) . ']';
        }

        return '[' . implode(' ', $entries) . ']';
    }

    /**
     * @param list<int> $codePoints
     */
    public function subsetPostScriptName(array $codePoints): string
    {
        $hash = sha1($this->source->data . '|' . implode(',', $codePoints));
        $prefix = '';

        for ($index = 0; $index < 6; $index++) {
            $prefix .= chr(65 + (hexdec($hash[$index]) % 26));
        }

        return $prefix . '+' . $this->metadata->postScriptName;
    }

    /**
     * @param list<int> $codePoints
     */
    public function unicodeBaseFontName(array $codePoints): string
    {
        return $this->subsetPostScriptName($codePoints);
    }

    /**
     * @param list<int> $codePoints
     */
    private function subsetCidForCodePoint(array $codePoints, int $codePoint): int
    {
        foreach ($codePoints as $index => $candidate) {
            if ($candidate === $codePoint) {
                return $index + 1;
            }
        }

        throw new InvalidArgumentException('Unicode code point is not present in the subset.');
    }

    /**
     * @param list<int> $codePoints
     */
    private function unicodeCharCodeForCodePoint(array $codePoints, int $codePoint): int
    {
        return $this->subsetCidForCodePoint($codePoints, $codePoint);
    }

    private function formatNumber(float $value): string
    {
        $formatted = number_format($value, 3, '.', '');

        return rtrim(rtrim($formatted, '0'), '.');
    }
}
