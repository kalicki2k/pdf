<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Font;

use function count;
use function str_pad;
use function str_split;
use function strlen;
use function substr_replace;
use function usort;

final class TrueTypeFontFixture
{
    public static function minimalTrueTypeFontBytes(): string
    {
        return self::minimalSfntFontBytes("\x00\x01\x00\x00");
    }

    public static function minimalCffOpenTypeFontBytes(): string
    {
        $cmap = self::buildCmapTable();
        $head = self::buildHeadTable();
        $hhea = self::buildHheaTable();
        $maxp = self::buildMaxpTable();
        $hmtx = self::buildHmtxTable();
        $name = self::buildNameTable();
        $post = self::buildPostTable();
        $cff = self::buildMinimalCffTable();

        return self::buildSfnt('OTTO', [
            'CFF ' => $cff,
            'cmap' => $cmap,
            'head' => $head,
            'hhea' => $hhea,
            'hmtx' => $hmtx,
            'maxp' => $maxp,
            'name' => $name,
            'post' => $post,
        ]);
    }

    public static function minimalUnicodeTrueTypeFontBytes(): string
    {
        $head = self::buildSubsettableHeadTable();
        $hhea = self::buildUnicodeHheaTable();
        $maxp = self::buildUnicodeMaxpTable();
        $hmtx = self::buildUnicodeHmtxTable();
        $name = self::buildNameTable();
        $post = self::buildPostTable();
        $cmap = self::buildUnicodeCmapTable();
        [$glyf, $loca] = self::buildUnicodeGlyphTables();

        return self::buildSfnt("\x00\x01\x00\x00", [
            'cmap' => $cmap,
            'glyf' => $glyf,
            'head' => $head,
            'hhea' => $hhea,
            'hmtx' => $hmtx,
            'loca' => $loca,
            'maxp' => $maxp,
            'name' => $name,
            'post' => $post,
        ]);
    }

    public static function minimalUnicodeCffOpenTypeFontBytes(): string
    {
        $head = self::buildHeadTable();
        $hhea = self::buildUnicodeHheaTable();
        $maxp = self::buildUnicodeMaxpTable();
        $hmtx = self::buildUnicodeHmtxTable();
        $name = self::buildNameTable();
        $post = self::buildPostTable();
        $cff = self::buildMinimalCffTable();
        $cmap = self::buildUnicodeCmapTable();

        return self::buildSfnt('OTTO', [
            'CFF ' => $cff,
            'cmap' => $cmap,
            'head' => $head,
            'hhea' => $hhea,
            'hmtx' => $hmtx,
            'maxp' => $maxp,
            'name' => $name,
            'post' => $post,
        ]);
    }

    public static function minimalLatinLigaTrueTypeFontBytes(): string
    {
        $head = self::buildSubsettableHeadTable();
        $hhea = self::buildLatinLigaHheaTable();
        $maxp = self::buildLatinLigaMaxpTable();
        $hmtx = self::buildLatinLigaHmtxTable();
        $name = self::buildNameTable();
        $post = self::buildPostTable();
        $cmap = self::buildLatinLigaCmapTable();
        [$glyf, $loca] = self::buildLatinLigaGlyphTables();
        $gsub = self::buildLatinLigaGsubTable();

        return self::buildSfnt("\x00\x01\x00\x00", [
            'GSUB' => $gsub,
            'cmap' => $cmap,
            'glyf' => $glyf,
            'head' => $head,
            'hhea' => $hhea,
            'hmtx' => $hmtx,
            'loca' => $loca,
            'maxp' => $maxp,
            'name' => $name,
            'post' => $post,
        ]);
    }

    public static function minimalLatinContextualTrueTypeFontBytes(): string
    {
        $head = self::buildSubsettableHeadTable();
        $hhea = self::buildLatinLigaHheaTable();
        $maxp = self::buildLatinLigaMaxpTable();
        $hmtx = self::buildLatinContextualHmtxTable();
        $name = self::buildNameTable();
        $post = self::buildPostTable();
        $cmap = self::buildLatinLigaCmapTable();
        [$glyf, $loca] = self::buildLatinLigaGlyphTables();
        $gsub = self::buildLatinContextualGsubTable();

        return self::buildSfnt("\x00\x01\x00\x00", [
            'GSUB' => $gsub,
            'cmap' => $cmap,
            'glyf' => $glyf,
            'head' => $head,
            'hhea' => $hhea,
            'hmtx' => $hmtx,
            'loca' => $loca,
            'maxp' => $maxp,
            'name' => $name,
            'post' => $post,
        ]);
    }

    public static function minimalArabicGsubTrueTypeFontBytes(): string
    {
        $head = self::buildSubsettableHeadTable();
        $hhea = self::buildArabicHheaTable();
        $maxp = self::buildArabicMaxpTable();
        $hmtx = self::buildArabicHmtxTable();
        $name = self::buildNameTable();
        $post = self::buildPostTable();
        $cmap = self::buildArabicCmapTable();
        [$glyf, $loca] = self::buildArabicGlyphTables();
        $gsub = self::buildArabicGsubTable();
        $gpos = self::buildArabicGposTable();

        return self::buildSfnt("\x00\x01\x00\x00", [
            'GPOS' => $gpos,
            'GSUB' => $gsub,
            'cmap' => $cmap,
            'glyf' => $glyf,
            'head' => $head,
            'hhea' => $hhea,
            'hmtx' => $hmtx,
            'loca' => $loca,
            'maxp' => $maxp,
            'name' => $name,
            'post' => $post,
        ]);
    }

