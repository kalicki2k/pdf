<?php

declare(strict_types=1);

namespace Kalle\Pdf\Font;

use function array_key_exists;
use function array_values;

use function bin2hex;
use function count;
use function dechex;
use function implode;

use InvalidArgumentException;
use Kalle\Pdf\Page\EmbeddedGlyph;

use function mb_chr;
use function mb_convert_encoding;
use function mb_ord;
use function preg_split;
use function sprintf;
use function str_pad;
use function strlen;
use function strtoupper;

use WeakMap;

final class EmbeddedFontDefinition
{
    /**
     * Reuse parsed embedded font metadata for repeated rendering with the same source instance.
     *
     * @var WeakMap<EmbeddedFontSource, self>|null
     */
    private static ?WeakMap $cache = null;

    /** @var array<string, bool> */
    private array $supportsTextCache = [];

    /** @var array<string, bool> */
    private array $supportsUnicodeTextCache = [];

    /** @var array<string, float> */
    private array $textWidthCache = [];

    private function __construct(
        public readonly EmbeddedFontSource $source,
        public readonly OpenTypeFontParser $parser,
        public readonly EmbeddedFontMetadata $metadata,
    ) {
    }

    public static function fromSource(EmbeddedFontSource $source): self
    {
        $cache = self::$cache ??= new WeakMap();

        if (isset($cache[$source])) {
            return $cache[$source];
        }

        $parser = new OpenTypeFontParser($source);

        $definition = new self(
            source: $source,
            parser: $parser,
            metadata: $parser->metadata(),
        );

        $cache[$source] = $definition;

        return $definition;
    }

    public function supportsText(string $text): bool
    {
        if (isset($this->supportsTextCache[$text])) {
            return $this->supportsTextCache[$text];
        }

        $encoded = mb_convert_encoding($text, 'Windows-1252', 'UTF-8');
        $roundTrip = mb_convert_encoding($encoded, 'UTF-8', 'Windows-1252');

        if ($roundTrip !== $text) {
            return $this->supportsTextCache[$text] = false;
        }

        foreach (preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $character) {
            if ($this->parser->getGlyphIdForCharacter($character) === 0) {
                return $this->supportsTextCache[$text] = false;
            }
        }

        return $this->supportsTextCache[$text] = true;
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
        if (isset($this->supportsUnicodeTextCache[$text])) {
            return $this->supportsUnicodeTextCache[$text];
        }

        foreach (preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $character) {
            $codePoint = mb_ord($character, 'UTF-8');

            if ($this->parser->getGlyphIdForCodePoint($codePoint) === 0) {
                return $this->supportsUnicodeTextCache[$text] = false;
            }
        }

        return $this->supportsUnicodeTextCache[$text] = true;
    }

    public function encodeUnicodeText(string $text): string
    {
        return $this->encodeUnicodeCodePoints($this->unicodeCodePointsForText($text));
    }

    /**
     * @param list<int> $codePoints
     */
    public function encodeUnicodeCodePoints(array $codePoints): string
    {
        $text = '';

        foreach ($codePoints as $codePoint) {
            $text .= mb_chr($codePoint, 'UTF-8');
        }

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

        $cacheKey = $fontSize . "\0" . $text;

        if (isset($this->textWidthCache[$cacheKey])) {
            return $this->textWidthCache[$cacheKey];
        }

        $width = 0;

        foreach (preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $character) {
            $glyphId = $this->parser->getGlyphIdForCharacter($character);
            $width += $this->parser->getAdvanceWidthForGlyphId($glyphId);
        }

        $measuredWidth = ($width / $this->metadata->unitsPerEm) * $fontSize;
        $this->textWidthCache[$cacheKey] = $measuredWidth;

        return $measuredWidth;
    }

