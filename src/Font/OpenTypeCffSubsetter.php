<?php

declare(strict_types=1);

namespace Kalle\Pdf\Font;

use InvalidArgumentException;

use function array_keys;
use function count;
use function implode;
use function strlen;
use function str_pad;
use function usort;

final readonly class OpenTypeCffSubsetter
{
    public function __construct(
        private OpenTypeFontParser $parser,
    ) {
        if ($this->parser->outlineType() !== OpenTypeOutlineType::CFF) {
            throw new InvalidArgumentException('OpenTypeCffSubsetter only supports OpenType CFF fonts.');
        }
    }

    /**
     * @param list<int> $codePoints
     */
    public function subset(array $codePoints, ?string $postScriptName = null): string
    {
        $cffParser = new CffFontParser($this->parser->tableBytes('CFF '));
        $subsetGlyphIds = [0];
        $charStrings = $cffParser->charStrings();
        $charsetSids = $cffParser->charsetSids();

        foreach ($codePoints as $codePoint) {
            $subsetGlyphIds[] = $this->parser->getGlyphIdForCodePoint($codePoint);
        }

        $subsetCharStrings = [];
        $subsetCharsetSids = [];
        $horizontalMetrics = [];

        foreach ($subsetGlyphIds as $subsetIndex => $glyphId) {
            $subsetCharStrings[] = $charStrings[$glyphId] ?? "\x0E";
            $horizontalMetrics[] = $this->parser->getHorizontalMetricsForGlyphId($glyphId);

            if ($subsetIndex === 0) {
                continue;
            }

            $subsetCharsetSids[] = $charsetSids[$glyphId] ?? 0;
        }

        return $this->buildSfnt([
            ...$this->preservedTables(),
            'CFF ' => $this->buildSubsetCffTable(
                $cffParser,
                $subsetCharStrings,
                $subsetCharsetSids,
                $postScriptName ?? $cffParser->postScriptName(),
            ),
            'cmap' => $this->buildUnicodeCmapTable($codePoints),
            'hhea' => $this->buildSubsetHheaTable(count($horizontalMetrics)),
            'hmtx' => $this->buildSubsetHmtxTable($horizontalMetrics),
            'maxp' => $this->buildSubsetMaxpTable(count($horizontalMetrics)),
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function preservedTables(): array
    {
        $tables = [];

        foreach ($this->parser->tableTags() as $tag) {
            if ($tag === 'CFF ' || $tag === 'cmap' || $tag === 'hhea' || $tag === 'hmtx' || $tag === 'maxp') {
                continue;
            }

            $tables[$tag] = $this->parser->tableBytes($tag);
        }

        return $tables;
    }

    /**
     * @param list<string> $charStrings
     * @param list<int> $charsetSids
     */
    private function buildSubsetCffTable(CffFontParser $parser, array $charStrings, array $charsetSids, string $postScriptName): string
    {
        $nameIndex = $this->buildCffIndex([$postScriptName]);
        $header = "\x01\x00\x04\x01";
        $stringIndex = pack('n', 0);
        $globalSubrIndex = pack('n', 0);
        $charset = "\x00";

        foreach ($charsetSids as $sid) {
            $charset .= pack('n', $sid);
        }

        $charStringsIndex = $this->buildCffIndex($charStrings);

        $bbox = $parser->fontBoundingBox();
        $italicAngle = (int) $parser->italicAngle();
        $topDict = '';
        $topDictIndex = '';

        do {
            $topDictIndex = $this->buildCffIndex([$topDict]);
            $charsetOffset = strlen($header) + strlen($nameIndex) + strlen($topDictIndex) + strlen($stringIndex) + strlen($globalSubrIndex);
            $charStringsOffset = $charsetOffset + strlen($charset);
            $topDict = $this->cffInteger($bbox->left)
                . $this->cffInteger($bbox->bottom)
                . $this->cffInteger($bbox->right)
                . $this->cffInteger($bbox->top)
                . "\x05"
                . $this->cffInteger($italicAngle)
                . "\x0C\x02"
                . $this->cffInteger($charsetOffset)
                . "\x0F"
                . $this->cffInteger($charStringsOffset)
                . "\x11";
        } while ($this->buildCffIndex([$topDict]) !== $topDictIndex);

        return $header
            . $nameIndex
            . $topDictIndex
            . $stringIndex
            . $globalSubrIndex
            . $charset
            . $charStringsIndex;
    }

    /**
     * @param list<int> $codePoints
     */
    private function buildUnicodeCmapTable(array $codePoints): string
    {
        $groups = '';

        foreach ($codePoints as $index => $codePoint) {
            $glyphId = $index + 1;
            $groups .= pack('N', $codePoint)
                . pack('N', $codePoint)
                . pack('N', $glyphId);
        }

        $subtable = pack('n', 12)
            . pack('n', 0)
            . pack('N', 16 + (count($codePoints) * 12))
            . pack('N', 0)
            . pack('N', count($codePoints))
            . $groups;

        return pack('n', 0)
            . pack('n', 1)
            . pack('n', 3)
            . pack('n', 10)
            . pack('N', 12)
            . $subtable;
    }

    private function buildSubsetHheaTable(int $numberOfHMetrics): string
    {
        $table = $this->parser->tableBytes('hhea');

        return substr_replace($table, pack('n', $numberOfHMetrics), 34, 2);
    }

    /**
     * @param list<array{advanceWidth: int, leftSideBearing: int}> $horizontalMetrics
     */
    private function buildSubsetHmtxTable(array $horizontalMetrics): string
    {
        $table = '';

        foreach ($horizontalMetrics as $metric) {
            $table .= pack('n', $metric['advanceWidth']) . pack('n', $metric['leftSideBearing'] & 0xFFFF);
        }

        return $table;
    }

    private function buildSubsetMaxpTable(int $glyphCount): string
    {
        $table = $this->parser->tableBytes('maxp');

        return substr_replace($table, pack('n', $glyphCount), 4, 2);
    }

    /**
     * @param array<string, string> $tables
     */
    private function buildSfnt(array $tables): string
    {
        $numTables = count($tables);
        $offset = 12 + ($numTables * 16);
        $directory = '';
        $body = '';

        $tags = array_keys($tables);
        usort($tags, static fn (string $left, string $right): int => $left <=> $right);

        foreach ($tags as $tag) {
            $table = $tables[$tag];
            $length = strlen($table);
            $alignedLength = (int) (ceil($length / 4) * 4);

            $directory .= $tag
                . pack('N', 0)
                . pack('N', $offset)
                . pack('N', $length);

            $body .= str_pad($table, $alignedLength, "\x00");
            $offset += $alignedLength;
        }

        return 'OTTO'
            . pack('n', $numTables)
            . pack('n', 0)
            . pack('n', 0)
            . pack('n', 0)
            . $directory
            . $body;
    }

    /**
     * @param list<string> $items
     */
    private function buildCffIndex(array $items): string
    {
        if ($items === []) {
            return pack('n', 0);
        }

        $data = '';
        $offsets = [1];

        foreach ($items as $item) {
            $data .= $item;
            $offsets[] = strlen($data) + 1;
        }

        $maxOffset = max($offsets);
        $offSize = match (true) {
            $maxOffset <= 0xFF => 1,
            $maxOffset <= 0xFFFF => 2,
            $maxOffset <= 0xFFFFFF => 3,
            default => 4,
        };

        $encodedOffsets = '';

        foreach ($offsets as $offset) {
            $encodedOffsets .= substr(pack('N', $offset), 4 - $offSize);
        }

        return pack('n', count($items))
            . pack('C', $offSize)
            . $encodedOffsets
            . $data;
    }

    private function cffInteger(int $value): string
    {
        if ($value >= -107 && $value <= 107) {
            return pack('C', $value + 139);
        }

        if ($value >= 108 && $value <= 1131) {
            $adjusted = $value - 108;

            return pack('C', 247 + intdiv($adjusted, 256)) . pack('C', $adjusted % 256);
        }

        if ($value >= -1131 && $value <= -108) {
            $adjusted = -$value - 108;

            return pack('C', 251 + intdiv($adjusted, 256)) . pack('C', $adjusted % 256);
        }

        if ($value >= -32768 && $value <= 32767) {
            return "\x1C" . pack('n', $value & 0xFFFF);
        }

        return "\x1D" . pack('N', $value);
    }
}
