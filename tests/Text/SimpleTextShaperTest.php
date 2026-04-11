<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Text;

use Kalle\Pdf\Font\EmbeddedFontDefinition;
use Kalle\Pdf\Font\EmbeddedFontSource;
use Kalle\Pdf\Tests\Font\TrueTypeFontFixture;
use Kalle\Pdf\Text\SimpleTextShaper;
use Kalle\Pdf\Text\TextDirection;
use Kalle\Pdf\Text\TextScript;
use PHPUnit\Framework\TestCase;

final class SimpleTextShaperTest extends TestCase
{
    public function testItShapesPureLtrTextIntoASingleRun(): void
    {
        $shaper = new SimpleTextShaper();
        $runs = $shaper->shape('Hello');

        self::assertCount(1, $runs);
        self::assertSame(TextDirection::LTR, $runs[0]->direction);
        self::assertSame(TextScript::LATIN, $runs[0]->script);
        self::assertSame('Hello', $runs[0]->text());
    }

    public function testItReversesGlyphOrderInsideRtlRuns(): void
    {
        $shaper = new SimpleTextShaper();
        $runs = $shaper->shape('שלום');

        self::assertCount(1, $runs);
        self::assertSame(TextDirection::RTL, $runs[0]->direction);
        self::assertSame(TextScript::HEBREW, $runs[0]->script);
        self::assertSame('םולש', $runs[0]->text());
    }

    public function testItUsesArabicJoiningAwareShapingForArabicRuns(): void
    {
        $shaper = new SimpleTextShaper();
        $runs = $shaper->shape('ببب');

        self::assertCount(1, $runs);
        self::assertSame(TextScript::ARABIC, $runs[0]->script);
        self::assertSame(['final', 'medial', 'initial'], array_map(
            static fn ($glyph): ?string => $glyph->form,
            $runs[0]->glyphs,
        ));
    }

    public function testItUsesDevanagariReorderingForIndicRuns(): void
    {
        $shaper = new SimpleTextShaper();
        $runs = $shaper->shape('कि');

        self::assertCount(1, $runs);
        self::assertSame(TextDirection::LTR, $runs[0]->direction);
        self::assertSame(TextScript::DEVANAGARI, $runs[0]->script);
        self::assertSame('िक', $runs[0]->text());
        self::assertSame(['indic.prebase', 'indic.base'], $runs[0]->glyphNames());
    }

    public function testItShapesMixedDirectionalTextIntoSeparateRuns(): void
    {
        $shaper = new SimpleTextShaper();
        $runs = $shaper->shape('Hello שלום world');

        self::assertCount(3, $runs);
        self::assertSame('Hello ', $runs[0]->text());
        self::assertSame(TextDirection::LTR, $runs[0]->direction);
        self::assertSame(TextScript::LATIN, $runs[0]->script);
        self::assertSame(' םולש', $runs[1]->text());
        self::assertSame(TextDirection::RTL, $runs[1]->direction);
        self::assertSame(TextScript::HEBREW, $runs[1]->script);
        self::assertSame('world', $runs[2]->text());
        self::assertSame(TextDirection::LTR, $runs[2]->direction);
        self::assertSame(TextScript::LATIN, $runs[2]->script);
    }

    public function testItMirrorsBracketCharactersInsideRtlRuns(): void
    {
        $shaper = new SimpleTextShaper();
        $runs = $shaper->shape('abc (שלום) def');

        self::assertCount(3, $runs);
        self::assertSame(' (םולש)', $runs[1]->text());
        self::assertSame(TextDirection::RTL, $runs[1]->direction);
        self::assertSame(TextScript::HEBREW, $runs[1]->script);
    }

    public function testItUsesGeneralLigaGsubLigaturesWhenAvailable(): void
    {
        $shaper = new SimpleTextShaper();
        $font = EmbeddedFontDefinition::fromSource(
            EmbeddedFontSource::fromString(TrueTypeFontFixture::minimalLatinLigaTrueTypeFontBytes()),
        );
        $runs = $shaper->shape('fi', TextDirection::LTR, $font);

        self::assertCount(1, $runs);
        self::assertCount(1, $runs[0]->glyphs);
        self::assertSame('fi', $runs[0]->text());
        self::assertSame(3, $runs[0]->glyphs[0]->glyphId);
        self::assertSame('gsub.liga', $runs[0]->glyphs[0]->glyphName);
        self::assertSame('fi', $runs[0]->glyphs[0]->unicodeText);
    }

    public function testItUsesGeneralCaltContextualSubstitutionsWhenAvailable(): void
    {
        $shaper = new SimpleTextShaper();
        $font = EmbeddedFontDefinition::fromSource(
            EmbeddedFontSource::fromString(TrueTypeFontFixture::minimalLatinContextualTrueTypeFontBytes()),
        );
        $runs = $shaper->shape('fi', TextDirection::LTR, $font);

        self::assertCount(1, $runs);
        self::assertCount(2, $runs[0]->glyphs);
        self::assertSame('fi', $runs[0]->text());
        self::assertSame(3, $runs[0]->glyphs[0]->glyphId);
        self::assertSame('gsub.calt', $runs[0]->glyphs[0]->glyphName);
        self::assertSame('f', $runs[0]->glyphs[0]->unicodeText);
        self::assertNull($runs[0]->glyphs[1]->glyphId);
        self::assertNull($runs[0]->glyphs[1]->glyphName);
    }
}
