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
        return self::minimalSfntFontBytes('OTTO');
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

    private static function buildHmtxTable(): string
    {
        return pack('n', 500) . pack('n', 0)
            . pack('n', 600) . pack('n', 0);
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
}
