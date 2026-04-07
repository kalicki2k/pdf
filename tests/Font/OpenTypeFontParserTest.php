<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Font;

use Kalle\Pdf\Font\OpenTypeFontParser;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class OpenTypeFontParserTest extends TestCase
{
    #[Test]
    public function it_rejects_truncated_table_directory_entries_when_reading_32_bit_values(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unable to read 32-bit unsigned integer from font data.');

        new OpenTypeFontParser("\x00\x01\x00\x00\x00\x01\x00\x00\x00\x00\x00\x00");
    }

    #[Test]
    public function it_rejects_advance_width_lookups_when_horizontal_metrics_tables_are_missing(): void
    {
        $parser = new OpenTypeFontParser("\x00\x00\x00\x00\x00\x00");

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Font is missing horizontal metrics tables.');

        $parser->getAdvanceWidthForGlyphId(1);
    }

    #[Test]
    public function it_rejects_units_per_em_lookups_when_the_head_table_is_missing(): void
    {
        $parser = new OpenTypeFontParser("\x00\x00\x00\x00\x00\x00");

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Font is missing head table.');

        $parser->getUnitsPerEm();
    }

    #[Test]
    public function it_rejects_truncated_head_tables_when_reading_16_bit_values(): void
    {
        $parser = new OpenTypeFontParser($this->createFontDataWithTruncatedHeadTable());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unable to read 16-bit unsigned integer from font data.');

        $parser->getUnitsPerEm();
    }

    #[Test]
    public function it_uses_the_last_advance_width_when_the_glyph_id_exceeds_the_hmetric_count(): void
    {
        $parser = new OpenTypeFontParser($this->createFontDataWithSingleHorizontalMetric(777));

        self::assertSame(777, $parser->getAdvanceWidthForGlyphId(5));
    }

    #[Test]
    public function it_rejects_glyph_id_lookups_when_the_font_has_no_cmap_table(): void
    {
        $parser = new OpenTypeFontParser("\x00\x01\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00");

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Font does not contain a cmap table.');

        $parser->getGlyphIdForCodePoint(0x6F22);
    }

    #[Test]
    public function it_rejects_glyph_id_lookups_when_no_supported_unicode_cmap_subtable_exists(): void
    {
        $parser = new OpenTypeFontParser($this->createFontDataWithUnsupportedCmapSubtable());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('No supported Unicode cmap subtable found.');

        $parser->getGlyphIdForCodePoint(0x6F22);
    }

    #[Test]
    public function it_returns_zero_for_format_12_cmaps_when_no_group_matches_the_code_point(): void
    {
        $parser = new OpenTypeFontParser($this->createFontDataWithFormat12Group(0x4E00, 0x4E10, 7));

        self::assertSame(0, $parser->getGlyphIdForCodePoint(0x6F22));
    }

    #[Test]
    public function it_returns_zero_for_format_4_cmaps_when_the_glyph_array_entry_is_zero(): void
    {
        $parser = new OpenTypeFontParser($this->createFontDataWithFormat4GlyphArrayEntry(0));

        self::assertSame(0, $parser->getGlyphIdForCodePoint(0x0041));
    }

    #[Test]
    public function it_uses_the_format_4_glyph_array_entry_when_present(): void
    {
        $parser = new OpenTypeFontParser($this->createFontDataWithFormat4GlyphArrayEntry(7));

        self::assertSame(7, $parser->getGlyphIdForCodePoint(0x0041));
    }

    #[Test]
    public function it_returns_zero_for_format_4_cmaps_when_no_segment_matches_the_code_point(): void
    {
        $parser = new OpenTypeFontParser($this->createFontDataWithFormat4GlyphArrayEntry(7));

        self::assertSame(0, $parser->getGlyphIdForCodePoint(0x0042));
    }

    #[Test]
    public function it_detects_cff_outlines_for_the_static_cjk_font(): void
    {
        $parser = new OpenTypeFontParser(file_get_contents('assets/fonts/NotoSansCJKsc-Regular.otf'));

        self::assertTrue($parser->hasCffOutlines());
    }

    #[Test]
    public function it_returns_a_non_zero_glyph_id_for_supported_unicode_characters(): void
    {
        $parser = new OpenTypeFontParser(file_get_contents('assets/fonts/NotoSansCJKsc-Regular.otf'));

        self::assertGreaterThan(0, $parser->getGlyphIdForCharacter('漢'));
        self::assertGreaterThan(0, $parser->getGlyphIdForCharacter('你'));
    }

    #[Test]
    public function it_returns_a_non_zero_advance_width_for_supported_glyphs(): void
    {
        $parser = new OpenTypeFontParser(file_get_contents('assets/fonts/NotoSansCJKsc-Regular.otf'));
        $glyphId = $parser->getGlyphIdForCharacter('漢');

        self::assertGreaterThan(0, $parser->getAdvanceWidthForGlyphId($glyphId));
    }

    private function createFontDataWithSingleHorizontalMetric(int $advanceWidth): string
    {
        $numTables = 3;
        $header = "\x00\x01\x00\x00" . pack('n', $numTables) . "\x00\x00\x00\x00\x00\x00";
        $tableDirectoryOffset = 12;
        $tableDataOffset = $tableDirectoryOffset + ($numTables * 16);

        $hheaOffset = $tableDataOffset;
        $hheaData = str_repeat("\x00", 34) . pack('n', 1);

        $hmtxOffset = $hheaOffset + strlen($hheaData);
        $hmtxData = pack('n', $advanceWidth) . pack('n', 0);

        $maxpOffset = $hmtxOffset + strlen($hmtxData);
        $maxpData = str_repeat("\x00", 6);

        $directory = ''
            . 'hhea' . pack('N', 0) . pack('N', $hheaOffset) . pack('N', strlen($hheaData))
            . 'hmtx' . pack('N', 0) . pack('N', $hmtxOffset) . pack('N', strlen($hmtxData))
            . 'maxp' . pack('N', 0) . pack('N', $maxpOffset) . pack('N', strlen($maxpData));

        return $header . $directory . $hheaData . $hmtxData . $maxpData;
    }

    private function createFontDataWithTruncatedHeadTable(): string
    {
        $numTables = 1;
        $header = "\x00\x01\x00\x00" . pack('n', $numTables) . "\x00\x00\x00\x00\x00\x00";
        $tableDirectoryOffset = 12;
        $tableDataOffset = $tableDirectoryOffset + ($numTables * 16);
        $headData = str_repeat("\x00", 18);

        $directory = 'head' . pack('N', 0) . pack('N', $tableDataOffset) . pack('N', strlen($headData));

        return $header . $directory . $headData;
    }

    private function createFontDataWithUnsupportedCmapSubtable(): string
    {
        $numTables = 1;
        $header = "\x00\x01\x00\x00" . pack('n', $numTables) . "\x00\x00\x00\x00\x00\x00";
        $tableDirectoryOffset = 12;
        $tableDataOffset = $tableDirectoryOffset + ($numTables * 16);

        $cmapOffset = $tableDataOffset;
        $subtableOffset = 12;
        $cmapData = ''
            . pack('n', 0)
            . pack('n', 1)
            . pack('n', 1)
            . pack('n', 0)
            . pack('N', $subtableOffset)
            . pack('n', 0)
            . pack('n', 6)
            . pack('n', 0);

        $directory = 'cmap' . pack('N', 0) . pack('N', $cmapOffset) . pack('N', strlen($cmapData));

        return $header . $directory . $cmapData;
    }

    private function createFontDataWithFormat12Group(int $startCharCode, int $endCharCode, int $startGlyphId): string
    {
        $numTables = 1;
        $header = "\x00\x01\x00\x00" . pack('n', $numTables) . "\x00\x00\x00\x00\x00\x00";
        $tableDirectoryOffset = 12;
        $tableDataOffset = $tableDirectoryOffset + ($numTables * 16);

        $cmapOffset = $tableDataOffset;
        $subtableOffset = 12;
        $subtableLength = 28;
        $cmapData = ''
            . pack('n', 0)
            . pack('n', 1)
            . pack('n', 3)
            . pack('n', 10)
            . pack('N', $subtableOffset)
            . pack('n', 12)
            . pack('n', 0)
            . pack('N', $subtableLength)
            . pack('N', 0)
            . pack('N', 1)
            . pack('N', $startCharCode)
            . pack('N', $endCharCode)
            . pack('N', $startGlyphId);

        $directory = 'cmap' . pack('N', 0) . pack('N', $cmapOffset) . pack('N', strlen($cmapData));

        return $header . $directory . $cmapData;
    }

    private function createFontDataWithFormat4GlyphArrayEntry(int $glyphId): string
    {
        $numTables = 1;
        $header = "\x00\x01\x00\x00" . pack('n', $numTables) . "\x00\x00\x00\x00\x00\x00";
        $tableDirectoryOffset = 12;
        $tableDataOffset = $tableDirectoryOffset + ($numTables * 16);

        $cmapOffset = $tableDataOffset;
        $subtableOffset = 12;
        $segCount = 2;
        $segCountX2 = $segCount * 2;
        $glyphIndexArray = pack('n', $glyphId);
        $subtableLength = 14 + 8 * $segCount + strlen($glyphIndexArray);

        $cmapData = ''
            . pack('n', 0)
            . pack('n', 1)
            . pack('n', 3)
            . pack('n', 1)
            . pack('N', $subtableOffset)
            . pack('n', 4)
            . pack('n', $subtableLength)
            . pack('n', 0)
            . pack('n', $segCountX2)
            . pack('n', 0)
            . pack('n', 0)
            . pack('n', 0)
            . pack('n', 0x0041)
            . pack('n', 0xFFFF)
            . pack('n', 0)
            . pack('n', 0x0041)
            . pack('n', 0xFFFF)
            . pack('n', 0)
            . pack('n', 1)
            . pack('n', 4)
            . pack('n', 0)
            . $glyphIndexArray;

        $directory = 'cmap' . pack('N', 0) . pack('N', $cmapOffset) . pack('N', strlen($cmapData));

        return $header . $directory . $cmapData;
    }
}
