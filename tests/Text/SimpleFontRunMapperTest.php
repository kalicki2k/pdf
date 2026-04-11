<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Text;

use Kalle\Pdf\Font\EmbeddedFontDefinition;
use Kalle\Pdf\Font\EmbeddedFontSource;
use Kalle\Pdf\Font\StandardFont;
use Kalle\Pdf\Font\StandardFontDefinition;
use Kalle\Pdf\Page\EmbeddedGlyph;
use Kalle\Pdf\Page\PageFont;
use Kalle\Pdf\Tests\Font\TrueTypeFontFixture;
use Kalle\Pdf\Text\ShapedGlyph;
use Kalle\Pdf\Text\ShapedTextRun;
use Kalle\Pdf\Text\SimpleFontRunMapper;
use Kalle\Pdf\Text\TextDirection;
use Kalle\Pdf\Text\TextOptions;
use Kalle\Pdf\Text\TextScript;
use PHPUnit\Framework\TestCase;

final class SimpleFontRunMapperTest extends TestCase
{
    public function testItMapsAStandardFontRun(): void
    {
        $mapper = new SimpleFontRunMapper();
        $run = new ShapedTextRun(TextDirection::LTR, TextScript::LATIN, [
            new ShapedGlyph('A', 0),
            new ShapedGlyph('V', 1),
        ]);

        $mapped = $mapper->map(
            $run,
            StandardFontDefinition::from(StandardFont::HELVETICA),
            new TextOptions(fontName: StandardFont::HELVETICA->value),
            1.4,
        );

        self::assertSame('AV', $mapped->text);
        self::assertSame(TextScript::LATIN, $mapped->script);
        self::assertSame('AV', $mapped->encodedText);
        self::assertSame(['A', 'V'], $mapped->glyphNames);
        self::assertSame([], $mapped->textAdjustments);
        self::assertFalse($mapped->useHexString);
        self::assertGreaterThan(0.0, $mapped->width);
    }

    public function testItMapsAnEmbeddedWesternRun(): void
    {
        $mapper = new SimpleFontRunMapper();
        $run = new ShapedTextRun(TextDirection::LTR, TextScript::LATIN, [
            new ShapedGlyph('A', 0),
        ]);
        $font = EmbeddedFontDefinition::fromSource(
            EmbeddedFontSource::fromString(TrueTypeFontFixture::minimalTrueTypeFontBytes()),
        );

        $mapped = $mapper->map($run, $font, new TextOptions(embeddedFont: $font->source), 1.4);

        self::assertSame('A', $mapped->text);
        self::assertSame(TextScript::LATIN, $mapped->script);
        self::assertSame('A', $mapped->encodedText);
        self::assertSame([], $mapped->glyphNames);
        self::assertSame([], $mapped->textAdjustments);
        self::assertFalse($mapped->useHexString);
        self::assertGreaterThan(0.0, $mapped->width);
    }

    public function testItCarriesShaperAssignedGlyphNamesIntoMappedRuns(): void
    {
        $mapper = new SimpleFontRunMapper();
        $run = new ShapedTextRun(TextDirection::LTR, TextScript::LATIN, [
            new ShapedGlyph('A', 0, glyphName: 'A.alt'),
            new ShapedGlyph('V', 1, glyphName: 'V.alt'),
        ]);

        $mapped = $mapper->map(
            $run,
            StandardFontDefinition::from(StandardFont::HELVETICA),
            new TextOptions(fontName: StandardFont::HELVETICA->value),
            1.4,
        );

        self::assertSame(['A.alt', 'V.alt'], $mapped->glyphNames);
    }

    public function testItCarriesEmbeddedGposAdjustmentsIntoMappedRuns(): void
    {
        $mapper = new SimpleFontRunMapper();
        $font = EmbeddedFontDefinition::fromSource(
            EmbeddedFontSource::fromString(TrueTypeFontFixture::minimalArabicGsubTrueTypeFontBytes()),
        );
        $run = new ShapedTextRun(TextDirection::RTL, TextScript::ARABIC, [
            new ShapedGlyph('ب', 0, glyphId: 5, unicodeCodePoint: 0x0628, unicodeText: 'ب'),
            new ShapedGlyph('ب', 1, glyphId: 7, unicodeCodePoint: 0x0628, unicodeText: 'ب'),
            new ShapedGlyph('ب', 2, glyphId: 6, unicodeCodePoint: 0x0628, unicodeText: 'ب'),
        ]);

        $mapped = $mapper->map(
            $run,
            $font,
            new TextOptions(embeddedFont: $font->source),
            1.4,
            useHexString: true,
        );

        self::assertSame([60, 40], $mapped->textAdjustments);
        self::assertTrue($mapped->useHexString);
    }

