<?php

declare(strict_types=1);

namespace Kalle\Pdf\Font;

use InvalidArgumentException;

use function count;
use function floor;
use function is_string;
use function mb_chr;
use function mb_ord;
use function preg_split;
use function str_pad;
use function strlen;
use function substr;
use function unpack;

final class OpenTypeFontParser
{
    /** @var array<string, array{offset: int, length: int}> */
    private array $tables = [];

    private readonly string $data;

    public function __construct(string | EmbeddedFontSource $source)
    {
        $this->data = $source instanceof EmbeddedFontSource
            ? $source->data
            : $source;

        if (strlen($this->data) < 12) {
            throw new InvalidArgumentException('OpenType font data is too short to contain a table directory.');
        }

        $this->parseTableDirectory();
    }

    public function outlineType(): OpenTypeOutlineType
    {
        $signature = $this->readBytes(0, 4);

        return match ($signature) {
            "\x00\x01\x00\x00", 'true', 'typ1' => OpenTypeOutlineType::TRUE_TYPE,
            'OTTO' => OpenTypeOutlineType::CFF,
            default => throw new InvalidArgumentException('Unsupported SFNT/OpenType font signature.'),
        };
    }

    public function metadata(): EmbeddedFontMetadata
    {
        return new EmbeddedFontMetadata(
            postScriptName: $this->postScriptName(),
            outlineType: $this->outlineType(),
            unitsPerEm: $this->unitsPerEm(),
            ascent: $this->ascent(),
            descent: $this->descent(),
            capHeight: $this->capHeight(),
            italicAngle: $this->italicAngle(),
            fontBoundingBox: $this->fontBoundingBox(),
            glyphCount: $this->glyphCount(),
        );
    }

    public function getGlyphIdForCharacter(string $character): int
    {
        if ($character === '') {
            throw new InvalidArgumentException('Character must not be empty.');
        }

        return $this->getGlyphIdForCodePoint(mb_ord($character, 'UTF-8'));
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
        return $this->getHorizontalMetricsForGlyphId($glyphId)['advanceWidth'];
    }

    /**
     * @return array{advanceWidth: int, leftSideBearing: int}
     */
    public function getHorizontalMetricsForGlyphId(int $glyphId): array
    {
        $hheaOffset = $this->requiredTableOffset('hhea');
        $hmtxOffset = $this->requiredTableOffset('hmtx');

        $numberOfHMetrics = $this->readUInt16($hheaOffset + 34);

        if ($glyphId < $numberOfHMetrics) {
            return [
                'advanceWidth' => $this->readUInt16($hmtxOffset + ($glyphId * 4)),
                'leftSideBearing' => $this->readInt16($hmtxOffset + ($glyphId * 4) + 2),
            ];
        }

        return [
            'advanceWidth' => $this->readUInt16($hmtxOffset + (($numberOfHMetrics - 1) * 4)),
            'leftSideBearing' => $this->readInt16(
                $hmtxOffset + ($numberOfHMetrics * 4) + (($glyphId - $numberOfHMetrics) * 2),
            ),
        ];
    }

    public function unitsPerEm(): int
    {
        return $this->readUInt16($this->requiredTableOffset('head') + 18);
    }

    public function ascent(): int
    {
        return $this->readInt16($this->requiredTableOffset('hhea') + 4);
    }

    public function descent(): int
    {
        return $this->readInt16($this->requiredTableOffset('hhea') + 6);
    }

    public function capHeight(): int
    {
        if (!isset($this->tables['OS/2'])) {
            return $this->ascent();
        }

        $os2Offset = $this->tables['OS/2']['offset'];
        $version = $this->readUInt16($os2Offset);

        if ($version < 2 || $this->tables['OS/2']['length'] < 90) {
            return $this->ascent();
        }

        return $this->readInt16($os2Offset + 88);
    }

    public function italicAngle(): float
    {
        if (!isset($this->tables['post'])) {
            return 0.0;
        }

        return $this->readFixed16_16($this->tables['post']['offset'] + 4);
    }

    public function glyphCount(): int
    {
        return $this->readUInt16($this->requiredTableOffset('maxp') + 4);
    }

    public function fontBoundingBox(): FontBoundingBox
    {
        $headOffset = $this->requiredTableOffset('head');

        return new FontBoundingBox(
            left: $this->readInt16($headOffset + 36),
            bottom: $this->readInt16($headOffset + 38),
            right: $this->readInt16($headOffset + 40),
            top: $this->readInt16($headOffset + 42),
        );
    }

