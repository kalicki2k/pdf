<?php

declare(strict_types=1);

namespace Kalle\Pdf\Font;

use function array_fill;
use function ceil;
use function count;
use function ksort;
use function pack;
use function strlen;
use function substr_replace;
use function usort;

use InvalidArgumentException;

final readonly class OpenTypeTrueTypeSubsetter
{
    public function __construct(
        private OpenTypeFontParser $parser,
    ) {
    }

    /**
     * @param list<int> $glyphIds
     */
    public function subset(array $glyphIds): string
    {
        if (
            !$this->parser->hasTable('glyf')
            || !$this->parser->hasTable('loca')
            || !$this->parser->hasTable('head')
            || !$this->parser->hasTable('hhea')
            || !$this->parser->hasTable('hmtx')
            || !$this->parser->hasTable('maxp')
        ) {
            throw new InvalidArgumentException('Font does not expose the required TrueType tables for binary subsetting.');
        }

        $glyphIds = $this->normalizeGlyphIds($glyphIds);
        $maxGlyphId = 0;

        foreach ($glyphIds as $glyphId) {
            if ($glyphId > $maxGlyphId) {
                $maxGlyphId = $glyphId;
            }
        }

        $tables = [];

        foreach ($this->parser->tableTags() as $tag) {
            if (in_array($tag, ['glyf', 'loca', 'head', 'hhea', 'hmtx', 'maxp'], true)) {
                continue;
            }

            $tables[$tag] = $tag === 'post'
                ? $this->buildPostTable()
                : $this->parser->tableBytes($tag);
        }

        [$glyfTable, $locaTable] = $this->buildGlyphTables($glyphIds, $maxGlyphId);
        $tables['glyf'] = $glyfTable;
        $tables['loca'] = $locaTable;
        $tables['hmtx'] = $this->buildHorizontalMetricsTable($maxGlyphId);
        $tables['hhea'] = $this->buildHorizontalHeaderTable($maxGlyphId + 1);
        $tables['maxp'] = $this->buildMaximumProfileTable($maxGlyphId + 1);
        $tables['head'] = $this->buildHeadTable();

        return $this->buildSfnt($tables);
    }

    /**
     * @param list<int> $glyphIds
     * @return list<int>
     */
    private function normalizeGlyphIds(array $glyphIds): array
    {
        $normalized = [0 => 0];
        $pending = $glyphIds;

        while ($pending !== []) {
            /** @var int $glyphId */
            $glyphId = array_shift($pending);

            if (isset($normalized[$glyphId])) {
                continue;
            }

            $normalized[$glyphId] = $glyphId;

            foreach ($this->compositeComponentGlyphIds($glyphId) as $componentGlyphId) {
                if (!isset($normalized[$componentGlyphId])) {
                    $pending[] = $componentGlyphId;
                }
            }
        }

        ksort($normalized);

        return array_values($normalized);
    }

    /**
     * @return list<int>
     */
    private function compositeComponentGlyphIds(int $glyphId): array
    {
        $glyphData = $this->parser->glyphDataForGlyphId($glyphId);

        if (strlen($glyphData) < 10) {
            return [];
        }

        $header = unpack('n', substr($glyphData, 0, 2));

        if (!is_array($header)) {
            return [];
        }

        /** @var int $numberOfContoursRaw */
        $numberOfContoursRaw = $header[1];
        $numberOfContours = $numberOfContoursRaw >= 0x8000
            ? $numberOfContoursRaw - 0x10000
            : $numberOfContoursRaw;

        if ($numberOfContours >= 0) {
            return [];
        }

        $offset = 10;
        $glyphIds = [];

        do {
            if (($offset + 4) > strlen($glyphData)) {
                break;
            }

            $componentHeader = unpack('nflags/nglyph', substr($glyphData, $offset, 4));

            if (!is_array($componentHeader)) {
                break;
            }

            /** @var int $flags */
            $flags = $componentHeader['flags'];
            /** @var int $componentGlyphId */
            $componentGlyphId = $componentHeader['glyph'];
            $glyphIds[] = $componentGlyphId;
            $offset += 4;

            $offset += ($flags & 0x0001) !== 0 ? 4 : 2;

            if (($flags & 0x0008) !== 0) {
                $offset += 2;
            } elseif (($flags & 0x0040) !== 0) {
                $offset += 4;
            } elseif (($flags & 0x0080) !== 0) {
                $offset += 8;
            }
        } while (($flags & 0x0020) !== 0);

        return $glyphIds;
    }

    /**
     * @param list<int> $glyphIds
     * @return array{0: string, 1: string}
     */
    private function buildGlyphTables(array $glyphIds, int $maxGlyphId): array
    {
        $included = array_fill(0, $maxGlyphId + 1, false);

        foreach ($glyphIds as $glyphId) {
            $included[$glyphId] = true;
        }

        $glyf = '';
        $offsets = [];

        for ($glyphId = 0; $glyphId <= $maxGlyphId; $glyphId++) {
            $offsets[] = strlen($glyf);

            if (!$included[$glyphId]) {
                continue;
            }

            $glyphData = $this->parser->glyphDataForGlyphId($glyphId);
            $glyf .= $glyphData;

            while (strlen($glyf) % 4 !== 0) {
                $glyf .= "\x00";
            }
        }

        $offsets[] = strlen($glyf);

        $loca = '';

        foreach ($offsets as $offset) {
            $loca .= pack('N', $offset);
        }

        return [$glyf, $loca];
    }

    private function buildHorizontalMetricsTable(int $maxGlyphId): string
    {
        $table = '';

        for ($glyphId = 0; $glyphId <= $maxGlyphId; $glyphId++) {
            $metrics = $this->parser->getHorizontalMetricsForGlyphId($glyphId);
            $table .= pack('n', $metrics['advanceWidth']);
            $table .= pack('n', $metrics['leftSideBearing'] & 0xFFFF);
        }

        return $table;
    }

    private function buildHorizontalHeaderTable(int $numberOfHMetrics): string
    {
        $table = $this->parser->tableBytes('hhea');

        return substr_replace($table, pack('n', $numberOfHMetrics), 34, 2);
    }

    private function buildMaximumProfileTable(int $numGlyphs): string
    {
        $table = $this->parser->tableBytes('maxp');

        return substr_replace($table, pack('n', $numGlyphs), 4, 2);
    }

    private function buildHeadTable(): string
    {
        $table = $this->parser->tableBytes('head');
        $table = substr_replace($table, pack('N', 0), 8, 4);

        return substr_replace($table, pack('n', 1), 50, 2);
    }

    private function buildPostTable(): string
    {
        $table = $this->parser->tableBytes('post');

        if (strlen($table) < 32) {
            return $table;
        }

        // Subset fonts do not preserve a meaningful glyph-name table. Emitting
        // a minimal format 3.0 post table keeps the font program internally
        // consistent for validators without carrying stale glyph-name metadata.
        return pack('N', 0x00030000) . substr($table, 4, 28);
    }

    /**
     * @param array<string, string> $tables
     */
    private function buildSfnt(array $tables): string
    {
        $sfnt = $this->buildSfntWithHeadAdjustment($tables, 0);
        $checksumAdjustment = (0xB1B0AFBA - $this->tableChecksum($sfnt)) & 0xFFFFFFFF;

        return $this->buildSfntWithHeadAdjustment($tables, $checksumAdjustment);
    }

    /**
     * @param array<string, string> $tables
     */
    private function buildSfntWithHeadAdjustment(array $tables, int $headAdjustment): string
    {
        $prepared = $tables;

        if (isset($prepared['head'])) {
            $prepared['head'] = substr_replace($prepared['head'], pack('N', $headAdjustment), 8, 4);
        }

        $numTables = count($prepared);
        $maxPower = 1;
        $entrySelector = 0;

        while (($maxPower * 2) <= $numTables) {
            $maxPower *= 2;
            $entrySelector++;
        }

        $searchRange = $maxPower * 16;
        $rangeShift = ($numTables * 16) - $searchRange;
        $offset = 12 + ($numTables * 16);
        $directory = '';
        $body = '';

        $tags = array_keys($prepared);
        usort($tags, static fn (string $left, string $right): int => $left <=> $right);

        foreach ($tags as $tag) {
            $table = $prepared[$tag];
            $length = strlen($table);
            $aligned = (int) (ceil($length / 4) * 4);
            $padded = str_pad($table, $aligned, "\x00");

            $directory .= $tag
                . pack('N', $this->tableChecksum($table))
                . pack('N', $offset)
                . pack('N', $length);
            $body .= $padded;
            $offset += $aligned;
        }

        return "\x00\x01\x00\x00"
            . pack('n', $numTables)
            . pack('n', $searchRange)
            . pack('n', $entrySelector)
            . pack('n', $rangeShift)
            . $directory
            . $body;
    }

    private function tableChecksum(string $table): int
    {
        $padded = str_pad($table, (int) (ceil(strlen($table) / 4) * 4), "\x00");
        $checksum = 0;

        for ($offset = 0, $length = strlen($padded); $offset < $length; $offset += 4) {
            $value = unpack('N', substr($padded, $offset, 4));

            if (!is_array($value)) {
                continue;
            }

            /** @var int $chunk */
            $chunk = $value[1];
            $checksum = ($checksum + $chunk) & 0xFFFFFFFF;
        }

        return $checksum;
    }
}