    public static function minimalDevanagariGsubTrueTypeFontBytes(): string
    {
        $head = self::buildSubsettableHeadTable();
        $hhea = self::buildDevanagariHheaTable();
        $maxp = self::buildDevanagariMaxpTable();
        $hmtx = self::buildDevanagariHmtxTable();
        $name = self::buildNameTable();
        $post = self::buildPostTable();
        $cmap = self::buildDevanagariCmapTable();
        [$glyf, $loca] = self::buildDevanagariGlyphTables();
        $gsub = self::buildDevanagariGsubTable();
        $gpos = self::buildDevanagariGposTable();

        return self::buildSfnt("\x00\x01\x00\x00", [
            'GPOS' => $gpos,
            'GSUB' => $gsub,
            'cmap' => $cmap,
            'glyf' => $glyf,
            'head' => $head,
            'hhea' => $hhea,
            'hmtx' => $hmtx,
            'loca' => $loca,
            'maxp' => $maxp,
            'name' => $name,
            'post' => $post,
        ]);
    }

    private static function minimalSfntFontBytes(string $signature): string
    {
        $head = self::buildHeadTable();
        $hhea = self::buildHheaTable();
        $maxp = self::buildMaxpTable();
        $hmtx = self::buildHmtxTable();
        $name = self::buildNameTable();
        $post = self::buildPostTable();
        $cmap = self::buildCmapTable();

        $tables = [
            'cmap' => $cmap,
            'head' => $head,
            'hhea' => $hhea,
            'hmtx' => $hmtx,
            'maxp' => $maxp,
            'name' => $name,
            'post' => $post,
        ];

        return self::buildSfnt($signature, $tables);
    }

    /**
     * @param array<string, string> $tables
     */
    private static function buildSfnt(string $signature, array $tables): string
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

