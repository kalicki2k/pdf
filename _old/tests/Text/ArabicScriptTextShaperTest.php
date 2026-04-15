<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Text;

use Kalle\Pdf\Font\EmbeddedFontDefinition;
use Kalle\Pdf\Font\EmbeddedFontSource;
use Kalle\Pdf\Tests\Font\TrueTypeFontFixture;
use Kalle\Pdf\Text\ArabicScriptTextShaper;
use Kalle\Pdf\Text\ScriptRun;
use Kalle\Pdf\Text\TextDirection;
use Kalle\Pdf\Text\TextScript;
use PHPUnit\Framework\TestCase;

final class ArabicScriptTextShaperTest extends TestCase
{
    public function testItShapesASingleArabicLetterAsIsolated(): void
    {
        $shaper = new ArabicScriptTextShaper();
        $run = $shaper->shape(new ScriptRun('ب', TextDirection::RTL, TextScript::ARABIC));

        self::assertSame('ﺏ', $run->text());
        self::assertSame('isolated', $run->glyphs[0]->form);
        self::assertSame('arabic.beh.isolated', $run->glyphs[0]->glyphName);
    }

    public function testItAssignsJoiningFormsForConnectedArabicLetters(): void
    {
        $shaper = new ArabicScriptTextShaper();
        $run = $shaper->shape(new ScriptRun('ببب', TextDirection::RTL, TextScript::ARABIC));

        self::assertCount(3, $run->glyphs);
        self::assertSame('ﺐﺒﺑ', $run->text());
        self::assertSame(['final', 'medial', 'initial'], array_map(
            static fn ($glyph): ?string => $glyph->form,
            $run->glyphs,
        ));
        self::assertSame(['arabic.beh.final', 'arabic.beh.medial', 'arabic.beh.initial'], array_map(
            static fn ($glyph): ?string => $glyph->glyphName,
            $run->glyphs,
        ));
    }

    public function testItHandlesRightJoiningLettersLikeAlef(): void
    {
        $shaper = new ArabicScriptTextShaper();
        $run = $shaper->shape(new ScriptRun('با', TextDirection::RTL, TextScript::ARABIC));

        self::assertSame('ﺎﺑ', $run->text());
        self::assertSame(['final', 'initial'], array_map(
            static fn ($glyph): ?string => $glyph->form,
            $run->glyphs,
        ));
    }

    public function testItBuildsAnIsolatedLamAlefLigature(): void
    {
        $shaper = new ArabicScriptTextShaper();
        $run = $shaper->shape(new ScriptRun('لا', TextDirection::RTL, TextScript::ARABIC));

        self::assertCount(1, $run->glyphs);
        self::assertSame('ﻻ', $run->text());
        self::assertSame('isolated', $run->glyphs[0]->form);
        self::assertSame('arabic.lam_alef.isolated', $run->glyphs[0]->glyphName);
    }

    public function testItBuildsAFinalLamAlefLigatureWhenConnectedToPreviousLetter(): void
    {
        $shaper = new ArabicScriptTextShaper();
        $run = $shaper->shape(new ScriptRun('بلا', TextDirection::RTL, TextScript::ARABIC));

        self::assertCount(2, $run->glyphs);
        self::assertSame('ﻼﺑ', $run->text());
        self::assertSame(['final', 'initial'], array_map(
            static fn ($glyph): ?string => $glyph->form,
            $run->glyphs,
        ));
    }

    public function testItKeepsTransparentMarksAsOwnGlyphs(): void
    {
        $shaper = new ArabicScriptTextShaper();
        $run = $shaper->shape(new ScriptRun('بَ', TextDirection::RTL, TextScript::ARABIC));

        self::assertCount(2, $run->glyphs);
        self::assertSame('َﺏ', $run->text());
        self::assertSame([null, 'isolated'], array_map(
            static fn ($glyph): ?string => $glyph->form,
            $run->glyphs,
        ));
        self::assertSame(['unicode.َ', 'arabic.beh.isolated'], array_map(
            static fn ($glyph): ?string => $glyph->glyphName,
            $run->glyphs,
        ));
    }

