<?php

declare(strict_types=1);

namespace Kalle\Pdf\Font\EmbeddedFont;

use function strlen;
use function substr;
use function unpack;

use InvalidArgumentException;

final class OpenTypeFontParser implements EmbeddedFontParser
{
    /** @var array<string, array{offset: int, length: int}> */
    private array $tables;

    /** @var array<int, int> */
    private array $glyphIdByCodePoint = [];

    /** @var array<int, int> */
    private array $advanceWidthsByGlyphId = [];

    private EmbeddedFontMetrics $metrics;
    private int $numberOfHMetrics;
    private int $numGlyphs;
    /** @var array{format: int, offset: int}|null */
    private ?array $preferredCmap = null;

    public function __construct(
        private readonly EmbeddedFontSource $source,
    ) {
        if (strlen($this->source->data) < 12) {
            throw new InvalidArgumentException('OpenType font data is too short to contain a table directory.');
        }

        $this->tables = $this->parseTableDirectory();
        $this->metrics = new EmbeddedFontMetrics(
            unitsPerEm: $this->unitsPerEm(),
            ascent: $this->ascentValue(),
            descent: $this->descentValue(),
        );
        $this->numberOfHMetrics = $this->readUInt16($this->tableOffset('hhea') + 34);
        $this->numGlyphs = $this->readUInt16($this->tableOffset('maxp') + 4);
    }

    public function metrics(): EmbeddedFontMetrics
    {
        return $this->metrics;
    }

    public function glyphIdForCodePoint(int $codePoint): int
    {
        if (isset($this->glyphIdByCodePoint[$codePoint])) {
            return $this->glyphIdByCodePoint[$codePoint];
        }

        $subtable = $this->preferredCmapSubtable();

        return $this->glyphIdByCodePoint[$codePoint] = match ($subtable['format']) {
            12 => $this->glyphIdFromFormat12($subtable['offset'], $codePoint),
            4 => $this->glyphIdFromFormat4($subtable['offset'], $codePoint),
            default => throw new InvalidArgumentException(sprintf('Unsupported cmap format %d.', $subtable['format'])),
        };
    }

    public function advanceWidthForGlyphId(int $glyphId): int
    {
        if (isset($this->advanceWidthsByGlyphId[$glyphId])) {
            return $this->advanceWidthsByGlyphId[$glyphId];
        }

        $glyphId = max(0, min($glyphId, $this->numGlyphs - 1));
        $hmtxOffset = $this->tableOffset('hmtx');
        $metricIndex = min($glyphId, $this->numberOfHMetrics - 1);
        $offset = $hmtxOffset + ($metricIndex * 4);

        return $this->advanceWidthsByGlyphId[$glyphId] = $this->readUInt16($offset);
    }

    /**
     * @return array<string, array{offset: int, length: int}>
     */
    private function parseTableDirectory(): array
    {
        $tableCount = $this->readUInt16(4);
        $tables = [];

        for ($index = 0; $index < $tableCount; $index++) {
            $entryOffset = 12 + ($index * 16);
            $tag = $this->readBytes($entryOffset, 4);
            $tables[$tag] = [
                'offset' => $this->readUInt32($entryOffset + 8),
                'length' => $this->readUInt32($entryOffset + 12),
            ];
        }

        return $tables;
    }

    private function unitsPerEm(): int
    {
        return $this->readUInt16($this->tableOffset('head') + 18);
    }

    private function ascentValue(): int
    {
        return $this->readInt16($this->tableOffset('hhea') + 4);
    }

    private function descentValue(): int
    {
        return $this->readInt16($this->tableOffset('hhea') + 6);
    }

