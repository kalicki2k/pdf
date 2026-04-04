<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Font;

use Kalle\Pdf\Font\CidFont;
use Kalle\Pdf\Font\ToUnicodeCMap;
use Kalle\Pdf\Font\UnicodeFont;
use Kalle\Pdf\Font\UnicodeGlyphMap;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class UnicodeFontTest extends TestCase
{
    #[Test]
    public function it_returns_the_base_font_name(): void
    {
        $glyphMap = new UnicodeGlyphMap();
        $font = new UnicodeFont(12, new CidFont(13, 'NotoSansCJKsc-Regular'), new ToUnicodeCMap(14, $glyphMap), $glyphMap);

        self::assertSame('NotoSansCJKsc-Regular', $font->getBaseFont());
    }

    #[Test]
    public function it_encodes_text_using_the_internal_unicode_glyph_map(): void
    {
        $glyphMap = new UnicodeGlyphMap();
        $font = new UnicodeFont(12, new CidFont(13, 'NotoSansCJKsc-Regular'), new ToUnicodeCMap(14, $glyphMap), $glyphMap);

        self::assertSame('<0001>', $font->encodeText('漢'));
        self::assertSame('<00020001>', $font->encodeText('字漢'));
        self::assertSame(
            [
                '漢' => '0001',
                '字' => '0002',
            ],
            $font->glyphMap->getCharacterMap(),
        );
    }

    #[Test]
    public function it_renders_a_type0_font_dictionary(): void
    {
        $glyphMap = new UnicodeGlyphMap();
        $font = new UnicodeFont(12, new CidFont(13, 'NotoSansCJKsc-Regular'), new ToUnicodeCMap(14, $glyphMap), $glyphMap);

        self::assertSame(
            "12 0 obj\n"
            . "<< /Type /Font /Subtype /Type0 /BaseFont /NotoSansCJKsc-Regular /Encoding /Identity-H /DescendantFonts [13 0 R] /ToUnicode 14 0 R >>\n"
            . "endobj\n",
            $font->render(),
        );
    }
}