    public function testItKeepsJoiningAcrossTransparentMarks(): void
    {
        $shaper = new ArabicScriptTextShaper();
        $run = $shaper->shape(new ScriptRun('بَب', TextDirection::RTL, TextScript::ARABIC));

        self::assertCount(3, $run->glyphs);
        self::assertSame('ﺐَﺑ', $run->text());
        self::assertSame(['final', null, 'initial'], array_map(
            static fn ($glyph): ?string => $glyph->form,
            $run->glyphs,
        ));
    }

    public function testItUsesFontGsubForArabicSingleSubstitutionsWhenAvailable(): void
    {
        $shaper = new ArabicScriptTextShaper();
        $font = EmbeddedFontDefinition::fromSource(
            EmbeddedFontSource::fromString(TrueTypeFontFixture::minimalArabicGsubTrueTypeFontBytes()),
        );
        $run = $shaper->shape(new ScriptRun('ببب', TextDirection::RTL, TextScript::ARABIC), $font);

        self::assertSame('ببب', $run->text());
        self::assertSame([5, 7, 6], array_map(
            static fn ($glyph): ?int => $glyph->glyphId,
            $run->glyphs,
        ));
        self::assertSame(['final', 'medial', 'initial'], array_map(
            static fn ($glyph): ?string => $glyph->form,
            $run->glyphs,
        ));
    }

    public function testItUsesFontGsubForLamAlefLigaturesWhenAvailable(): void
    {
        $shaper = new ArabicScriptTextShaper();
        $font = EmbeddedFontDefinition::fromSource(
            EmbeddedFontSource::fromString(TrueTypeFontFixture::minimalArabicGsubTrueTypeFontBytes()),
        );
        $run = $shaper->shape(new ScriptRun('لا', TextDirection::RTL, TextScript::ARABIC), $font);

        self::assertCount(1, $run->glyphs);
        self::assertSame(8, $run->glyphs[0]->glyphId);
        self::assertSame('gsub.rlig', $run->glyphs[0]->glyphName);
        self::assertSame('لا', $run->glyphs[0]->unicodeText);
    }

    public function testItUsesFontGposMarkPlacementForTransparentMarksWhenAvailable(): void
    {
        $shaper = new ArabicScriptTextShaper();
        $font = EmbeddedFontDefinition::fromSource(
            EmbeddedFontSource::fromString(TrueTypeFontFixture::minimalArabicGsubTrueTypeFontBytes()),
        );
        $run = $shaper->shape(new ScriptRun('بَ', TextDirection::RTL, TextScript::ARABIC), $font);

        self::assertCount(2, $run->glyphs);
        self::assertSame(9, $run->glyphs[0]->glyphId);
        self::assertSame('gpos.mark', $run->glyphs[0]->glyphName);
        self::assertSame(-200.0, $run->glyphs[0]->xAdvance);
        self::assertSame(290.0, $run->glyphs[0]->xOffset);
        self::assertSame(530.0, $run->glyphs[0]->yOffset);
        self::assertSame(4, $run->glyphs[1]->glyphId);
    }

    public function testItUsesFontGposMarkToMarkPlacementForStackedTransparentMarks(): void
    {
        $shaper = new ArabicScriptTextShaper();
        $font = EmbeddedFontDefinition::fromSource(
            EmbeddedFontSource::fromString(TrueTypeFontFixture::minimalArabicGsubTrueTypeFontBytes()),
        );
        $run = $shaper->shape(new ScriptRun('بَّ', TextDirection::RTL, TextScript::ARABIC), $font);

        self::assertCount(3, $run->glyphs);
        self::assertSame(10, $run->glyphs[0]->glyphId);
        self::assertSame('gpos.mkmk', $run->glyphs[0]->glyphName);
        self::assertSame(370.0, $run->glyphs[0]->xOffset);
        self::assertSame(710.0, $run->glyphs[0]->yOffset);
        self::assertSame(9, $run->glyphs[1]->glyphId);
        self::assertSame('gpos.mark', $run->glyphs[1]->glyphName);
        self::assertSame(4, $run->glyphs[2]->glyphId);
    }
}