    public function ascent(float $fontSize): float
    {
        return ($this->metadata->ascent / $this->metadata->unitsPerEm) * $fontSize;
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
     * @param list<int> $codePoints
     * @return list<EmbeddedGlyph>
     */
    public function embeddedGlyphsForCodePoints(array $codePoints): array
    {
        $glyphs = [];

        foreach ($codePoints as $codePoint) {
            $glyphId = $this->parser->getGlyphIdForCodePoint($codePoint);
            $key = $glyphId . ':' . $codePoint;

            if (isset($glyphs[$key])) {
                continue;
            }

            $glyphs[$key] = new EmbeddedGlyph(
                glyphId: $glyphId,
                unicodeCodePoint: $codePoint,
                unicodeText: mb_chr($codePoint, 'UTF-8'),
            );
        }

        return array_values($glyphs);
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
            $widths[$code] = $this->pdfWidth($this->parser->getAdvanceWidthForGlyphId($glyphId));
        }

        return $widths;
    }

    public function fontFileStreamContents(): string
    {
        return $this->fontFileStreamDictionaryContents()
            . "\nstream\n"
            . $this->fontFileStreamData()
            . "\nendstream";
    }

    public function fontFileStreamDictionaryContents(): string
    {
        return $this->fontFileStreamDictionaryContentsForData($this->fontFileStreamData());
    }

    public function fontFileStreamData(): string
    {
        return $this->source->data;
    }

    /**
     * @param list<int> $codePoints
     */
    public function unicodeSubsetFontFileStreamContents(array $codePoints): string
    {
        return $this->unicodeSubsetFontFileStreamContentsForGlyphs($this->embeddedGlyphsForCodePoints($codePoints));
    }

    /**
     * @param list<EmbeddedGlyph> $glyphs
     */
    public function unicodeSubsetFontFileStreamContentsForGlyphs(array $glyphs): string
    {
        return $this->unicodeSubsetFontFileStreamDictionaryContentsForGlyphs($glyphs)
            . "\nstream\n"
            . $this->unicodeSubsetFontFileStreamDataForGlyphs($glyphs)
            . "\nendstream";
    }

    /**
     * @param list<EmbeddedGlyph> $glyphs
     */
    public function unicodeSubsetFontFileStreamDictionaryContentsForGlyphs(array $glyphs): string
    {
        return $this->fontFileStreamDictionaryContentsForData($this->unicodeSubsetFontFileStreamDataForGlyphs($glyphs));
    }

    /**
     * @param list<EmbeddedGlyph> $glyphs
     */
    public function unicodeSubsetFontFileStreamDataForGlyphs(array $glyphs): string
    {
        if ($this->metadata->outlineType === OpenTypeOutlineType::CFF) {
            return new OpenTypeCffSubsetter($this->parser)->subset(
                array_map(static fn (EmbeddedGlyph $glyph): int => $glyph->unicodeCodePoint, $glyphs),
                $this->subsetPostScriptNameForGlyphs($glyphs),
            );
        }

        $glyphIds = [0];

        foreach ($glyphs as $glyph) {
            $glyphIds[] = $glyph->glyphId;
        }

        return new OpenTypeTrueTypeSubsetter($this->parser)->subset($glyphIds);
    }

    public function fontDescriptorContents(int $fontFileObjectId, ?string $fontName = null): string
    {
        return $this->fontDescriptorContentsWithCidSet($fontFileObjectId, $fontName);
    }

