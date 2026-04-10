<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Internal\Font;

use Kalle\Pdf\Font\CidFont;
use Kalle\Pdf\Font\FontDescriptor;
use Kalle\Pdf\Font\FontFileStream;
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

        self::assertSame(12, $font->getId());
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
    public function it_creates_a_default_glyph_map_when_none_is_provided(): void
    {
        $font = new UnicodeFont(12, new CidFont(13, 'NotoSansCJKsc-Regular'), new ToUnicodeCMap(14, new UnicodeGlyphMap()));

        self::assertInstanceOf(UnicodeGlyphMap::class, $font->glyphMap);
        self::assertSame('<0001>', $font->encodeText('漢'));
    }

    #[Test]
    public function it_reports_utf_8_support_and_exposes_the_code_point_map(): void
    {
        $glyphMap = new UnicodeGlyphMap();
        $font = new UnicodeFont(12, new CidFont(13, 'NotoSansCJKsc-Regular'), new ToUnicodeCMap(14, $glyphMap), $glyphMap);
        $font->encodeText('Ä字');

        self::assertTrue($font->supportsText('Ä字'));
        self::assertFalse($font->supportsText("\xC3\x28"));
        self::assertSame([
            '0001' => '00C4',
            '0002' => '5B57',
        ], $font->getCodePointMap());
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
            \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($font),
        );
    }

    #[Test]
    public function it_falls_back_to_character_count_when_no_font_metrics_are_embedded(): void
    {
        $glyphMap = new UnicodeGlyphMap();
        $font = new UnicodeFont(12, new CidFont(13, 'NotoSansCJKsc-Regular'), new ToUnicodeCMap(14, $glyphMap), $glyphMap);

        self::assertSame(0.0, $font->measureTextWidth('', 10));
        self::assertSame(20.0, $font->measureTextWidth('漢字', 10));
    }

    #[Test]
    public function it_measures_text_using_embedded_font_metrics_when_available(): void
    {
        $glyphMap = new UnicodeGlyphMap();
        $font = new UnicodeFont(
            12,
            new CidFont(
                13,
                'NotoSans-Regular',
                fontDescriptor: new FontDescriptor(
                    15,
                    'NotoSans-Regular',
                    FontFileStream::fromPath(16, 'assets/fonts/NotoSans-Regular.ttf'),
                ),
            ),
            new ToUnicodeCMap(14, $glyphMap),
            $glyphMap,
        );

        self::assertGreaterThan(0.0, $font->measureTextWidth('Hello', 10));
        self::assertNotSame(50.0, $font->measureTextWidth('Hello', 10));
    }
}
