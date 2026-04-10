<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Internal\Font;

use Kalle\Pdf\Internal\Font\CidFont;
use Kalle\Pdf\Internal\Font\CidToGidMap;
use Kalle\Pdf\Internal\Font\FontDescriptor;
use Kalle\Pdf\Internal\Font\FontFileStream;
use Kalle\Pdf\Internal\Font\OpenTypeFontParser;
use Kalle\Pdf\Internal\Font\UnicodeGlyphMap;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CidFontTest extends TestCase
{
    #[Test]
    public function it_returns_the_base_font_name(): void
    {
        $font = new CidFont(13, 'NotoSansCJKsc-Regular');

        self::assertSame('NotoSansCJKsc-Regular', $font->getBaseFont());
    }

    #[Test]
    public function it_renders_a_cid_font_dictionary(): void
    {
        $font = new CidFont(13, 'NotoSansCJKsc-Regular');

        self::assertSame(
            "13 0 obj\n"
            . "<< /Type /Font /Subtype /CIDFontType2 /BaseFont /NotoSansCJKsc-Regular /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 1000 >>\n"
            . "endobj\n",
            $font->render(),
        );
    }

    #[Test]
    public function it_renders_a_cid_font_dictionary_with_a_font_descriptor_reference(): void
    {
        $fontDescriptor = new FontDescriptor(14, 'NotoSansCJKsc-Regular', new FontFileStream(15, 'FONTDATA'));
        $font = new CidFont(13, 'NotoSansCJKsc-Regular', fontDescriptor: $fontDescriptor);

        self::assertSame(
            "13 0 obj\n"
            . "<< /Type /Font /Subtype /CIDFontType2 /BaseFont /NotoSansCJKsc-Regular /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /FontDescriptor 14 0 R /DW 1000 >>\n"
            . "endobj\n",
            $font->render(),
        );
    }

    #[Test]
    public function it_renders_a_cid_font_dictionary_with_a_cid_to_gid_map_reference(): void
    {
        $glyphMap = new UnicodeGlyphMap();
        $glyphMap->encodeText('漢');
        $cidToGidMap = new CidToGidMap(16, $glyphMap, new OpenTypeFontParser(file_get_contents('assets/fonts/NotoSansCJKsc-Regular.otf')));
        $font = new CidFont(13, 'NotoSansCJKsc-Regular', 'CIDFontType0', cidToGidMap: $cidToGidMap);

        self::assertStringContainsString('/Subtype /CIDFontType0', $font->render());
        self::assertStringContainsString('/CIDToGIDMap 16 0 R', $font->render());
    }

    #[Test]
    public function it_renders_width_arrays_for_explicit_cid_widths(): void
    {
        $font = new CidFont(13, 'NotoSansCJKsc-Regular', widths: [
            '0001' => 1000,
            '0002' => 980,
        ]);

        self::assertStringContainsString('/DW 1000', $font->render());
        self::assertStringContainsString('/W [1 [1000] 2 [980]]', $font->render());
    }

    #[Test]
    public function it_can_replace_explicit_cid_widths_after_construction(): void
    {
        $font = new CidFont(13, 'NotoSansCJKsc-Regular');
        $font->setWidths([
            '000A' => 700,
            '000B' => 710,
        ]);

        self::assertStringContainsString('/W [10 [700] 11 [710]]', $font->render());
    }
}
