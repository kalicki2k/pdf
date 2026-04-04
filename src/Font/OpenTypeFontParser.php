<?php

declare(strict_types=1);

namespace Kalle\Pdf\Font;

use InvalidArgumentException;

final class OpenTypeFontParser
{
    /** @var array<string, array{offset: int, length: int}> */
    private array $tables = [];

    public function __construct(private readonly string $data)
    {
        $this->parseTableDirectory();
    }

    public function hasCffOutlines(): bool
    {
        return isset($this->tables['CFF ']);
    }

    public function getGlyphIdForCharacter(string $character): int
    {
        $codePoint = mb_ord($character, 'UTF-8');

        if ($codePoint === false) {
            throw new InvalidArgumentException('Unable to determine Unicode code point for character.');
        }

        return $this->getGlyphIdForCodePoint($codePoint);
    }

    public function getGlyphIdForCodePoint(int $codePoint): int
    {
        $subtable = $this->findPreferredCmapSubtable();

        return match ($subtable['format']) {
            12 => $this->glyphIdFromFormat12($subtable['offset'], $codePoint),
            4 => $this->glyphIdFromFormat4($subtable['offset'], $codePoint),
            default => throw new InvalidArgumentException("Unsupported cmap format {$subtable['format']}."),
        };
    }

    public function getAdvanceWidthForGlyphId(int $glyphId): int
    {
        if (!isset($this->tables['hhea'], $this->tables['hmtx'], $this->tables['maxp'])) {
            throw new InvalidArgumentException('Font is missing horizontal metrics tables.');
        }

        $numberOfHMetrics = $this->readUInt16($this->tables['hhea']['offset'] + 34);
        $hmtxOffset = $this->tables['hmtx']['offset'];

        if ($glyphId < $numberOfHMetrics) {
            return $this->readUInt16($hmtxOffset + ($glyphId * 4));
        }

        return $this->readUInt16($hmtxOffset + (($numberOfHMetrics - 1) * 4));
    }

    private function parseTableDirectory(): void
    {
        $numTables = $this->readUInt16(4);

        for ($index = 0; $index < $numTables; $index++) {
            $offset = 12 + ($index * 16);
            $tag = substr($this->data, $offset, 4);

            $this->tables[$tag] = [
                'offset' => $this->readUInt32($offset + 8),
                'length' => $this->readUInt32($offset + 12),
            ];
        }
    }

    /**
     * @return array{format: int, offset: int}
     */
    private function findPreferredCmapSubtable(): array
    {
        if (!isset($this->tables['cmap'])) {
            throw new InvalidArgumentException('Font does not contain a cmap table.');
        }

        $cmapOffset = $this->tables['cmap']['offset'];
        $numTables = $this->readUInt16($cmapOffset + 2);
        $candidates = [];

        for ($index = 0; $index < $numTables; $index++) {
            $recordOffset = $cmapOffset + 4 + ($index * 8);
            $platformId = $this->readUInt16($recordOffset);
            $encodingId = $this->readUInt16($recordOffset + 2);
            $subtableOffset = $cmapOffset + $this->readUInt32($recordOffset + 4);
            $format = $this->readUInt16($subtableOffset);

            $candidates[] = [
                'platformId' => $platformId,
                'encodingId' => $encodingId,
                'format' => $format,
                'offset' => $subtableOffset,
            ];
        }

        $preferred = [
            [3, 10, 12],
            [0, 4, 12],
            [3, 1, 4],
            [0, 3, 4],
        ];

        foreach ($preferred as [$platformId, $encodingId, $format]) {
            foreach ($candidates as $candidate) {
                if (
                    $candidate['platformId'] === $platformId
                    && $candidate['encodingId'] === $encodingId
                    && $candidate['format'] === $format
                ) {
                    return [
                        'format' => $candidate['format'],
                        'offset' => $candidate['offset'],
                    ];
                }
            }
        }

        throw new InvalidArgumentException('No supported Unicode cmap subtable found.');
    }

    private function glyphIdFromFormat12(int $offset, int $codePoint): int
    {
        $nGroups = $this->readUInt32($offset + 12);

        for ($index = 0; $index < $nGroups; $index++) {
            $groupOffset = $offset + 16 + ($index * 12);
            $startCharCode = $this->readUInt32($groupOffset);
            $endCharCode = $this->readUInt32($groupOffset + 4);
            $startGlyphId = $this->readUInt32($groupOffset + 8);

            if ($codePoint < $startCharCode || $codePoint > $endCharCode) {
                continue;
            }

            return $startGlyphId + ($codePoint - $startCharCode);
        }

        return 0;
    }

    private function glyphIdFromFormat4(int $offset, int $codePoint): int
    {
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

            $glyphOffset = $idRangeOffsetOffset + ($index * 2) + $idRangeOffset + (($codePoint - $startCode) * 2);
            $glyphId = $this->readUInt16($glyphOffset);

            if ($glyphId === 0) {
                return 0;
            }

            return ($glyphId + $idDelta) & 0xFFFF;
        }

        return 0;
    }

    private function readUInt16(int $offset): int
    {
        return unpack('n', substr($this->data, $offset, 2))[1];
    }

    private function readInt16(int $offset): int
    {
        $value = $this->readUInt16($offset);

        return $value >= 0x8000 ? $value - 0x10000 : $value;
    }

    private function readUInt32(int $offset): int
    {
        return unpack('N', substr($this->data, $offset, 4))[1];
    }
}