    public function fontDescriptorContentsWithCidSet(
        int $fontFileObjectId,
        ?string $fontName = null,
        ?int $cidSetObjectId = null,
    ): string {
        $flags = 32 | ($this->metadata->italicAngle !== 0.0 ? 64 : 0);
        $bbox = $this->metadata->fontBoundingBox;

        $contents = '<< /Type /FontDescriptor'
            . ' /FontName /' . ($fontName ?? $this->metadata->postScriptName)
            . ' /Flags ' . $flags
            . ' /FontBBox ['
            . $this->pdfMetric($bbox->left) . ' '
            . $this->pdfMetric($bbox->bottom) . ' '
            . $this->pdfMetric($bbox->right) . ' '
            . $this->pdfMetric($bbox->top) . ']'
            . ' /ItalicAngle ' . $this->formatNumber($this->metadata->italicAngle)
            . ' /Ascent ' . $this->pdfMetric($this->metadata->ascent)
            . ' /Descent ' . $this->pdfMetric($this->metadata->descent)
            . ' /CapHeight ' . $this->pdfMetric($this->metadata->capHeight)
            . ' /StemV 80'
            . ' /' . ($this->metadata->outlineType === OpenTypeOutlineType::CFF ? 'FontFile3' : 'FontFile2')
            . ' ' . $fontFileObjectId . ' 0 R';

        if ($cidSetObjectId !== null) {
            $contents .= ' /CIDSet ' . $cidSetObjectId . ' 0 R';
        }

        return $contents . ' >>';
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
     * @param list<EmbeddedGlyph> $glyphs
     */
    public function unicodeType0FontObjectContentsForGlyphs(int $cidFontObjectId, int $toUnicodeObjectId, array $glyphs): string
    {
        return '<< /Type /Font'
            . ' /Subtype /Type0'
            . ' /BaseFont /' . $this->unicodeBaseFontNameForGlyphs($glyphs)
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
            . ' /DW ' . $this->pdfWidth($this->parser->getAdvanceWidthForGlyphId(0))
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
     * @param list<EmbeddedGlyph> $glyphs
     */
    public function unicodeCidFontObjectContentsForGlyphs(
        int $fontDescriptorObjectId,
        ?int $cidToGidMapObjectId,
        array $glyphs,
    ): string {
        $contents = '<< /Type /Font'
            . ' /Subtype /' . ($this->metadata->outlineType === OpenTypeOutlineType::CFF ? 'CIDFontType0' : 'CIDFontType2')
            . ' /BaseFont /' . $this->unicodeBaseFontNameForGlyphs($glyphs)
            . ' /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >>'
            . ' /FontDescriptor ' . $fontDescriptorObjectId . ' 0 R'
            . ' /DW ' . $this->pdfWidth($this->parser->getAdvanceWidthForGlyphId(0))
            . ' /W ' . $this->unicodeWidthsArrayForGlyphs($glyphs);

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
     * @param list<EmbeddedGlyph> $glyphs
     */
    public function unicodeCidToGidMapStreamContentsForGlyphs(array $glyphs): string
    {
        return $this->unicodeCidToGidMapStreamDictionaryContentsForGlyphs($glyphs)
            . "\nstream\n"
            . $this->unicodeCidToGidMapStreamDataForGlyphs($glyphs)
            . "\nendstream";
    }

    /**
     * @param list<EmbeddedGlyph> $glyphs
     */
    public function unicodeCidToGidMapStreamDictionaryContentsForGlyphs(array $glyphs): string
    {
        return '<< /Length ' . strlen($this->unicodeCidToGidMapStreamDataForGlyphs($glyphs)) . ' >>';
    }

    /**
     * @param list<EmbeddedGlyph> $glyphs
     */
    public function unicodeCidToGidMapStreamDataForGlyphs(array $glyphs): string
    {
        if ($this->metadata->outlineType === OpenTypeOutlineType::CFF) {
            throw new InvalidArgumentException('CIDToGIDMap is only used for TrueType CID fonts.');
        }

        if ($glyphs === []) {
            return '';
        }

        $map = pack('n', 0);

        foreach ($glyphs as $glyph) {
            $map .= pack('n', $glyph->glyphId);
        }

        return $map;
    }

    /**
     * @param list<EmbeddedGlyph> $glyphs
     */
    public function unicodeCidSetStreamContentsForGlyphs(array $glyphs): string
    {
        return $this->unicodeCidSetStreamDictionaryContentsForGlyphs($glyphs)
            . "\nstream\n"
            . $this->unicodeCidSetStreamDataForGlyphs($glyphs)
            . "\nendstream";
    }

    /**
     * @param list<EmbeddedGlyph> $glyphs
     */
    public function unicodeCidSetStreamDictionaryContentsForGlyphs(array $glyphs): string
    {
        return '<< /Length ' . strlen($this->unicodeCidSetStreamDataForGlyphs($glyphs)) . ' >>';
    }

    /**
     * @param list<EmbeddedGlyph> $glyphs
     */
    public function unicodeCidSetStreamDataForGlyphs(array $glyphs): string
    {
        $highestCid = count($glyphs);
        $byteLength = intdiv($highestCid, 8) + 1;
        $cidSet = str_repeat("\x00", $byteLength);

        $cidSet[0] = $cidSet[0] | "\x80";

        foreach (array_keys($glyphs) as $index) {
            $cid = $index + 1;
            $byteIndex = intdiv($cid, 8);
            $bitMask = 1 << (7 - ($cid % 8));
            $cidSet[$byteIndex] = $cidSet[$byteIndex] | pack('C', $bitMask);
        }

        return $cidSet;
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
     * @param list<EmbeddedGlyph> $glyphs
     */
    public function unicodeToUnicodeStreamContentsForGlyphs(array $glyphs): string
    {
        return $this->unicodeToUnicodeStreamDictionaryContentsForGlyphs($glyphs)
            . "\nstream\n"
            . $this->unicodeToUnicodeStreamDataForGlyphs($glyphs)
            . 'endstream';
    }

    /**
     * @param list<EmbeddedGlyph> $glyphs
     */
    public function unicodeToUnicodeStreamDictionaryContentsForGlyphs(array $glyphs): string
    {
        return '<< /Length ' . strlen($this->unicodeToUnicodeStreamDataForGlyphs($glyphs)) . ' >>';
    }

    /**
     * @param list<EmbeddedGlyph> $glyphs
     */
    public function unicodeToUnicodeStreamDataForGlyphs(array $glyphs): string
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
            count($glyphs) . ' beginbfchar',
        ];

        foreach ($glyphs as $index => $glyph) {
            $cid = strtoupper(str_pad(dechex($index + 1), 4, '0', STR_PAD_LEFT));
            $unicode = strtoupper(bin2hex(mb_convert_encoding($glyph->unicodeText, 'UTF-16BE', 'UTF-8')));
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

        return implode("\n", $lines) . "\n";
    }

    private function fontFileStreamDictionaryContentsForData(string $data): string
    {
        return match ($this->metadata->outlineType) {
            OpenTypeOutlineType::TRUE_TYPE => '<< /Length ' . strlen($data) . ' /Length1 ' . strlen($data) . ' >>',
            OpenTypeOutlineType::CFF => '<< /Length ' . strlen($data) . ' /Subtype /OpenType >>',
        };
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
            $entries[] = (string) $this->unicodeCharCodeForCodePoint($codePoints, $codePoint) . ' [' . $this->pdfWidth($this->parser->getAdvanceWidthForGlyphId(
                $this->parser->getGlyphIdForCodePoint($codePoint),
            )) . ']';
        }

        return '[' . implode(' ', $entries) . ']';
    }

    /**
     * @param list<EmbeddedGlyph> $glyphs
     */
    private function unicodeWidthsArrayForGlyphs(array $glyphs): string
    {
        if ($glyphs === []) {
            return '[]';
        }

        $entries = [];

        foreach ($glyphs as $index => $glyph) {
            $entries[] = (string) ($index + 1) . ' [' . $this->pdfWidth($this->parser->getAdvanceWidthForGlyphId($glyph->glyphId)) . ']';
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
            /** @var int<65, 90> $prefixCode */
            $prefixCode = 65 + (hexdec($hash[$index]) % 26);
            $prefix .= chr($prefixCode);
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
     * @param list<EmbeddedGlyph> $glyphs
     */
    public function subsetPostScriptNameForGlyphs(array $glyphs): string
    {
        return $this->subsetPostScriptName(array_map(
            static fn (EmbeddedGlyph $glyph): int => $glyph->unicodeCodePoint,
            $glyphs,
        ));
    }

    /**
     * @param list<EmbeddedGlyph> $glyphs
     */
    public function unicodeBaseFontNameForGlyphs(array $glyphs): string
    {
        return $this->subsetPostScriptNameForGlyphs($glyphs);
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

    private function pdfWidth(int $fontUnits): int
    {
        return $this->pdfMetric($fontUnits);
    }

    private function pdfMetric(int $fontUnits): int
    {
        return (int) round(($fontUnits / $this->metadata->unitsPerEm) * 1000);
    }
}