        return $signature
            . pack('n', $numTables)
            . pack('n', 0)
            . pack('n', 0)
            . pack('n', 0)
            . $directory
            . $body;
    }

    private static function buildHeadTable(): string
    {
        return
            pack('N', 0x00010000)
            . pack('N', 0)
            . pack('N', 0)
            . pack('N', 0x5F0F3CF5)
            . pack('n', 0)
            . pack('n', 1000)
            . str_repeat("\x00", 16)
            . pack('n', 0xFFCE)
            . pack('n', 0xFF38)
            . pack('n', 950)
            . pack('n', 800)
            . pack('n', 0)
            . pack('n', 0)
            . pack('n', 0)
            . pack('n', 0)
            . pack('n', 0);
    }

    private static function buildSubsettableHeadTable(): string
    {
        $table = self::buildHeadTable();

        return substr_replace($table, pack('n', 1), 50, 2);
    }

    private static function buildHheaTable(): string
    {
        $table = str_repeat("\x00", 36);

        $table = substr_replace($table, pack('N', 0x00010000), 0, 4);
        $table = substr_replace($table, pack('n', 800), 4, 2);
        $table = substr_replace($table, pack('n', 0xFF38), 6, 2);
        $table = substr_replace($table, pack('n', 1000), 10, 2);
        $table = substr_replace($table, pack('n', 600), 16, 2);
        $table = substr_replace($table, pack('n', 1), 18, 2);
        $table = substr_replace($table, pack('n', 2), 34, 2);

        return $table;
    }

    private static function buildMaxpTable(): string
    {
        return pack('N', 0x00010000) . pack('n', 2);
    }

    private static function buildUnicodeMaxpTable(): string
    {
        return pack('N', 0x00010000) . pack('n', 5);
    }

    private static function buildHmtxTable(): string
    {
        return pack('n', 500) . pack('n', 0)
            . pack('n', 600) . pack('n', 0);
    }

    private static function buildUnicodeHmtxTable(): string
    {
        return pack('n', 500) . pack('n', 0)
            . pack('n', 600) . pack('n', 0)
            . pack('n', 700) . pack('n', 0)
            . pack('n', 800) . pack('n', 0)
            . pack('n', 900) . pack('n', 0);
    }

    private static function buildPostTable(): string
    {
        return pack('N', 0x00030000)
            . pack('N', 0)
            . pack('n', 0)
            . pack('n', 0)
            . str_repeat("\x00", 16);
    }

    private static function buildNameTable(): string
    {
        $postScriptName = 'TestFont-Regular';
        $encoded = '';

        foreach (str_split($postScriptName) as $character) {
            $encoded .= "\x00" . $character;
        }

        return pack('n', 0)
            . pack('n', 1)
            . pack('n', 18)
            . pack('n', 3)
            . pack('n', 1)
            . pack('n', 0x0409)
            . pack('n', 6)
            . pack('n', strlen($encoded))
            . pack('n', 0)
            . $encoded;
    }

    private static function buildCmapTable(): string
    {
        $subtable = pack('n', 4)
            . pack('n', 32)
            . pack('n', 0)
            . pack('n', 4)
            . pack('n', 4)
            . pack('n', 1)
            . pack('n', 0)
            . pack('n', 65)
            . pack('n', 0xFFFF)
            . pack('n', 0)
            . pack('n', 65)
            . pack('n', 0xFFFF)
            . pack('n', 0xFFC0)
            . pack('n', 1)
            . pack('n', 0)
            . pack('n', 0);

        return pack('n', 0)
            . pack('n', 1)
            . pack('n', 3)
            . pack('n', 1)
            . pack('N', 12)
            . $subtable;
    }

    private static function buildUnicodeHheaTable(): string
    {
        $table = self::buildHheaTable();

        return substr_replace($table, pack('n', 5), 34, 2);
    }

    private static function buildArabicHheaTable(): string
    {
        $table = self::buildHheaTable();

        return substr_replace($table, pack('n', 11), 34, 2);
    }

    private static function buildLatinLigaHheaTable(): string
    {
        $table = self::buildHheaTable();

        return substr_replace($table, pack('n', 4), 34, 2);
    }

    private static function buildDevanagariHheaTable(): string
    {
        $table = self::buildHheaTable();

        return substr_replace($table, pack('n', 10), 34, 2);
    }

    private static function buildUnicodeCmapTable(): string
    {
        $subtable = pack('n', 12)
            . pack('n', 0)
            . pack('N', 64)
            . pack('N', 0)
            . pack('N', 4)
            . pack('N', 0x41)
            . pack('N', 0x41)
            . pack('N', 1)
            . pack('N', 0x0416)
            . pack('N', 0x0416)
            . pack('N', 2)
            . pack('N', 0x4E2D)
            . pack('N', 0x4E2D)
            . pack('N', 3)
            . pack('N', 0x1F600)
            . pack('N', 0x1F600)
            . pack('N', 4);

        return pack('n', 0)
            . pack('n', 1)
            . pack('n', 3)
            . pack('n', 10)
            . pack('N', 12)
            . $subtable;
    }

    private static function buildArabicCmapTable(): string
    {
        $subtable = pack('n', 12)
            . pack('n', 0)
            . pack('N', 76)
            . pack('N', 0)
            . pack('N', 5)
            . pack('N', 0x0627)
            . pack('N', 0x0627)
            . pack('N', 1)
            . pack('N', 0x0628)
            . pack('N', 0x0628)
            . pack('N', 2)
            . pack('N', 0x0644)
            . pack('N', 0x0644)
            . pack('N', 3)
            . pack('N', 0x064E)
            . pack('N', 0x064E)
            . pack('N', 9)
            . pack('N', 0x0651)
            . pack('N', 0x0651)
            . pack('N', 10);

        return pack('n', 0)
            . pack('n', 1)
            . pack('n', 3)
            . pack('n', 10)
            . pack('N', 12)
            . $subtable;
    }

    private static function buildLatinLigaCmapTable(): string
    {
        $subtable = pack('n', 12)
            . pack('n', 0)
            . pack('N', 40)
            . pack('N', 0)
            . pack('N', 2)
            . pack('N', 0x66)
            . pack('N', 0x66)
            . pack('N', 1)
            . pack('N', 0x69)
            . pack('N', 0x69)
            . pack('N', 2);

        return pack('n', 0)
            . pack('n', 1)
            . pack('n', 3)
            . pack('n', 10)
            . pack('N', 12)
            . $subtable;
    }

    private static function buildDevanagariCmapTable(): string
    {
        $subtable = pack('n', 12)
            . pack('n', 0)
            . pack('N', 100)
            . pack('N', 0)
            . pack('N', 7)
            . pack('N', 0x0938)
            . pack('N', 0x0938)
            . pack('N', 1)
            . pack('N', 0x0924)
            . pack('N', 0x0924)
            . pack('N', 2)
            . pack('N', 0x0915)
            . pack('N', 0x0915)
            . pack('N', 3)
            . pack('N', 0x0930)
            . pack('N', 0x0930)
            . pack('N', 4)
            . pack('N', 0x093F)
            . pack('N', 0x093F)
            . pack('N', 5)
            . pack('N', 0x0902)
            . pack('N', 0x0902)
            . pack('N', 10)
            . pack('N', 0x093C)
            . pack('N', 0x093C)
            . pack('N', 11);

        return pack('n', 0)
            . pack('n', 1)
            . pack('n', 3)
            . pack('n', 10)
            . pack('N', 12)
            . $subtable;
    }

    private static function buildArabicMaxpTable(): string
    {
        return pack('N', 0x00010000) . pack('n', 11);
    }

    private static function buildLatinLigaMaxpTable(): string
    {
        return pack('N', 0x00010000) . pack('n', 4);
    }

    private static function buildDevanagariMaxpTable(): string
    {
        return pack('N', 0x00010000) . pack('n', 12);
    }

    private static function buildArabicHmtxTable(): string
    {
        return pack('n', 500) . pack('n', 0)
            . pack('n', 510) . pack('n', 0)
            . pack('n', 520) . pack('n', 0)
            . pack('n', 530) . pack('n', 0)
            . pack('n', 610) . pack('n', 0)
            . pack('n', 620) . pack('n', 0)
            . pack('n', 630) . pack('n', 0)
            . pack('n', 640) . pack('n', 0)
            . pack('n', 700) . pack('n', 0)
            . pack('n', 200) . pack('n', 0)
            . pack('n', 180) . pack('n', 0);
    }

    private static function buildLatinLigaHmtxTable(): string
    {
        return pack('n', 500) . pack('n', 0)
            . pack('n', 510) . pack('n', 0)
            . pack('n', 520) . pack('n', 0)
            . pack('n', 700) . pack('n', 0);
    }

    private static function buildLatinContextualHmtxTable(): string
    {
        return pack('n', 500) . pack('n', 0)
            . pack('n', 510) . pack('n', 0)
            . pack('n', 520) . pack('n', 0)
            . pack('n', 530) . pack('n', 0);
    }

    private static function buildDevanagariHmtxTable(): string
    {
        return pack('n', 500) . pack('n', 0)
            . pack('n', 510) . pack('n', 0)
            . pack('n', 520) . pack('n', 0)
            . pack('n', 530) . pack('n', 0)
            . pack('n', 540) . pack('n', 0)
            . pack('n', 200) . pack('n', 0)
            . pack('n', 210) . pack('n', 0)
            . pack('n', 220) . pack('n', 0)
            . pack('n', 230) . pack('n', 0)
            . pack('n', 240) . pack('n', 0)
            . pack('n', 120) . pack('n', 0)
            . pack('n', 100) . pack('n', 0);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private static function buildUnicodeGlyphTables(): array
    {
        $glyphs = [
            pack('nnnnn', 0, 0, 0, 0, 0),
            pack('nnnnn', 0, 0, 0, 400, 700),
            pack('nnnnn', 0, 0, 0, 500, 700),
            pack('nnnnn', 0, 0, 0, 600, 700),
            pack('nnnnn', 0xFFFF, 0, 0, 700, 700)
                . pack('nn', 0x0023, 2)
                . pack('nn', 0, 0)
                . pack('nn', 0x0003, 3)
                . pack('nn', 0, 0),
        ];

        $glyf = '';
        $offsets = [];

        foreach ($glyphs as $glyph) {
            $offsets[] = strlen($glyf);
            $glyf .= $glyph;

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

    /**
     * @return array{0: string, 1: string}
     */
    private static function buildArabicGlyphTables(): array
    {
        $glyphs = [];

        for ($glyphId = 0; $glyphId < 9; $glyphId++) {
            $glyphs[] = pack('nnnnn', 0, 0, 0, 300 + ($glyphId * 10), 700);
        }

        $glyphs[] = pack('nnnnn', 0, 0, 0, 120, 200);
        $glyphs[] = pack('nnnnn', 0, 0, 0, 100, 180);

        $glyf = '';
        $offsets = [];

        foreach ($glyphs as $glyph) {
            $offsets[] = strlen($glyf);
            $glyf .= $glyph;

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

    /**
     * @return array{0: string, 1: string}
     */
    private static function buildLatinLigaGlyphTables(): array
    {
        $glyphs = [
            pack('nnnnn', 0, 0, 0, 300, 700),
            pack('nnnnn', 0, 0, 0, 310, 700),
            pack('nnnnn', 0, 0, 0, 320, 700),
            pack('nnnnn', 0, 0, 0, 420, 700),
        ];

        $glyf = '';
        $offsets = [];

        foreach ($glyphs as $glyph) {
            $offsets[] = strlen($glyf);
            $glyf .= $glyph;

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

    /**
     * @return array{0: string, 1: string}
     */
    private static function buildDevanagariGlyphTables(): array
    {
        $glyphs = [];

        for ($glyphId = 0; $glyphId < 12; $glyphId++) {
            $glyphs[] = pack('nnnnn', 0, 0, 0, 300 + ($glyphId * 10), 700);
        }

        $glyf = '';
        $offsets = [];

        foreach ($glyphs as $glyph) {
            $offsets[] = strlen($glyf);
            $glyf .= $glyph;

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

    private static function buildArabicGsubTable(): string
    {
        $scriptList = pack('n', 0);

        $featureTags = ['fina', 'init', 'isol', 'medi', 'rlig'];
        $featureTables = [];

        foreach ([0, 1, 2, 3] as $lookupIndex) {
            $featureTables[] = pack('n', 0) . pack('n', 1) . pack('n', $lookupIndex);
        }
        $featureTables[] = pack('n', 0) . pack('n', 1) . pack('n', 4);

        $featureList = pack('n', count($featureTags));
        $featureOffsets = [];
        $featureOffset = 2 + (count($featureTags) * 6);

        foreach ($featureTables as $table) {
            $featureOffsets[] = $featureOffset;
            $featureOffset += strlen($table);
        }

        foreach ($featureTags as $index => $tag) {
            $featureList .= $tag . pack('n', $featureOffsets[$index]);
        }

        foreach ($featureTables as $table) {
            $featureList .= $table;
        }

        $lookupList = self::buildLookupList([
            self::buildSingleSubstitutionLookup(2, 5),
            self::buildSingleSubstitutionLookup(2, 6),
            self::buildSingleSubstitutionLookup(2, 4),
            self::buildSingleSubstitutionLookup(2, 7),
            self::buildLigatureSubstitutionLookup(3, [1], 8),
        ]);

        return pack('N', 0x00010000)
            . pack('n', 10)
            . pack('n', 10 + strlen($scriptList))
            . pack('n', 10 + strlen($scriptList) + strlen($featureList))
            . $scriptList
            . $featureList
            . $lookupList;
    }

    private static function buildLatinLigaGsubTable(): string
    {
        $scriptList = pack('n', 0);
        $featureList = pack('n', 1)
            . 'liga'
            . pack('n', 8)
            . pack('n', 0)
            . pack('n', 1)
            . pack('n', 0);
        $lookupList = self::buildLookupList([
            self::buildLigatureSubstitutionLookup(1, [2], 3),
        ]);

        return pack('N', 0x00010000)
            . pack('n', 10)
            . pack('n', 10 + strlen($scriptList))
            . pack('n', 10 + strlen($scriptList) + strlen($featureList))
            . $scriptList
            . $featureList
            . $lookupList;
    }

    private static function buildLatinContextualGsubTable(): string
    {
        $scriptList = pack('n', 0);
        $featureList = pack('n', 1)
            . 'calt'
            . pack('n', 8)
            . pack('n', 0)
            . pack('n', 1)
            . pack('n', 0);
        $lookupList = self::buildLookupList([
            self::buildContextSubstitutionLookupFormat3(
                coverageGlyphIds: [1, 2],
                sequenceIndex: 0,
                nestedLookupIndex: 1,
            ),
            self::buildSingleSubstitutionLookup(1, 3),
        ]);

        return pack('N', 0x00010000)
            . pack('n', 10)
            . pack('n', 10 + strlen($scriptList))
            . pack('n', 10 + strlen($scriptList) + strlen($featureList))
            . $scriptList
            . $featureList
            . $lookupList;
    }

    private static function buildDevanagariGsubTable(): string
    {
        $scriptList = pack('n', 0);
        $featureTags = ['half', 'pref', 'rphf'];
        $featureTables = [
            pack('n', 0) . pack('n', 2) . pack('n', 0) . pack('n', 1),
            pack('n', 0) . pack('n', 1) . pack('n', 2),
            pack('n', 0) . pack('n', 1) . pack('n', 3),
        ];
        $featureList = pack('n', count($featureTags));
        $featureOffsets = [];
        $featureOffset = 2 + (count($featureTags) * 6);

        foreach ($featureTables as $table) {
            $featureOffsets[] = $featureOffset;
            $featureOffset += strlen($table);
        }

        foreach ($featureTags as $index => $tag) {
            $featureList .= $tag . pack('n', $featureOffsets[$index]);
        }

        foreach ($featureTables as $table) {
            $featureList .= $table;
        }

        $lookupList = self::buildLookupList([
            self::buildSingleSubstitutionLookup(1, 6),
            self::buildSingleSubstitutionLookup(3, 9),
            self::buildSingleSubstitutionLookup(2, 7),
            self::buildSingleSubstitutionLookup(4, 8),
        ]);

        return pack('N', 0x00010000)
            . pack('n', 10)
            . pack('n', 10 + strlen($scriptList))
            . pack('n', 10 + strlen($scriptList) + strlen($featureList))
            . $scriptList
            . $featureList
            . $lookupList;
    }

    private static function buildDevanagariGposTable(): string
    {
        $scriptList = pack('n', 0);
        $featureTags = ['mark', 'mkmk'];
        $featureTables = [
            pack('n', 0) . pack('n', 1) . pack('n', 0),
            pack('n', 0) . pack('n', 1) . pack('n', 1),
        ];
        $featureList = pack('n', count($featureTags));
        $featureOffsets = [];
        $featureOffset = 2 + (count($featureTags) * 6);

        foreach ($featureTables as $table) {
            $featureOffsets[] = $featureOffset;
            $featureOffset += strlen($table);
        }

        foreach ($featureTags as $index => $tag) {
            $featureList .= $tag . pack('n', $featureOffsets[$index]);
        }

        foreach ($featureTables as $table) {
            $featureList .= $table;
        }

        $lookupList = self::buildLookupList([
            self::buildMarkToBasePositioningLookup(
                marks: [
                    10 => ['class' => 0, 'x' => 20, 'y' => 40],
                    11 => ['class' => 0, 'x' => 30, 'y' => 50],
                ],
                bases: [
                    3 => [0 => ['x' => 250, 'y' => 620]],
                ],
            ),
            self::buildMarkToMarkPositioningLookup(
                marks: [
                    11 => ['class' => 0, 'x' => 30, 'y' => 50],
                ],
                baseMarks: [
                    10 => [0 => ['x' => 100, 'y' => 160]],
                ],
            ),
        ]);

        return pack('N', 0x00010000)
            . pack('n', 10)
            . pack('n', 10 + strlen($scriptList))
            . pack('n', 10 + strlen($scriptList) + strlen($featureList))
            . $scriptList
            . $featureList
            . $lookupList;
    }

    private static function buildArabicGposTable(): string
    {
        $scriptList = pack('n', 0);
        $featureTags = ['kern', 'mark', 'mkmk'];
        $featureTables = [
            pack('n', 0) . pack('n', 2) . pack('n', 0) . pack('n', 1),
            pack('n', 0) . pack('n', 1) . pack('n', 2),
            pack('n', 0) . pack('n', 1) . pack('n', 3),
        ];
        $featureList = pack('n', count($featureTags));
        $featureOffsets = [];
        $featureOffset = 2 + (count($featureTags) * 6);

        foreach ($featureTables as $table) {
            $featureOffsets[] = $featureOffset;
            $featureOffset += strlen($table);
        }

        foreach ($featureTags as $index => $tag) {
            $featureList .= $tag . pack('n', $featureOffsets[$index]);
        }

        foreach ($featureTables as $table) {
            $featureList .= $table;
        }

        $lookupList = self::buildLookupList([
            self::buildSingleAdjustmentPositioningLookup([
                5 => -20,
                7 => -10,
            ]),
            self::buildPairAdjustmentLookup([
                5 => [7 => -40],
                7 => [6 => -30],
            ]),
            self::buildMarkToBasePositioningLookup(
                marks: [
                    9 => ['class' => 0, 'x' => 50, 'y' => 50],
                    10 => ['class' => 0, 'x' => 40, 'y' => 40],
                ],
                bases: [
                    4 => [0 => ['x' => 340, 'y' => 580]],
                    5 => [0 => ['x' => 320, 'y' => 560]],
                    7 => [0 => ['x' => 300, 'y' => 540]],
                    6 => [0 => ['x' => 280, 'y' => 520]],
                ],
            ),
            self::buildMarkToMarkPositioningLookup(
                marks: [
                    10 => ['class' => 0, 'x' => 40, 'y' => 40],
                ],
                baseMarks: [
                    9 => [0 => ['x' => 120, 'y' => 220]],
                ],
            ),
        ]);

        return pack('N', 0x00010000)
            . pack('n', 10)
            . pack('n', 10 + strlen($scriptList))
            . pack('n', 10 + strlen($scriptList) + strlen($featureList))
            . $scriptList
            . $featureList
            . $lookupList;
    }

    /**
     * @param list<string> $lookups
     */
    private static function buildLookupList(array $lookups): string
    {
        $lookupList = pack('n', count($lookups));
        $offset = 2 + (count($lookups) * 2);
        $body = '';

        foreach ($lookups as $lookup) {
            $lookupList .= pack('n', $offset);
            $body .= $lookup;
            $offset += strlen($lookup);
        }

        return $lookupList . $body;
    }

    private static function buildSingleSubstitutionLookup(int $sourceGlyphId, int $targetGlyphId): string
    {
        $coverage = pack('n', 1) . pack('n', 1) . pack('n', $sourceGlyphId);
        $subtable = pack('n', 2)
            . pack('n', 8)
            . pack('n', 1)
            . pack('n', $targetGlyphId)
            . $coverage;

        return pack('n', 1)
            . pack('n', 0)
            . pack('n', 1)
            . pack('n', 8)
            . $subtable;
    }

    /**
     * @param list<int> $componentGlyphIds
     */
    private static function buildLigatureSubstitutionLookup(
        int $firstGlyphId,
        array $componentGlyphIds,
        int $ligatureGlyphId,
    ): string {
        $coverage = pack('n', 1) . pack('n', 1) . pack('n', $firstGlyphId);
        $ligature = pack('n', $ligatureGlyphId)
            . pack('n', count($componentGlyphIds) + 1);

        foreach ($componentGlyphIds as $componentGlyphId) {
            $ligature .= pack('n', $componentGlyphId);
        }

        $ligatureSet = pack('n', 1)
            . pack('n', 4)
            . $ligature;

        $subtable = pack('n', 1)
            . pack('n', 8)
            . pack('n', 1)
            . pack('n', 14)
            . $coverage
            . $ligatureSet;

        return pack('n', 4)
            . pack('n', 0)
            . pack('n', 1)
            . pack('n', 8)
            . $subtable;
    }

    /**
     * @param list<int> $coverageGlyphIds
     */
    private static function buildContextSubstitutionLookupFormat3(
        array $coverageGlyphIds,
        int $sequenceIndex,
        int $nestedLookupIndex,
    ): string {
        $coverages = [];
        $coverageBody = '';
        $coverageOffset = 6 + (count($coverageGlyphIds) * 2) + 4;

        foreach ($coverageGlyphIds as $glyphId) {
            $coverage = pack('n', 1)
                . pack('n', 1)
                . pack('n', $glyphId);
            $coverages[] = $coverageOffset;
            $coverageBody .= $coverage;
            $coverageOffset += strlen($coverage);
        }

        $subtable = pack('n', 3)
            . pack('n', count($coverageGlyphIds))
            . pack('n', 1);

        foreach ($coverages as $offset) {
            $subtable .= pack('n', $offset);
        }

        $subtable .= pack('n', $sequenceIndex)
            . pack('n', $nestedLookupIndex)
            . $coverageBody;

        return pack('n', 5)
            . pack('n', 0)
            . pack('n', 1)
            . pack('n', 8)
            . $subtable;
    }

    /**
     * @param array<int, array<int, int>> $pairs
     */
    private static function buildPairAdjustmentLookup(array $pairs): string
    {
        $firstGlyphIds = array_keys($pairs);
        sort($firstGlyphIds);

        $coverage = pack('n', 1) . pack('n', count($firstGlyphIds));

        foreach ($firstGlyphIds as $firstGlyphId) {
            $coverage .= pack('n', $firstGlyphId);
        }

        $pairSets = [];

        foreach ($firstGlyphIds as $firstGlyphId) {
            $pairValues = $pairs[$firstGlyphId];
            ksort($pairValues);
            $pairSet = pack('n', count($pairValues));

            foreach ($pairValues as $secondGlyphId => $xAdvanceAdjustment) {
                $pairSet .= pack('n', $secondGlyphId) . pack('n', $xAdvanceAdjustment & 0xFFFF);
            }

            $pairSets[] = $pairSet;
        }

        $subtable = pack('n', 1)
            . pack('n', 10 + (count($pairSets) * 2))
            . pack('n', 0x0004)
            . pack('n', 0)
            . pack('n', count($pairSets));

        $offset = 10 + (count($pairSets) * 2) + strlen($coverage);

        foreach ($pairSets as $pairSet) {
            $subtable .= pack('n', $offset);
            $offset += strlen($pairSet);
        }

        $subtable .= $coverage;

        foreach ($pairSets as $pairSet) {
            $subtable .= $pairSet;
        }

        return pack('n', 2)
            . pack('n', 0)
            . pack('n', 1)
            . pack('n', 8)
            . $subtable;
    }

    /**
     * @param array<int, int> $adjustments
     */
    private static function buildSingleAdjustmentPositioningLookup(array $adjustments): string
    {
        $glyphIds = array_keys($adjustments);
        sort($glyphIds);

        $coverage = pack('n', 1) . pack('n', count($glyphIds));

        foreach ($glyphIds as $glyphId) {
            $coverage .= pack('n', $glyphId);
        }

        $subtable = pack('n', 2)
            . pack('n', 8 + (count($glyphIds) * 2))
            . pack('n', 0x0004)
            . pack('n', count($glyphIds));

        foreach ($glyphIds as $glyphId) {
            $subtable .= pack('n', $adjustments[$glyphId] & 0xFFFF);
        }

        $subtable .= $coverage;

        return pack('n', 1)
            . pack('n', 0)
            . pack('n', 1)
            . pack('n', 8)
            . $subtable;
    }

    /**
     * @param array<int, array{class: int, x: int, y: int}> $marks
     * @param array<int, array<int, array{x: int, y: int}>> $bases
     */
    private static function buildMarkToBasePositioningLookup(array $marks, array $bases): string
    {
        $markGlyphIds = array_keys($marks);
        $baseGlyphIds = array_keys($bases);
        sort($markGlyphIds);
        sort($baseGlyphIds);

        $markCoverage = pack('n', 1) . pack('n', count($markGlyphIds));

        foreach ($markGlyphIds as $glyphId) {
            $markCoverage .= pack('n', $glyphId);
        }

        $baseCoverage = pack('n', 1) . pack('n', count($baseGlyphIds));

        foreach ($baseGlyphIds as $glyphId) {
            $baseCoverage .= pack('n', $glyphId);
        }

        $classCount = 1;
        $markArray = pack('n', count($markGlyphIds));
        $markAnchors = '';
        $markAnchorOffsets = [];
        $markAnchorOffset = 2 + (count($markGlyphIds) * 4);

        foreach ($markGlyphIds as $glyphId) {
            $markAnchorOffsets[$glyphId] = $markAnchorOffset;
            $markAnchors .= self::buildAnchorTable($marks[$glyphId]['x'], $marks[$glyphId]['y']);
            $markAnchorOffset += 6;
        }

        foreach ($markGlyphIds as $glyphId) {
            $markArray .= pack('n', $marks[$glyphId]['class']) . pack('n', $markAnchorOffsets[$glyphId]);
        }

        $markArray .= $markAnchors;

        $baseArray = pack('n', count($baseGlyphIds));
        $baseAnchors = '';
        $baseAnchorOffset = 2 + (count($baseGlyphIds) * ($classCount * 2));
        $baseAnchorOffsets = [];

        foreach ($baseGlyphIds as $glyphId) {
            $anchor = $bases[$glyphId][0];
            $baseAnchorOffsets[$glyphId] = $baseAnchorOffset;
            $baseAnchors .= self::buildAnchorTable($anchor['x'], $anchor['y']);
            $baseAnchorOffset += 6;
        }

        foreach ($baseGlyphIds as $glyphId) {
            $baseArray .= pack('n', $baseAnchorOffsets[$glyphId]);
        }

        $baseArray .= $baseAnchors;

        $subtable = pack('n', 1)
            . pack('n', 12)
            . pack('n', 12 + strlen($markCoverage))
            . pack('n', $classCount)
            . pack('n', 12 + strlen($markCoverage) + strlen($baseCoverage))
            . pack('n', 12 + strlen($markCoverage) + strlen($baseCoverage) + strlen($markArray))
            . $markCoverage
            . $baseCoverage
            . $markArray
            . $baseArray;

        return pack('n', 4)
            . pack('n', 0)
            . pack('n', 1)
            . pack('n', 8)
            . $subtable;
    }

    private static function buildAnchorTable(int $x, int $y): string
    {
        return pack('n', 1)
            . pack('n', $x & 0xFFFF)
            . pack('n', $y & 0xFFFF);
    }

    /**
     * @param array<int, array{class: int, x: int, y: int}> $marks
     * @param array<int, array<int, array{x: int, y: int}>> $baseMarks
     */
    private static function buildMarkToMarkPositioningLookup(array $marks, array $baseMarks): string
    {
        $mark1GlyphIds = array_keys($marks);
        $mark2GlyphIds = array_keys($baseMarks);
        sort($mark1GlyphIds);
        sort($mark2GlyphIds);

        $mark1Coverage = pack('n', 1) . pack('n', count($mark1GlyphIds));
        foreach ($mark1GlyphIds as $glyphId) {
            $mark1Coverage .= pack('n', $glyphId);
        }

        $mark2Coverage = pack('n', 1) . pack('n', count($mark2GlyphIds));
        foreach ($mark2GlyphIds as $glyphId) {
            $mark2Coverage .= pack('n', $glyphId);
        }

        $classCount = 1;
        $mark1Array = pack('n', count($mark1GlyphIds));
        $mark1Anchors = '';
        $mark1AnchorOffsets = [];
        $mark1AnchorOffset = 2 + (count($mark1GlyphIds) * 4);

        foreach ($mark1GlyphIds as $glyphId) {
            $mark1AnchorOffsets[$glyphId] = $mark1AnchorOffset;
            $mark1Anchors .= self::buildAnchorTable($marks[$glyphId]['x'], $marks[$glyphId]['y']);
            $mark1AnchorOffset += 6;
        }

        foreach ($mark1GlyphIds as $glyphId) {
            $mark1Array .= pack('n', $marks[$glyphId]['class']) . pack('n', $mark1AnchorOffsets[$glyphId]);
        }

        $mark1Array .= $mark1Anchors;

        $mark2Array = pack('n', count($mark2GlyphIds));
        $mark2Anchors = '';
        $mark2AnchorOffset = 2 + (count($mark2GlyphIds) * ($classCount * 2));
        $mark2AnchorOffsets = [];

        foreach ($mark2GlyphIds as $glyphId) {
            $anchor = $baseMarks[$glyphId][0];
            $mark2AnchorOffsets[$glyphId] = $mark2AnchorOffset;
            $mark2Anchors .= self::buildAnchorTable($anchor['x'], $anchor['y']);
            $mark2AnchorOffset += 6;
        }

        foreach ($mark2GlyphIds as $glyphId) {
            $mark2Array .= pack('n', $mark2AnchorOffsets[$glyphId]);
        }

        $mark2Array .= $mark2Anchors;

        $subtable = pack('n', 1)
            . pack('n', 12)
            . pack('n', 12 + strlen($mark1Coverage))
            . pack('n', $classCount)
            . pack('n', 12 + strlen($mark1Coverage) + strlen($mark2Coverage))
            . pack('n', 12 + strlen($mark1Coverage) + strlen($mark2Coverage) + strlen($mark1Array))
            . $mark1Coverage
            . $mark2Coverage
            . $mark1Array
            . $mark2Array;

        return pack('n', 6)
            . pack('n', 0)
            . pack('n', 1)
            . pack('n', 8)
            . $subtable;
    }

    private static function buildMinimalCffTable(): string
    {
        $name = 'TestCff-Regular';
        $header = "\x01\x00\x04\x01";
        $nameIndex = self::buildCffIndex([$name]);
        $stringIndex = pack('n', 0);
        $globalSubrIndex = pack('n', 0);
        $charset = "\x00" . pack('n', 391);
        $charStringsIndex = self::buildCffIndex(["\x0E", "\x0E"]);

        $topDictLength = 26;
        $topDictIndexLength = 2 + 1 + 2 + $topDictLength;
        $charsetOffset = strlen($header) + strlen($nameIndex) + $topDictIndexLength + strlen($stringIndex) + strlen($globalSubrIndex);
        $charStringsOffset = $charsetOffset + strlen($charset);

        $topDict = self::cffInt16(-50)
            . self::cffInt16(-200)
            . self::cffInt16(950)
            . self::cffInt16(800)
            . "\x05"
            . self::cffInt16(-12)
            . "\x0C\x02"
            . self::cffInt16($charsetOffset)
            . "\x0F"
            . self::cffInt16($charStringsOffset)
            . "\x11";

        return $header
            . $nameIndex
            . self::buildCffIndex([$topDict])
            . $stringIndex
            . $globalSubrIndex
            . $charset
            . $charStringsIndex;
    }

    /**
     * @param list<string> $items
     */
    private static function buildCffIndex(array $items): string
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

        return pack('n', count($items))
            . "\x01"
            . implode('', array_map(static function (int $offset): string {
                if ($offset < 0 || $offset > 255) {
                    throw new \InvalidArgumentException('CFF index offset must fit into a single byte.');
                }

                return chr($offset);
            }, $offsets))
            . $data;
    }

    private static function cffInt16(int $value): string
    {
        return "\x1C" . pack('n', $value & 0xFFFF);
    }
}