    public function testItBuildsPositionedFragmentsForEmbeddedGlyphOffsets(): void
    {
        $mapper = new SimpleFontRunMapper();
        $font = EmbeddedFontDefinition::fromSource(
            EmbeddedFontSource::fromString(TrueTypeFontFixture::minimalArabicGsubTrueTypeFontBytes()),
        );
        $pageFont = PageFont::embeddedUnicode($font, [
            new EmbeddedGlyph(5, 0x0628, 'ب'),
            new EmbeddedGlyph(7, 0x0628, 'ب'),
        ]);
        $run = new ShapedTextRun(TextDirection::RTL, TextScript::ARABIC, [
            new ShapedGlyph('ب', 0, xOffset: 0.0, yOffset: 0.0, glyphId: 5, unicodeCodePoint: 0x0628, unicodeText: 'ب'),
            new ShapedGlyph('ب', 1, xAdvance: -610.0, xOffset: 120.0, yOffset: 200.0, glyphId: 7, unicodeCodePoint: 0x0628, unicodeText: 'ب'),
        ]);

        $mapped = $mapper->map(
            $run,
            $font,
            new TextOptions(embeddedFont: $font->source, fontSize: 10),
            1.4,
            $pageFont,
            true,
        );

        self::assertCount(2, $mapped->positionedFragments);
        self::assertSame("\x00\x01", $mapped->positionedFragments[0]->encodedText);
        self::assertSame(0.0, $mapped->positionedFragments[0]->xOffset);
        self::assertSame(0.0, $mapped->positionedFragments[0]->yOffset);
        self::assertSame("\x00\x02", $mapped->positionedFragments[1]->encodedText);
        self::assertEquals(7.4, $mapped->positionedFragments[1]->xOffset);
        self::assertEquals(2.0, $mapped->positionedFragments[1]->yOffset);
    }

    public function testItBuildsPositionedFragmentsForArabicMarkPlacement(): void
    {
        $mapper = new SimpleFontRunMapper();
        $font = EmbeddedFontDefinition::fromSource(
            EmbeddedFontSource::fromString(TrueTypeFontFixture::minimalArabicGsubTrueTypeFontBytes()),
        );
        $pageFont = PageFont::embeddedUnicode($font, [
            new EmbeddedGlyph(9, 0x064E, 'َ'),
            new EmbeddedGlyph(4, 0x0628, 'ب'),
        ]);
        $run = new ShapedTextRun(TextDirection::RTL, TextScript::ARABIC, [
            new ShapedGlyph('َ', 1, xAdvance: -200.0, xOffset: 270.0, yOffset: 510.0, glyphName: 'gpos.mark', glyphId: 9, unicodeCodePoint: 0x064E, unicodeText: 'َ'),
            new ShapedGlyph('ب', 0, glyphId: 4, unicodeCodePoint: 0x0628, unicodeText: 'ب'),
        ]);

        $mapped = $mapper->map(
            $run,
            $font,
            new TextOptions(embeddedFont: $font->source, fontSize: 10),
            1.4,
            $pageFont,
            true,
        );

        self::assertCount(2, $mapped->positionedFragments);
        self::assertSame("\x00\x01", $mapped->positionedFragments[0]->encodedText);
        self::assertEquals(2.7, $mapped->positionedFragments[0]->xOffset);
        self::assertEqualsWithDelta(5.1, $mapped->positionedFragments[0]->yOffset, 0.0001);
    }

