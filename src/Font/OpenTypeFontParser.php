<?php

declare(strict_types=1);

namespace Kalle\Pdf\Font;

use function count;

use InvalidArgumentException;

use function mb_ord;
use function strlen;
use function substr;
use function unpack;

final class OpenTypeFontParser
{
    /** @var array<string, array{offset: int, length: int}> */
    private array $tables = [];
    /** @var array<string, list<int>>|null */
    private ?array $gsubFeatureLookups = null;
    /** @var array<string, list<int>>|null */
    private ?array $gposFeatureLookups = null;
    /** @var array{format: int, offset: int}|null */
    private ?array $preferredCmapSubtable = null;
    /** @var array<int, int> */
    private array $glyphIdByCodePoint = [];
    /** @var array<int, array{advanceWidth: int, leftSideBearing: int}> */
    private array $horizontalMetricsByGlyphId = [];
    /** @var array<int, list<int>> */
    private array $coverageGlyphIdsCache = [];
    /** @var array<string, array{lookupListOffset: int, lookupCount: int}|null> */
    private array $layoutLookupListCache = [];

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
        if (isset($this->glyphIdByCodePoint[$codePoint])) {
            return $this->glyphIdByCodePoint[$codePoint];
        }

        $subtable = $this->findPreferredCmapSubtable();