    public function postScriptName(): string
    {
        $nameOffset = $this->requiredTableOffset('name');
        $count = $this->readUInt16($nameOffset + 2);
        $stringOffset = $this->readUInt16($nameOffset + 4);
        $records = [];

        for ($index = 0; $index < $count; $index++) {
            $recordOffset = $nameOffset + 6 + ($index * 12);
            $records[] = [
                'platformId' => $this->readUInt16($recordOffset),
                'encodingId' => $this->readUInt16($recordOffset + 2),
                'languageId' => $this->readUInt16($recordOffset + 4),
                'nameId' => $this->readUInt16($recordOffset + 6),
                'length' => $this->readUInt16($recordOffset + 8),
                'offset' => $this->readUInt16($recordOffset + 10),
            ];
        }

        $preferred = [
            [3, 1, 0x0409, 6],
            [0, 4, 0, 6],
            [1, 0, 0, 6],
        ];

        foreach ($preferred as [$platformId, $encodingId, $languageId, $nameId]) {
            foreach ($records as $record) {
                if (
                    $record['platformId'] !== $platformId
                    || $record['encodingId'] !== $encodingId
                    || $record['languageId'] !== $languageId
                    || $record['nameId'] !== $nameId
                ) {
                    continue;
                }

                $rawName = $this->readBytes($nameOffset + $stringOffset + $record['offset'], $record['length']);

                return $this->decodeNameRecord($record['platformId'], $record['encodingId'], $rawName);
            }
        }

        throw new InvalidArgumentException('OpenType font does not contain a supported PostScript name record.');
    }

    public function hasTable(string $tag): bool
    {
        return isset($this->tables[$tag]);
    }

    public function tableBytes(string $tag): string
    {
        $table = $this->tables[$tag] ?? null;

        if ($table === null) {
            throw new InvalidArgumentException(sprintf(
                "Font does not contain table '%s'.",
                $tag,
            ));
        }

        return $this->readBytes($table['offset'], $table['length']);
    }

    /**
     * @return list<string>
     */
    public function tableTags(): array
    {
        return array_keys($this->tables);
    }

    public function indexToLocFormat(): int
    {
        return $this->readInt16($this->requiredTableOffset('head') + 50);
    }

    public function glyphDataForGlyphId(int $glyphId): string
    {
        if (!$this->hasTable('glyf') || !$this->hasTable('loca')) {
            throw new InvalidArgumentException('TrueType glyph tables are missing.');
        }

        $locaOffset = $this->requiredTableOffset('loca');
        $locFormat = $this->indexToLocFormat();

        $start = $locFormat === 0
            ? $this->readUInt16($locaOffset + ($glyphId * 2)) * 2
            : $this->readUInt32($locaOffset + ($glyphId * 4));

        $end = $locFormat === 0
            ? $this->readUInt16($locaOffset + (($glyphId + 1) * 2)) * 2
            : $this->readUInt32($locaOffset + (($glyphId + 1) * 4));

        return $this->readBytes($this->requiredTableOffset('glyf') + $start, $end - $start);
    }

    private function parseTableDirectory(): void
    {
        $numTables = $this->readUInt16(4);

        for ($index = 0; $index < $numTables; $index++) {
            $offset = 12 + ($index * 16);
            $tag = $this->readBytes($offset, 4);

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
        $cmapOffset = $this->requiredTableOffset('cmap');
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

    private function decodeNameRecord(int $platformId, int $encodingId, string $rawName): string
    {
        if ($platformId === 3 || $platformId === 0) {
            $decoded = mb_convert_encoding($rawName, 'UTF-8', 'UTF-16BE');

            if ($decoded === '') {
                throw new InvalidArgumentException('Unable to decode UTF-16BE name record from OpenType font.');
            }

            return $decoded;
        }

        if ($platformId === 1 && $encodingId === 0) {
            return $rawName;
        }

        throw new InvalidArgumentException('Unsupported OpenType name record encoding.');
    }

    private function requiredTableOffset(string $tag): int
    {
        if (!isset($this->tables[$tag])) {
            throw new InvalidArgumentException(sprintf(
                "Font is missing required '%s' table.",
                $tag,
            ));
        }

        return $this->tables[$tag]['offset'];
    }

    private function readUInt16(int $offset): int
    {
        $value = unpack('n', $this->readBytes($offset, 2));

        if (!is_array($value)) {
            throw new InvalidArgumentException('Unable to read 16-bit unsigned integer from font data.');
        }

        /** @var int $result */
        $result = $value[1];

        return $result;
    }

    private function readInt16(int $offset): int
    {
        $value = $this->readUInt16($offset);

        return $value >= 0x8000 ? $value - 0x10000 : $value;
    }

    private function readUInt32(int $offset): int
    {
        $value = unpack('N', $this->readBytes($offset, 4));

        if (!is_array($value)) {
            throw new InvalidArgumentException('Unable to read 32-bit unsigned integer from font data.');
        }

        /** @var int $result */
        $result = $value[1];

        return $result;
    }

    private function readFixed16_16(int $offset): float
    {
        $major = $this->readInt16($offset);
        $minor = $this->readUInt16($offset + 2);

        return $major + ($minor / 65536);
    }

    private function readBytes(int $offset, int $length): string
    {
        $bytes = substr($this->data, $offset, $length);

        if (strlen($bytes) !== $length) {
            throw new InvalidArgumentException('Unexpected end of OpenType font data.');
        }

        return $bytes;
    }
}
