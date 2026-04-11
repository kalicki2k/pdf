<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Font;

use Kalle\Pdf\Font\EmbeddedFontSource;
use Kalle\Pdf\Font\FontBoundingBox;
use Kalle\Pdf\Font\OpenTypeFontParser;
use Kalle\Pdf\Font\OpenTypeOutlineType;
use PHPUnit\Framework\TestCase;

final class OpenTypeFontParserTest extends TestCase
{
    public function testItParsesEmbeddedTrueTypeMetadata(): void
    {
        $parser = new OpenTypeFontParser(EmbeddedFontSource::fromString(TrueTypeFontFixture::minimalTrueTypeFontBytes()));
        $metadata = $parser->metadata();

        self::assertSame(OpenTypeOutlineType::TRUE_TYPE, $metadata->outlineType);
        self::assertSame('TestFont-Regular', $metadata->postScriptName);
        self::assertSame(1000, $metadata->unitsPerEm);
        self::assertSame(800, $metadata->ascent);
        self::assertSame(-200, $metadata->descent);
        self::assertSame(800, $metadata->capHeight);
        self::assertSame(0.0, $metadata->italicAngle);
        self::assertEquals(new FontBoundingBox(-50, -200, 950, 800), $metadata->fontBoundingBox);
        self::assertSame(2, $metadata->glyphCount);
    }

    public function testItResolvesGlyphIdsAndAdvanceWidthsFromTheFont(): void
    {
        $parser = new OpenTypeFontParser(TrueTypeFontFixture::minimalTrueTypeFontBytes());

        self::assertSame(1, $parser->getGlyphIdForCharacter('A'));
        self::assertSame(0, $parser->getGlyphIdForCharacter('B'));
        self::assertSame(600, $parser->getAdvanceWidthForGlyphId(1));
        self::assertSame(500, $parser->getAdvanceWidthForGlyphId(0));
    }

    public function testItResolvesUnicodeGlyphIdsFromFormat12Cmap(): void
    {
        $parser = new OpenTypeFontParser(TrueTypeFontFixture::minimalUnicodeTrueTypeFontBytes());

        self::assertSame(2, $parser->getGlyphIdForCharacter('Ж'));
        self::assertSame(3, $parser->getGlyphIdForCharacter('中'));
        self::assertSame(4, $parser->getGlyphIdForCharacter('😀'));
        self::assertSame(700, $parser->getAdvanceWidthForGlyphId(2));
        self::assertSame(800, $parser->getAdvanceWidthForGlyphId(3));
        self::assertSame(900, $parser->getAdvanceWidthForGlyphId(4));
    }

    public function testItResolvesArabicGsubSingleSubstitutions(): void
    {
        $parser = new OpenTypeFontParser(TrueTypeFontFixture::minimalArabicGsubTrueTypeFontBytes());

        self::assertTrue($parser->hasGsubFeature('isol'));
        self::assertTrue($parser->hasGsubFeature('fina'));
        self::assertTrue($parser->hasGsubFeature('init'));
        self::assertTrue($parser->hasGsubFeature('medi'));
        self::assertTrue($parser->hasGsubFeature('rlig'));
        self::assertSame(4, $parser->substituteGlyphIdWithFeature('isol', 2));
        self::assertSame(5, $parser->substituteGlyphIdWithFeature('fina', 2));
        self::assertSame(6, $parser->substituteGlyphIdWithFeature('init', 2));
        self::assertSame(7, $parser->substituteGlyphIdWithFeature('medi', 2));
        self::assertSame(
            ['substitutedGlyphId' => 8, 'consumedGlyphCount' => 2],
            $parser->substituteGlyphSequenceWithFeature('rlig', [3, 1]),
        );
        self::assertTrue($parser->hasGposFeature('kern'));
        self::assertSame(-20, $parser->gposSingleAdjustmentValueWithFeature('kern', 5));
        self::assertSame(-10, $parser->gposSingleAdjustmentValueWithFeature('kern', 7));
        self::assertNull($parser->gposSingleAdjustmentValueWithFeature('kern', 6));
        self::assertSame(-40, $parser->gposPairAdjustmentValueWithFeature('kern', 5, 7));
        self::assertSame(-30, $parser->gposPairAdjustmentValueWithFeature('kern', 7, 6));
        self::assertNull($parser->gposPairAdjustmentValueWithFeature('kern', 5, 6));
        self::assertTrue($parser->hasGposFeature('mark'));
        self::assertTrue($parser->hasGposFeature('mkmk'));
        self::assertSame(
            ['xOffset' => 290, 'yOffset' => 530],
            $parser->gposMarkToBasePlacementWithFeature('mark', 4, 9),
        );
        self::assertSame(
            ['xOffset' => 270, 'yOffset' => 510],
            $parser->gposMarkToBasePlacementWithFeature('mark', 5, 9),
        );
        self::assertSame(
            ['xOffset' => 80, 'yOffset' => 180],
            $parser->gposMarkToMarkPlacementWithFeature('mkmk', 9, 10),
        );
    }