    public function testItBuildsPositionedFragmentsForDevanagariMarkPlacement(): void
    {
        $mapper = new SimpleFontRunMapper();
        $font = EmbeddedFontDefinition::fromSource(
            EmbeddedFontSource::fromString(TrueTypeFontFixture::minimalDevanagariGsubTrueTypeFontBytes()),
        );
        $pageFont = PageFont::embeddedUnicode($font, [
            new EmbeddedGlyph(5, 0x093F, 'ि'),
            new EmbeddedGlyph(3, 0x0915, 'क'),
            new EmbeddedGlyph(10, 0x0902, 'ं'),
        ]);
        $run = new ShapedTextRun(TextDirection::LTR, TextScript::DEVANAGARI, [
            new ShapedGlyph('ि', 1, glyphName: 'indic.prebase', glyphId: 5, unicodeCodePoint: 0x093F, unicodeText: 'ि'),
            new ShapedGlyph('क', 0, glyphName: 'indic.base', glyphId: 3, unicodeCodePoint: 0x0915, unicodeText: 'क'),
            new ShapedGlyph('ं', 2, xAdvance: -240.0, xOffset: 230.0, yOffset: 580.0, glyphName: 'gpos.mark', glyphId: 10, unicodeCodePoint: 0x0902, unicodeText: 'ं'),
        ]);

        $mapped = $mapper->map(
            $run,
            $font,
            new TextOptions(embeddedFont: $font->source, fontSize: 10),
            1.4,
            $pageFont,
            true,
        );

        self::assertCount(3, $mapped->positionedFragments);
        self::assertSame("\x00\x03", $mapped->positionedFragments[2]->encodedText);
        self::assertEquals(9.6, $mapped->positionedFragments[2]->xOffset);
        self::assertEquals(5.8, $mapped->positionedFragments[2]->yOffset);
    }

    public function testItBuildsPositionedFragmentsForStackedDevanagariMarks(): void
    {
        $mapper = new SimpleFontRunMapper();
        $font = EmbeddedFontDefinition::fromSource(
            EmbeddedFontSource::fromString(TrueTypeFontFixture::minimalDevanagariGsubTrueTypeFontBytes()),
        );
        $pageFont = PageFont::embeddedUnicode($font, [
            new EmbeddedGlyph(5, 0x093F, 'ि'),
            new EmbeddedGlyph(3, 0x0915, 'क'),
            new EmbeddedGlyph(10, 0x0902, 'ं'),
            new EmbeddedGlyph(11, 0x093C, '़'),
        ]);
        $run = new ShapedTextRun(TextDirection::LTR, TextScript::DEVANAGARI, [
            new ShapedGlyph('ि', 1, glyphName: 'indic.prebase', glyphId: 5, unicodeCodePoint: 0x093F, unicodeText: 'ि'),
            new ShapedGlyph('क', 0, glyphName: 'indic.base', glyphId: 3, unicodeCodePoint: 0x0915, unicodeText: 'क'),
            new ShapedGlyph('ं', 2, xAdvance: -240.0, xOffset: 230.0, yOffset: 580.0, glyphName: 'gpos.mark', glyphId: 10, unicodeCodePoint: 0x0902, unicodeText: 'ं'),
            new ShapedGlyph('़', 3, xAdvance: -240.0, xOffset: 300.0, yOffset: 690.0, glyphName: 'gpos.mkmk', glyphId: 11, unicodeCodePoint: 0x093C, unicodeText: '़'),
        ]);

        $mapped = $mapper->map(
            $run,
            $font,
            new TextOptions(embeddedFont: $font->source, fontSize: 10),
            1.4,
            $pageFont,
            true,
        );

        self::assertCount(4, $mapped->positionedFragments);
        self::assertSame("\x00\x04", $mapped->positionedFragments[3]->encodedText);
        self::assertEquals(10.3, $mapped->positionedFragments[3]->xOffset);
        self::assertEquals(6.9, $mapped->positionedFragments[3]->yOffset);
    }

