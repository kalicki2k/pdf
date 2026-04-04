<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Core;

use Kalle\Pdf\Core\OpenTypeFontParser;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class OpenTypeFontParserTest extends TestCase
{
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
}