    /**
     * @return array{format: int, offset: int}
     */
    private function preferredCmapSubtable(): array
    {
        if ($this->preferredCmap !== null) {
            return $this->preferredCmap;
        }

        $cmapOffset = $this->tableOffset('cmap');
        $tableCount = $this->readUInt16($cmapOffset + 2);
        $candidates = [];

        for ($index = 0; $index < $tableCount; $index++) {
            $recordOffset = $cmapOffset + 4 + ($index * 8);
            $platformId = $this->readUInt16($recordOffset);
            $encodingId = $this->readUInt16($recordOffset + 2);
            $subtableOffset = $cmapOffset + $this->readUInt32($recordOffset + 4);
            $format = $this->readUInt16($subtableOffset);

            if (($format !== 4 && $format !== 12) || !$this->isUnicodeCmap($platformId, $encodingId)) {
                continue;
            }

            $priority = match ([$platformId, $encodingId, $format]) {
                [3, 10, 12] => 0,
                [0, 4, 12], [0, 3, 4], [0, 4, 4], [0, 3, 12] => 1,
                [3, 1, 4] => 2,
                default => 3,
            };

            $candidates[] = [
                'priority' => $priority,
                'format' => $format,
                'offset' => $subtableOffset,
            ];
        }

        if ($candidates === []) {
            throw new InvalidArgumentException('OpenType font does not contain a supported Unicode cmap.');
        }

        usort(
            $candidates,
            static fn (array $left, array $right): int => $left['priority'] <=> $right['priority'],
        );

        /** @var array{priority: int, format: int, offset: int} $selected */
        $selected = $candidates[0];

        return $this->preferredCmap = [
            'format' => $selected['format'],
            'offset' => $selected['offset'],
        ];
    }

    private function glyphIdFromFormat12(int $offset, int $codePoint): int
    {
        $groupCount = $this->readUInt32($offset + 12);
        $groupOffset = $offset + 16;

        for ($index = 0; $index < $groupCount; $index++) {
            $entryOffset = $groupOffset + ($index * 12);
            $startCode = $this->readUInt32($entryOffset);
            $endCode = $this->readUInt32($entryOffset + 4);

            if ($codePoint < $startCode || $codePoint > $endCode) {
                continue;
            }

            $startGlyphId = $this->readUInt32($entryOffset + 8);

            return $startGlyphId + ($codePoint - $startCode);
        }

        return 0;
    }

    private function glyphIdFromFormat4(int $offset, int $codePoint): int
    {
        if ($codePoint > 0xFFFF) {
            return 0;
        }

        $segCount = intdiv($this->readUInt16($offset + 6), 2);
        $endCodeOffset = $offset + 14;
        $startCodeOffset = $endCodeOffset + ($segCount * 2) + 2;
        $idDeltaOffset = $startCodeOffset + ($segCount * 2);
        $idRangeOffsetOffset = $idDeltaOffset + ($segCount * 2);

        for ($index = 0; $index < $segCount; $index++) {
            $endCode = $this->readUInt16($endCodeOffset + ($index * 2));
            $startCode = $this->readUInt16($startCodeOffset + ($index * 2));

            if ($codePoint < $startCode || $codePoint > $endCode) {
                continue;
            }

            $idDelta = $this->readInt16($idDeltaOffset + ($index * 2));
            $idRangeOffset = $this->readUInt16($idRangeOffsetOffset + ($index * 2));

            if ($idRangeOffset === 0) {
                return ($codePoint + $idDelta) & 0xFFFF;
            }

            $glyphIndexOffset = $idRangeOffsetOffset
                + ($index * 2)
                + $idRangeOffset
                + (($codePoint - $startCode) * 2);
            $glyphId = $this->readUInt16($glyphIndexOffset);

            if ($glyphId === 0) {
                return 0;
            }

            return ($glyphId + $idDelta) & 0xFFFF;
        }

        return 0;
    }

    private function isUnicodeCmap(int $platformId, int $encodingId): bool
    {
        return ($platformId === 0)
            || ($platformId === 3 && ($encodingId === 1 || $encodingId === 10));
    }

    private function tableOffset(string $tag): int
    {
        $table = $this->tables[$tag] ?? null;

        if ($table === null) {
            throw new InvalidArgumentException(sprintf('OpenType font table "%s" is missing.', $tag));
        }

        return $table['offset'];
    }

    private function readBytes(int $offset, int $length): string
    {
        $bytes = substr($this->source->data, $offset, $length);

        if ($bytes === false || strlen($bytes) !== $length) {
            throw new InvalidArgumentException('Unexpected end of OpenType font data.');
        }

        return $bytes;
    }

    private function readUInt16(int $offset): int
    {
        return unpack('nvalue', $this->readBytes($offset, 2))['value'];
    }

    private function readInt16(int $offset): int
    {
        $value = $this->readUInt16($offset);

        return $value >= 0x8000
            ? $value - 0x10000
            : $value;
    }

    private function readUInt32(int $offset): int
    {
        return unpack('Nvalue', $this->readBytes($offset, 4))['value'];
    }
}