        return $this->glyphIdByCodePoint[$codePoint] = match ($subtable['format']) {
            12 => $this->glyphIdFromFormat12($subtable['offset'], $codePoint),
            4 => $this->glyphIdFromFormat4($subtable['offset'], $codePoint),
            default => throw new InvalidArgumentException("Unsupported cmap format {$subtable['format']}."),
        };
    }

    public function hasGsubFeature(string $featureTag): bool
    {
        return isset($this->gsubFeatureLookups()[$featureTag]);
    }

    public function hasGposFeature(string $featureTag): bool
    {
        return isset($this->gposFeatureLookups()[$featureTag]);
    }

    public function substituteGlyphIdWithFeature(string $featureTag, int $glyphId): ?int
    {
        foreach ($this->gsubFeatureLookups()[$featureTag] ?? [] as $lookupIndex) {
            $substitutedGlyphId = $this->applyGsubLookup($lookupIndex, $glyphId);

            if ($substitutedGlyphId !== null) {
                return $substitutedGlyphId;
            }
        }

        return null;
    }

    /**
     * @param list<int> $glyphIds
     * @return array{substitutedGlyphId: int, consumedGlyphCount: int}|null
     */
    public function substituteGlyphSequenceWithFeature(string $featureTag, array $glyphIds): ?array
    {
        foreach ($this->gsubFeatureLookups()[$featureTag] ?? [] as $lookupIndex) {
            $substitution = $this->applyGsubSequenceLookup($lookupIndex, $glyphIds);

            if ($substitution !== null) {
                return $substitution;
            }
        }

        return null;
    }

    /**
     * @param list<int> $glyphIds
     * @return array{substitutedGlyphId: int, matchedGlyphCount: int}|null
     */
    public function substituteContextualGlyphSequenceWithFeature(string $featureTag, array $glyphIds): ?array
    {
        foreach ($this->gsubFeatureLookups()[$featureTag] ?? [] as $lookupIndex) {
            $substitution = $this->applyGsubContextLookup($lookupIndex, $glyphIds);

            if ($substitution !== null) {
                return $substitution;
            }
        }

        return null;
    }

    public function gposPairAdjustmentValueWithFeature(string $featureTag, int $leftGlyphId, int $rightGlyphId): ?int
    {
        foreach ($this->gposFeatureLookups()[$featureTag] ?? [] as $lookupIndex) {
            $adjustment = $this->applyGposPairAdjustmentLookup($lookupIndex, $leftGlyphId, $rightGlyphId);

            if ($adjustment !== null) {
                return $adjustment;
            }
        }

        return null;
    }

    public function gposSingleAdjustmentValueWithFeature(string $featureTag, int $glyphId): ?int
    {
        foreach ($this->gposFeatureLookups()[$featureTag] ?? [] as $lookupIndex) {
            $adjustment = $this->applyGposSingleAdjustmentLookup($lookupIndex, $glyphId);

            if ($adjustment !== null) {
                return $adjustment;
            }
        }

        return null;
    }

    /**
     * @return array{xOffset: int, yOffset: int}|null
     */
    public function gposMarkToBasePlacementWithFeature(string $featureTag, int $baseGlyphId, int $markGlyphId): ?array
    {
        foreach ($this->gposFeatureLookups()[$featureTag] ?? [] as $lookupIndex) {
            $placement = $this->applyGposMarkToBaseLookup($lookupIndex, $baseGlyphId, $markGlyphId);

            if ($placement !== null) {
                return $placement;
            }
        }

        return null;
    }

    /**
     * @return array{xOffset: int, yOffset: int}|null
     */
    public function gposMarkToMarkPlacementWithFeature(string $featureTag, int $baseMarkGlyphId, int $markGlyphId): ?array
    {
        foreach ($this->gposFeatureLookups()[$featureTag] ?? [] as $lookupIndex) {
            $placement = $this->applyGposMarkToMarkLookup($lookupIndex, $baseMarkGlyphId, $markGlyphId);

            if ($placement !== null) {
                return $placement;
            }
        }

        return null;
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
        if (isset($this->horizontalMetricsByGlyphId[$glyphId])) {
            return $this->horizontalMetricsByGlyphId[$glyphId];
        }

        $hheaOffset = $this->requiredTableOffset('hhea');
        $hmtxOffset = $this->requiredTableOffset('hmtx');

        $numberOfHMetrics = $this->readUInt16($hheaOffset + 34);

        if ($glyphId < $numberOfHMetrics) {
            return $this->horizontalMetricsByGlyphId[$glyphId] = [
                'advanceWidth' => $this->readUInt16($hmtxOffset + ($glyphId * 4)),
                'leftSideBearing' => $this->readInt16($hmtxOffset + ($glyphId * 4) + 2),
            ];
        }

        return $this->horizontalMetricsByGlyphId[$glyphId] = [
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
        if ($this->outlineType() === OpenTypeOutlineType::CFF && isset($this->tables['CFF '])) {
            return new CffFontParser($this->tableBytes('CFF '))->italicAngle();
        }

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
        if ($this->outlineType() === OpenTypeOutlineType::CFF && isset($this->tables['CFF '])) {
            return new CffFontParser($this->tableBytes('CFF '))->fontBoundingBox();
        }

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
        if ($this->outlineType() === OpenTypeOutlineType::CFF && isset($this->tables['CFF '])) {
            return new CffFontParser($this->tableBytes('CFF '))->postScriptName();
        }

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
     * @return array<string, list<int>>
     */
    private function gsubFeatureLookups(): array
    {
        return $this->layoutFeatureLookups('GSUB', $this->gsubFeatureLookups);
    }

    /**
     * @return array<string, list<int>>
     */
    private function gposFeatureLookups(): array
    {
        return $this->layoutFeatureLookups('GPOS', $this->gposFeatureLookups);
    }

    /**
     * @param array<string, list<int>>|null $cache
     * @param-out array<string, list<int>> $cache
     * @return array<string, list<int>>
     */
    private function layoutFeatureLookups(string $tableTag, ?array &$cache): array
    {
        if ($cache !== null) {
            return $cache;
        }

        if (!$this->hasTable($tableTag)) {
            $cache = [];

            return $cache;
        }

        $layoutOffset = $this->requiredTableOffset($tableTag);
        $featureListOffset = $layoutOffset + $this->readUInt16($layoutOffset + 6);
        $featureCount = $this->readUInt16($featureListOffset);
        /** @var array<string, list<int>> $features */
        $features = [];

        for ($index = 0; $index < $featureCount; $index++) {
            $recordOffset = $featureListOffset + 2 + ($index * 6);
            $featureTag = $this->readBytes($recordOffset, 4);
            $featureOffset = $featureListOffset + $this->readUInt16($recordOffset + 4);
            $lookupCount = $this->readUInt16($featureOffset + 2);
            $lookupIndices = [];

            for ($lookupIndex = 0; $lookupIndex < $lookupCount; $lookupIndex++) {
                $lookupIndices[] = $this->readUInt16($featureOffset + 4 + ($lookupIndex * 2));
            }

            $features[$featureTag] = $lookupIndices;
        }

        $cache = $features;

        return $cache;
    }

    private function applyGposPairAdjustmentLookup(int $lookupIndex, int $leftGlyphId, int $rightGlyphId): ?int
    {
        $lookupList = $this->layoutLookupList('GPOS');

        if ($lookupList === null) {
            return null;
        }

        if ($lookupIndex >= $lookupList['lookupCount']) {
            return null;
        }

        $lookupListOffset = $lookupList['lookupListOffset'];
        $lookupOffset = $lookupListOffset + $this->readUInt16($lookupListOffset + 2 + ($lookupIndex * 2));
        $lookupType = $this->readUInt16($lookupOffset);
        $subtableCount = $this->readUInt16($lookupOffset + 4);

        if ($lookupType !== 2) {
            return null;
        }

        for ($subtableIndex = 0; $subtableIndex < $subtableCount; $subtableIndex++) {
            $subtableOffset = $lookupOffset + $this->readUInt16($lookupOffset + 6 + ($subtableIndex * 2));
            $adjustment = $this->applyPairAdjustmentSubtable($subtableOffset, $leftGlyphId, $rightGlyphId);

            if ($adjustment !== null) {
                return $adjustment;
            }
        }

        return null;
    }

    private function applyGposSingleAdjustmentLookup(int $lookupIndex, int $glyphId): ?int
    {
        $lookupList = $this->layoutLookupList('GPOS');

        if ($lookupList === null) {
            return null;
        }

        if ($lookupIndex >= $lookupList['lookupCount']) {
            return null;
        }

        $lookupListOffset = $lookupList['lookupListOffset'];
        $lookupOffset = $lookupListOffset + $this->readUInt16($lookupListOffset + 2 + ($lookupIndex * 2));
        $lookupType = $this->readUInt16($lookupOffset);
        $subtableCount = $this->readUInt16($lookupOffset + 4);

        if ($lookupType !== 1) {
            return null;
        }

        for ($subtableIndex = 0; $subtableIndex < $subtableCount; $subtableIndex++) {
            $subtableOffset = $lookupOffset + $this->readUInt16($lookupOffset + 6 + ($subtableIndex * 2));
            $adjustment = $this->applySingleAdjustmentSubtable($subtableOffset, $glyphId);

            if ($adjustment !== null) {
                return $adjustment;
            }
        }

        return null;
    }

    private function applySingleAdjustmentSubtable(int $subtableOffset, int $glyphId): ?int
    {
        $posFormat = $this->readUInt16($subtableOffset);
        $coverageOffset = $subtableOffset + $this->readUInt16($subtableOffset + 2);
        $coveredGlyphIds = $this->coverageGlyphIds($coverageOffset);
        $coveredIndex = array_search($glyphId, $coveredGlyphIds, true);

        if (!is_int($coveredIndex)) {
            return null;
        }

        $valueFormat = $this->readUInt16($subtableOffset + 4);

        if ($valueFormat !== 0x0004) {
            return null;
        }

        if ($posFormat === 1) {
            return $this->readInt16($subtableOffset + 6);
        }

        if ($posFormat === 2) {
            return $this->readInt16($subtableOffset + 8 + ($coveredIndex * 2));
        }

        return null;
    }

    private function applyPairAdjustmentSubtable(int $subtableOffset, int $leftGlyphId, int $rightGlyphId): ?int
    {
        $posFormat = $this->readUInt16($subtableOffset);

        if ($posFormat !== 1) {
            return null;
        }

        $coverageOffset = $subtableOffset + $this->readUInt16($subtableOffset + 2);
        $coveredGlyphIds = $this->coverageGlyphIds($coverageOffset);
        $firstGlyphIndex = array_search($leftGlyphId, $coveredGlyphIds, true);

        if (!is_int($firstGlyphIndex)) {
            return null;
        }

        $valueFormat1 = $this->readUInt16($subtableOffset + 4);
        $valueFormat2 = $this->readUInt16($subtableOffset + 6);

        if ($valueFormat1 !== 0x0004 || $valueFormat2 !== 0) {
            return null;
        }

        $pairSetCount = $this->readUInt16($subtableOffset + 8);

        if ($firstGlyphIndex >= $pairSetCount) {
            return null;
        }

        $pairSetOffset = $subtableOffset + $this->readUInt16($subtableOffset + 10 + ($firstGlyphIndex * 2));
        $pairValueCount = $this->readUInt16($pairSetOffset);

        for ($pairIndex = 0; $pairIndex < $pairValueCount; $pairIndex++) {
            $pairValueOffset = $pairSetOffset + 2 + ($pairIndex * 4);

            if ($this->readUInt16($pairValueOffset) !== $rightGlyphId) {
                continue;
            }

            return $this->readInt16($pairValueOffset + 2);
        }

        return null;
    }

    /**
     * @return array{xOffset: int, yOffset: int}|null
     */
    private function applyGposMarkToBaseLookup(int $lookupIndex, int $baseGlyphId, int $markGlyphId): ?array
    {
        $lookupList = $this->layoutLookupList('GPOS');

        if ($lookupList === null) {
            return null;
        }

        if ($lookupIndex >= $lookupList['lookupCount']) {
            return null;
        }

        $lookupListOffset = $lookupList['lookupListOffset'];
        $lookupOffset = $lookupListOffset + $this->readUInt16($lookupListOffset + 2 + ($lookupIndex * 2));
        $lookupType = $this->readUInt16($lookupOffset);
        $subtableCount = $this->readUInt16($lookupOffset + 4);

        if ($lookupType !== 4) {
            return null;
        }

        for ($subtableIndex = 0; $subtableIndex < $subtableCount; $subtableIndex++) {
            $subtableOffset = $lookupOffset + $this->readUInt16($lookupOffset + 6 + ($subtableIndex * 2));
            $placement = $this->applyMarkToBaseSubtable($subtableOffset, $baseGlyphId, $markGlyphId);

            if ($placement !== null) {
                return $placement;
            }
        }

        return null;
    }

    /**
     * @return array{xOffset: int, yOffset: int}|null
     */
    private function applyGposMarkToMarkLookup(int $lookupIndex, int $baseMarkGlyphId, int $markGlyphId): ?array
    {
        $lookupList = $this->layoutLookupList('GPOS');

        if ($lookupList === null) {
            return null;
        }

        if ($lookupIndex >= $lookupList['lookupCount']) {
            return null;
        }

        $lookupListOffset = $lookupList['lookupListOffset'];
        $lookupOffset = $lookupListOffset + $this->readUInt16($lookupListOffset + 2 + ($lookupIndex * 2));
        $lookupType = $this->readUInt16($lookupOffset);
        $subtableCount = $this->readUInt16($lookupOffset + 4);

        if ($lookupType !== 6) {
            return null;
        }

        for ($subtableIndex = 0; $subtableIndex < $subtableCount; $subtableIndex++) {
            $subtableOffset = $lookupOffset + $this->readUInt16($lookupOffset + 6 + ($subtableIndex * 2));
            $placement = $this->applyMarkToMarkSubtable($subtableOffset, $baseMarkGlyphId, $markGlyphId);

            if ($placement !== null) {
                return $placement;
            }
        }

        return null;
    }

    /**
     * @return array{xOffset: int, yOffset: int}|null
     */
    private function applyMarkToBaseSubtable(int $subtableOffset, int $baseGlyphId, int $markGlyphId): ?array
    {
        $posFormat = $this->readUInt16($subtableOffset);

        if ($posFormat !== 1) {
            return null;
        }

        $markCoverageOffset = $subtableOffset + $this->readUInt16($subtableOffset + 2);
        $baseCoverageOffset = $subtableOffset + $this->readUInt16($subtableOffset + 4);
        $markGlyphIds = $this->coverageGlyphIds($markCoverageOffset);
        $baseGlyphIds = $this->coverageGlyphIds($baseCoverageOffset);
        $markIndex = array_search($markGlyphId, $markGlyphIds, true);
        $baseIndex = array_search($baseGlyphId, $baseGlyphIds, true);

        if (!is_int($markIndex) || !is_int($baseIndex)) {
            return null;
        }

        $classCount = $this->readUInt16($subtableOffset + 6);
        $markArrayOffset = $subtableOffset + $this->readUInt16($subtableOffset + 8);
        $baseArrayOffset = $subtableOffset + $this->readUInt16($subtableOffset + 10);
        $markRecordOffset = $markArrayOffset + 2 + ($markIndex * 4);
        $markClass = $this->readUInt16($markRecordOffset);

        if ($markClass >= $classCount) {
            return null;
        }

        $markAnchorOffset = $markArrayOffset + $this->readUInt16($markRecordOffset + 2);
        $baseRecordOffset = $baseArrayOffset + 2 + ($baseIndex * ($classCount * 2));
        $baseAnchorRelativeOffset = $this->readUInt16($baseRecordOffset + ($markClass * 2));

        if ($baseAnchorRelativeOffset === 0) {
            return null;
        }

        $baseAnchorOffset = $baseArrayOffset + $baseAnchorRelativeOffset;
        $markAnchor = $this->readAnchor($markAnchorOffset);
        $baseAnchor = $this->readAnchor($baseAnchorOffset);

        return [
            'xOffset' => $baseAnchor['x'] - $markAnchor['x'],
            'yOffset' => $baseAnchor['y'] - $markAnchor['y'],
        ];
    }

    /**
     * @return array{xOffset: int, yOffset: int}|null
     */
    private function applyMarkToMarkSubtable(int $subtableOffset, int $baseMarkGlyphId, int $markGlyphId): ?array
    {
        $posFormat = $this->readUInt16($subtableOffset);

        if ($posFormat !== 1) {
            return null;
        }

        $mark1CoverageOffset = $subtableOffset + $this->readUInt16($subtableOffset + 2);
        $mark2CoverageOffset = $subtableOffset + $this->readUInt16($subtableOffset + 4);
        $mark1GlyphIds = $this->coverageGlyphIds($mark1CoverageOffset);
        $mark2GlyphIds = $this->coverageGlyphIds($mark2CoverageOffset);
        $mark1Index = array_search($markGlyphId, $mark1GlyphIds, true);
        $mark2Index = array_search($baseMarkGlyphId, $mark2GlyphIds, true);

        if (!is_int($mark1Index) || !is_int($mark2Index)) {
            return null;
        }

        $classCount = $this->readUInt16($subtableOffset + 6);
        $mark1ArrayOffset = $subtableOffset + $this->readUInt16($subtableOffset + 8);
        $mark2ArrayOffset = $subtableOffset + $this->readUInt16($subtableOffset + 10);
        $mark1RecordOffset = $mark1ArrayOffset + 2 + ($mark1Index * 4);
        $markClass = $this->readUInt16($mark1RecordOffset);

        if ($markClass >= $classCount) {
            return null;
        }

        $mark1AnchorOffset = $mark1ArrayOffset + $this->readUInt16($mark1RecordOffset + 2);
        $mark2RecordOffset = $mark2ArrayOffset + 2 + ($mark2Index * ($classCount * 2));
        $mark2AnchorRelativeOffset = $this->readUInt16($mark2RecordOffset + ($markClass * 2));

        if ($mark2AnchorRelativeOffset === 0) {
            return null;
        }

        $mark2AnchorOffset = $mark2ArrayOffset + $mark2AnchorRelativeOffset;
        $mark1Anchor = $this->readAnchor($mark1AnchorOffset);
        $mark2Anchor = $this->readAnchor($mark2AnchorOffset);

        return [
            'xOffset' => $mark2Anchor['x'] - $mark1Anchor['x'],
            'yOffset' => $mark2Anchor['y'] - $mark1Anchor['y'],
        ];
    }

    private function applyGsubLookup(int $lookupIndex, int $glyphId): ?int
    {
        $lookupList = $this->layoutLookupList('GSUB');

        if ($lookupList === null) {
            return null;
        }

        if ($lookupIndex >= $lookupList['lookupCount']) {
            return null;
        }

        $lookupListOffset = $lookupList['lookupListOffset'];
        $lookupOffset = $lookupListOffset + $this->readUInt16($lookupListOffset + 2 + ($lookupIndex * 2));
        $lookupType = $this->readUInt16($lookupOffset);
        $subtableCount = $this->readUInt16($lookupOffset + 4);

        if ($lookupType !== 1) {
            return null;
        }

        for ($subtableIndex = 0; $subtableIndex < $subtableCount; $subtableIndex++) {
            $subtableOffset = $lookupOffset + $this->readUInt16($lookupOffset + 6 + ($subtableIndex * 2));
            $substitutedGlyphId = $this->applySingleSubstitutionSubtable($subtableOffset, $glyphId);

            if ($substitutedGlyphId !== null) {
                return $substitutedGlyphId;
            }
        }

        return null;
    }

    /**
     * @param list<int> $glyphIds
     * @return array{substitutedGlyphId: int, consumedGlyphCount: int}|null
     */
    private function applyGsubSequenceLookup(int $lookupIndex, array $glyphIds): ?array
    {
        $lookupList = $this->layoutLookupList('GSUB');

        if ($lookupList === null) {
            return null;
        }

        if ($lookupIndex >= $lookupList['lookupCount']) {
            return null;
        }

        $lookupListOffset = $lookupList['lookupListOffset'];
        $lookupOffset = $lookupListOffset + $this->readUInt16($lookupListOffset + 2 + ($lookupIndex * 2));
        $lookupType = $this->readUInt16($lookupOffset);
        $subtableCount = $this->readUInt16($lookupOffset + 4);

        if ($lookupType !== 4) {
            return null;
        }

        for ($subtableIndex = 0; $subtableIndex < $subtableCount; $subtableIndex++) {
            $subtableOffset = $lookupOffset + $this->readUInt16($lookupOffset + 6 + ($subtableIndex * 2));
            $substitution = $this->applyLigatureSubstitutionSubtable($subtableOffset, $glyphIds);

            if ($substitution !== null) {
                return $substitution;
            }
        }

        return null;
    }

    /**
     * @param list<int> $glyphIds
     * @return array{substitutedGlyphId: int, matchedGlyphCount: int}|null
     */
    private function applyGsubContextLookup(int $lookupIndex, array $glyphIds): ?array
    {
        $lookupList = $this->layoutLookupList('GSUB');

        if ($lookupList === null) {
            return null;
        }

        if ($lookupIndex >= $lookupList['lookupCount']) {
            return null;
        }

        $lookupListOffset = $lookupList['lookupListOffset'];
        $lookupOffset = $lookupListOffset + $this->readUInt16($lookupListOffset + 2 + ($lookupIndex * 2));
        $lookupType = $this->readUInt16($lookupOffset);
        $subtableCount = $this->readUInt16($lookupOffset + 4);

        if ($lookupType !== 5 && $lookupType !== 6) {
            return null;
        }

        for ($subtableIndex = 0; $subtableIndex < $subtableCount; $subtableIndex++) {
            $subtableOffset = $lookupOffset + $this->readUInt16($lookupOffset + 6 + ($subtableIndex * 2));
            $substitution = $lookupType === 5
                ? $this->applyContextSubstitutionSubtable($lookupListOffset, $subtableOffset, $glyphIds)
                : $this->applyChainingContextSubstitutionSubtable($lookupListOffset, $subtableOffset, $glyphIds);

            if ($substitution !== null) {
                return $substitution;
            }
        }

        return null;
    }

    private function applySingleSubstitutionSubtable(int $subtableOffset, int $glyphId): ?int
    {
        $substFormat = $this->readUInt16($subtableOffset);
        $coverageOffset = $subtableOffset + $this->readUInt16($subtableOffset + 2);
        $coveredGlyphIds = $this->coverageGlyphIds($coverageOffset);
        $coveredIndex = array_search($glyphId, $coveredGlyphIds, true);

        if (!is_int($coveredIndex)) {
            return null;
        }

        if ($substFormat === 1) {
            $delta = $this->readInt16($subtableOffset + 4);

            return ($glyphId + $delta) & 0xFFFF;
        }

        if ($substFormat === 2) {
            return $this->readUInt16($subtableOffset + 6 + ($coveredIndex * 2));
        }

        return null;
    }

    /**
     * @param list<int> $glyphIds
     * @return array{substitutedGlyphId: int, consumedGlyphCount: int}|null
     */
    private function applyLigatureSubstitutionSubtable(int $subtableOffset, array $glyphIds): ?array
    {
        $substFormat = $this->readUInt16($subtableOffset);

        if ($substFormat !== 1 || $glyphIds === []) {
            return null;
        }

        $coverageOffset = $subtableOffset + $this->readUInt16($subtableOffset + 2);
        $coveredGlyphIds = $this->coverageGlyphIds($coverageOffset);
        $firstGlyphIndex = array_search($glyphIds[0], $coveredGlyphIds, true);

        if (!is_int($firstGlyphIndex)) {
            return null;
        }

        $ligSetCount = $this->readUInt16($subtableOffset + 4);

        if ($firstGlyphIndex >= $ligSetCount) {
            return null;
        }

        $ligSetOffset = $subtableOffset + $this->readUInt16($subtableOffset + 6 + ($firstGlyphIndex * 2));
        $ligatureCount = $this->readUInt16($ligSetOffset);

        for ($ligatureIndex = 0; $ligatureIndex < $ligatureCount; $ligatureIndex++) {
            $ligatureOffset = $ligSetOffset + $this->readUInt16($ligSetOffset + 2 + ($ligatureIndex * 2));
            $ligGlyph = $this->readUInt16($ligatureOffset);
            $componentCount = $this->readUInt16($ligatureOffset + 2);

            if ($componentCount < 2 || count($glyphIds) < $componentCount) {
                continue;
            }

            $matches = true;

            for ($componentIndex = 1; $componentIndex < $componentCount; $componentIndex++) {
                if ($this->readUInt16($ligatureOffset + 4 + (($componentIndex - 1) * 2)) !== $glyphIds[$componentIndex]) {
                    $matches = false;

                    break;
                }
            }

            if ($matches) {
                return [
                    'substitutedGlyphId' => $ligGlyph,
                    'consumedGlyphCount' => $componentCount,
                ];
            }
        }

        return null;
    }

    /**
     * @param list<int> $glyphIds
     * @return array{substitutedGlyphId: int, matchedGlyphCount: int}|null
     */
    private function applyChainingContextSubstitutionSubtable(int $lookupListOffset, int $subtableOffset, array $glyphIds): ?array
    {
        $substFormat = $this->readUInt16($subtableOffset);

        if ($substFormat !== 3 || $glyphIds === []) {
            return null;
        }

        $backtrackGlyphCount = $this->readUInt16($subtableOffset + 2);

        if ($backtrackGlyphCount !== 0) {
            return null;
        }

        $offset = $subtableOffset + 4 + ($backtrackGlyphCount * 2);
        $inputGlyphCount = $this->readUInt16($offset);

        if ($inputGlyphCount < 1 || count($glyphIds) < $inputGlyphCount) {
            return null;
        }

        $offset += 2;

        for ($index = 0; $index < $inputGlyphCount; $index++) {
            $coverageOffset = $subtableOffset + $this->readUInt16($offset + ($index * 2));
            $coveredGlyphIds = $this->coverageGlyphIds($coverageOffset);

            if (!in_array($glyphIds[$index], $coveredGlyphIds, true)) {
                return null;
            }
        }

        $offset += $inputGlyphCount * 2;
        $lookaheadGlyphCount = $this->readUInt16($offset);

        if (count($glyphIds) < $inputGlyphCount + $lookaheadGlyphCount) {
            return null;
        }

        $offset += 2;

        for ($index = 0; $index < $lookaheadGlyphCount; $index++) {
            $coverageOffset = $subtableOffset + $this->readUInt16($offset + ($index * 2));
            $coveredGlyphIds = $this->coverageGlyphIds($coverageOffset);

            if (!in_array($glyphIds[$inputGlyphCount + $index], $coveredGlyphIds, true)) {
                return null;
            }
        }

        $offset += $lookaheadGlyphCount * 2;
        $substitutionCount = $this->readUInt16($offset);

        if ($substitutionCount < 1) {
            return null;
        }

        return $this->applyNestedSingleSubstitutionLookup(
            $lookupListOffset,
            $offset + 2,
            $glyphIds,
            $inputGlyphCount,
        );
    }

    /**
     * @param list<int> $glyphIds
     * @return array{substitutedGlyphId: int, matchedGlyphCount: int}|null
     */
    private function applyNestedSingleSubstitutionLookup(
        int $lookupListOffset,
        int $lookupRecordOffset,
        array $glyphIds,
        int $matchedGlyphCount,
    ): ?array {
        $sequenceIndex = $this->readUInt16($lookupRecordOffset);
        $nestedLookupIndex = $this->readUInt16($lookupRecordOffset + 2);

        if (!isset($glyphIds[$sequenceIndex])) {
            return null;
        }

        $nestedLookupOffset = $lookupListOffset + $this->readUInt16($lookupListOffset + 2 + ($nestedLookupIndex * 2));
        $nestedLookupType = $this->readUInt16($nestedLookupOffset);
        $nestedSubtableCount = $this->readUInt16($nestedLookupOffset + 4);

        if ($nestedLookupType !== 1 || $nestedSubtableCount < 1) {
            return null;
        }

        for ($subtableIndex = 0; $subtableIndex < $nestedSubtableCount; $subtableIndex++) {
            $nestedSubtableOffset = $nestedLookupOffset + $this->readUInt16($nestedLookupOffset + 6 + ($subtableIndex * 2));
            $substitutedGlyphId = $this->applySingleSubstitutionSubtable($nestedSubtableOffset, $glyphIds[$sequenceIndex]);

            if ($substitutedGlyphId !== null) {
                return [
                    'substitutedGlyphId' => $substitutedGlyphId,
                    'matchedGlyphCount' => $matchedGlyphCount,
                ];
            }
        }

        return null;
    }

    /**
     * @param list<int> $glyphIds
     * @return array{substitutedGlyphId: int, matchedGlyphCount: int}|null
     */
    private function applyContextSubstitutionSubtable(int $lookupListOffset, int $subtableOffset, array $glyphIds): ?array
    {
        $substFormat = $this->readUInt16($subtableOffset);

        if ($substFormat !== 3 || $glyphIds === []) {
            return null;
        }

        $glyphCount = $this->readUInt16($subtableOffset + 2);
        $substitutionCount = $this->readUInt16($subtableOffset + 4);

        if (count($glyphIds) < $glyphCount || $substitutionCount < 1) {
            return null;
        }

        for ($index = 0; $index < $glyphCount; $index++) {
            $coverageOffset = $subtableOffset + $this->readUInt16($subtableOffset + 6 + ($index * 2));
            $coveredGlyphIds = $this->coverageGlyphIds($coverageOffset);

            if (!in_array($glyphIds[$index], $coveredGlyphIds, true)) {
                return null;
            }
        }

        return $this->applyNestedSingleSubstitutionLookup(
            $lookupListOffset,
            $subtableOffset + 6 + ($glyphCount * 2),
            $glyphIds,
            $glyphCount,
        );
    }

    /**
     * @return list<int>
     */
    private function coverageGlyphIds(int $coverageOffset): array
    {
        if (isset($this->coverageGlyphIdsCache[$coverageOffset])) {
            return $this->coverageGlyphIdsCache[$coverageOffset];
        }

        $format = $this->readUInt16($coverageOffset);

        if ($format === 1) {
            $glyphCount = $this->readUInt16($coverageOffset + 2);
            $glyphIds = [];

            for ($index = 0; $index < $glyphCount; $index++) {
                $glyphIds[] = $this->readUInt16($coverageOffset + 4 + ($index * 2));
            }

            return $this->coverageGlyphIdsCache[$coverageOffset] = $glyphIds;
        }

        if ($format === 2) {
            $rangeCount = $this->readUInt16($coverageOffset + 2);
            $glyphIds = [];

            for ($index = 0; $index < $rangeCount; $index++) {
                $rangeOffset = $coverageOffset + 4 + ($index * 6);
                $startGlyphId = $this->readUInt16($rangeOffset);
                $endGlyphId = $this->readUInt16($rangeOffset + 2);

                for ($glyphId = $startGlyphId; $glyphId <= $endGlyphId; $glyphId++) {
                    $glyphIds[] = $glyphId;
                }
            }

            return $this->coverageGlyphIdsCache[$coverageOffset] = $glyphIds;
        }

        throw new InvalidArgumentException(sprintf(
            "Unsupported GSUB coverage format '%d'.",
            $format,
        ));
    }

    /**
     * @return array{x: int, y: int}
     */
    private function readAnchor(int $anchorOffset): array
    {
        $anchorFormat = $this->readUInt16($anchorOffset);

        if ($anchorFormat !== 1) {
            throw new InvalidArgumentException(sprintf(
                "Unsupported GPOS anchor format '%d'.",
                $anchorFormat,
            ));
        }

        return [
            'x' => $this->readInt16($anchorOffset + 2),
            'y' => $this->readInt16($anchorOffset + 4),
        ];
    }

    /**
     * @return array{format: int, offset: int}
     */
    private function findPreferredCmapSubtable(): array
    {
        if ($this->preferredCmapSubtable !== null) {
            return $this->preferredCmapSubtable;
        }

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
                    return $this->preferredCmapSubtable = [
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

    /**
     * @return array{lookupListOffset: int, lookupCount: int}|null
     */
    private function layoutLookupList(string $tableTag): ?array
    {
        if (array_key_exists($tableTag, $this->layoutLookupListCache)) {
            return $this->layoutLookupListCache[$tableTag];
        }

        if (!$this->hasTable($tableTag)) {
            return $this->layoutLookupListCache[$tableTag] = null;
        }

        $layoutOffset = $this->requiredTableOffset($tableTag);
        $lookupListOffset = $layoutOffset + $this->readUInt16($layoutOffset + 8);

        return $this->layoutLookupListCache[$tableTag] = [
            'lookupListOffset' => $lookupListOffset,
            'lookupCount' => $this->readUInt16($lookupListOffset),
        ];
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