    public function testItResolvesGeneralLigaGsubLigatures(): void
    {
        $parser = new OpenTypeFontParser(TrueTypeFontFixture::minimalLatinLigaTrueTypeFontBytes());

        self::assertTrue($parser->hasGsubFeature('liga'));
        self::assertSame(1, $parser->getGlyphIdForCharacter('f'));
        self::assertSame(2, $parser->getGlyphIdForCharacter('i'));
        self::assertSame(
            ['substitutedGlyphId' => 3, 'consumedGlyphCount' => 2],
            $parser->substituteGlyphSequenceWithFeature('liga', [1, 2]),
        );
    }

    public function testItResolvesGeneralCaltContextualSubstitutions(): void
    {
        $parser = new OpenTypeFontParser(TrueTypeFontFixture::minimalLatinContextualTrueTypeFontBytes());

        self::assertTrue($parser->hasGsubFeature('calt'));
        self::assertSame(1, $parser->getGlyphIdForCharacter('f'));
        self::assertSame(2, $parser->getGlyphIdForCharacter('i'));
        self::assertSame(
            ['substitutedGlyphId' => 3, 'matchedGlyphCount' => 2],
            $parser->substituteContextualGlyphSequenceWithFeature('calt', [1, 2]),
        );
    }

    public function testItResolvesDevanagariIndicGsubSingleSubstitutions(): void
    {
        $parser = new OpenTypeFontParser(TrueTypeFontFixture::minimalDevanagariGsubTrueTypeFontBytes());

        self::assertTrue($parser->hasGsubFeature('half'));
        self::assertTrue($parser->hasGsubFeature('pref'));
        self::assertTrue($parser->hasGsubFeature('rphf'));
        self::assertTrue($parser->hasGposFeature('mark'));
        self::assertTrue($parser->hasGposFeature('mkmk'));
        self::assertSame(6, $parser->substituteGlyphIdWithFeature('half', 1));
        self::assertSame(9, $parser->substituteGlyphIdWithFeature('half', 3));
        self::assertSame(7, $parser->substituteGlyphIdWithFeature('pref', 2));
        self::assertSame(8, $parser->substituteGlyphIdWithFeature('rphf', 4));
        self::assertSame(
            ['xOffset' => 230, 'yOffset' => 580],
            $parser->gposMarkToBasePlacementWithFeature('mark', 3, 10),
        );
        self::assertSame(
            ['xOffset' => 70, 'yOffset' => 110],
            $parser->gposMarkToMarkPlacementWithFeature('mkmk', 10, 11),
        );
    }

    public function testItResolvesBengaliIndicGsubSingleSubstitutions(): void
    {
        $parser = new OpenTypeFontParser(TrueTypeFontFixture::minimalBengaliGsubTrueTypeFontBytes());

        self::assertTrue($parser->hasGsubFeature('half'));
        self::assertTrue($parser->hasGsubFeature('pref'));
        self::assertTrue($parser->hasGsubFeature('rphf'));
        self::assertTrue($parser->hasGposFeature('mark'));
        self::assertTrue($parser->hasGposFeature('mkmk'));
        self::assertSame(6, $parser->substituteGlyphIdWithFeature('half', 1));
        self::assertSame(9, $parser->substituteGlyphIdWithFeature('half', 3));
        self::assertSame(7, $parser->substituteGlyphIdWithFeature('pref', 2));
        self::assertSame(8, $parser->substituteGlyphIdWithFeature('rphf', 4));
        self::assertSame(
            ['xOffset' => 230, 'yOffset' => 580],
            $parser->gposMarkToBasePlacementWithFeature('mark', 3, 10),
        );
        self::assertSame(
            ['xOffset' => 70, 'yOffset' => 110],
            $parser->gposMarkToMarkPlacementWithFeature('mkmk', 10, 11),
        );
    }

    public function testItResolvesGujaratiIndicGsubSingleSubstitutions(): void
    {
        $parser = new OpenTypeFontParser(TrueTypeFontFixture::minimalGujaratiGsubTrueTypeFontBytes());

        self::assertTrue($parser->hasGsubFeature('half'));
        self::assertTrue($parser->hasGsubFeature('pref'));
        self::assertTrue($parser->hasGsubFeature('rphf'));
        self::assertTrue($parser->hasGposFeature('mark'));
        self::assertTrue($parser->hasGposFeature('mkmk'));
        self::assertSame(6, $parser->substituteGlyphIdWithFeature('half', 1));
        self::assertSame(9, $parser->substituteGlyphIdWithFeature('half', 3));
        self::assertSame(7, $parser->substituteGlyphIdWithFeature('pref', 2));
        self::assertSame(8, $parser->substituteGlyphIdWithFeature('rphf', 4));
        self::assertSame(
            ['xOffset' => 230, 'yOffset' => 580],
            $parser->gposMarkToBasePlacementWithFeature('mark', 3, 10),
        );
        self::assertSame(
            ['xOffset' => 70, 'yOffset' => 110],
            $parser->gposMarkToMarkPlacementWithFeature('mkmk', 10, 11),
        );
    }
}