    public function testItBuildsPositionedFragmentsForBengaliMarks(): void
    {
        $mapper = new SimpleFontRunMapper();
        $font = EmbeddedFontDefinition::fromSource(
            EmbeddedFontSource::fromString(TrueTypeFontFixture::minimalBengaliGsubTrueTypeFontBytes()),
        );
        $pageFont = PageFont::embeddedUnicode($font, [
            new EmbeddedGlyph(5, 0x09BF, 'ি'),
            new EmbeddedGlyph(3, 0x0995, 'ক'),
            new EmbeddedGlyph(10, 0x0982, 'ং'),
            new EmbeddedGlyph(11, 0x09BC, '়'),
        ]);
        $run = new ShapedTextRun(TextDirection::LTR, TextScript::BENGALI, [
            new ShapedGlyph('ি', 1, glyphName: 'indic.prebase', glyphId: 5, unicodeCodePoint: 0x09BF, unicodeText: 'ি'),
            new ShapedGlyph('ক', 0, glyphName: 'indic.base', glyphId: 3, unicodeCodePoint: 0x0995, unicodeText: 'ক'),
            new ShapedGlyph('ং', 2, xAdvance: -240.0, xOffset: 230.0, yOffset: 580.0, glyphName: 'gpos.mark', glyphId: 10, unicodeCodePoint: 0x0982, unicodeText: 'ং'),
            new ShapedGlyph('়', 3, xAdvance: -240.0, xOffset: 300.0, yOffset: 690.0, glyphName: 'gpos.mkmk', glyphId: 11, unicodeCodePoint: 0x09BC, unicodeText: '়'),
        ]);

        $mapped = $mapper->map(
            $run,
            $font,
            new TextOptions(embeddedFont: $font->source, fontSize: 10),
            1.4,
            $pageFont,
            true,
        );

        self::assertCount(4, $mapped->positionedFragments);
        self::assertSame("\x00\x03", $mapped->positionedFragments[2]->encodedText);
        self::assertEquals(9.6, $mapped->positionedFragments[2]->xOffset);
        self::assertEquals(5.8, $mapped->positionedFragments[2]->yOffset);
        self::assertSame("\x00\x04", $mapped->positionedFragments[3]->encodedText);
        self::assertEquals(10.3, $mapped->positionedFragments[3]->xOffset);
        self::assertEquals(6.9, $mapped->positionedFragments[3]->yOffset);
    }

    public function testItBuildsPositionedFragmentsForGujaratiMarks(): void
    {
        $mapper = new SimpleFontRunMapper();
        $font = EmbeddedFontDefinition::fromSource(
            EmbeddedFontSource::fromString(TrueTypeFontFixture::minimalGujaratiGsubTrueTypeFontBytes()),
        );
        $pageFont = PageFont::embeddedUnicode($font, [
            new EmbeddedGlyph(5, 0x0ABF, 'િ'),
            new EmbeddedGlyph(3, 0x0A95, 'ક'),
            new EmbeddedGlyph(10, 0x0A82, 'ં'),
            new EmbeddedGlyph(11, 0x0ABC, '઼'),
        ]);
        $run = new ShapedTextRun(TextDirection::LTR, TextScript::GUJARATI, [
            new ShapedGlyph('િ', 1, glyphName: 'indic.prebase', glyphId: 5, unicodeCodePoint: 0x0ABF, unicodeText: 'િ'),
            new ShapedGlyph('ક', 0, glyphName: 'indic.base', glyphId: 3, unicodeCodePoint: 0x0A95, unicodeText: 'ક'),
            new ShapedGlyph('ં', 2, xAdvance: -240.0, xOffset: 230.0, yOffset: 580.0, glyphName: 'gpos.mark', glyphId: 10, unicodeCodePoint: 0x0A82, unicodeText: 'ં'),
            new ShapedGlyph('઼', 3, xAdvance: -240.0, xOffset: 300.0, yOffset: 690.0, glyphName: 'gpos.mkmk', glyphId: 11, unicodeCodePoint: 0x0ABC, unicodeText: '઼'),
        ]);

        $mapped = $mapper->map(
            $run,
            $font,
            new TextOptions(embeddedFont: $font->source, fontSize: 10),
            1.4,
            $pageFont,
            true,
        );

        self::assertCount(4, $mapped->positionedFragments);
        self::assertSame("\x00\x03", $mapped->positionedFragments[2]->encodedText);
        self::assertEquals(9.6, $mapped->positionedFragments[2]->xOffset);
        self::assertEquals(5.8, $mapped->positionedFragments[2]->yOffset);
        self::assertSame("\x00\x04", $mapped->positionedFragments[3]->encodedText);
        self::assertEquals(10.3, $mapped->positionedFragments[3]->xOffset);
        self::assertEquals(6.9, $mapped->positionedFragments[3]->yOffset);
    }
}
