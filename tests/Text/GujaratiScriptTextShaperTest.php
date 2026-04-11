<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Text;

use Kalle\Pdf\Font\EmbeddedFontDefinition;
use Kalle\Pdf\Font\EmbeddedFontSource;
use Kalle\Pdf\Tests\Font\TrueTypeFontFixture;
use Kalle\Pdf\Text\GujaratiScriptTextShaper;
use Kalle\Pdf\Text\ScriptRun;
use Kalle\Pdf\Text\TextDirection;
use Kalle\Pdf\Text\TextScript;
use PHPUnit\Framework\TestCase;

final class GujaratiScriptTextShaperTest extends TestCase
{
    public function testItReordersPreBaseMatraWithinASimpleCluster(): void
    {
        $shaper = new GujaratiScriptTextShaper();
        $run = new ScriptRun('કિ', TextDirection::LTR, TextScript::GUJARATI);
        $shaped = $shaper->shape($run);

        self::assertSame('િક', $shaped->text());
        self::assertSame(['indic.prebase', 'indic.base'], $shaped->glyphNames());
    }

    public function testItMovesLeadingRaViramaToReph(): void
    {
        $shaper = new GujaratiScriptTextShaper();
        $run = new ScriptRun('ર્કિ', TextDirection::LTR, TextScript::GUJARATI);
        $shaped = $shaper->shape($run);

        self::assertSame('િકર', $shaped->text());
        self::assertSame(['indic.prebase', 'indic.base', 'indic.reph'], $shaped->glyphNames());
        self::assertSame('ર્', $shaped->glyphs[2]->unicodeText);
    }

    public function testItMarksTheLastPreBaseHalfFormAsPrefWhenMultipleHalfFormsExist(): void
    {
        $shaper = new GujaratiScriptTextShaper();
        $run = new ScriptRun('સ્ત્કિ', TextDirection::LTR, TextScript::GUJARATI);
        $shaped = $shaper->shape($run);

        self::assertSame('િસતક', $shaped->text());
        self::assertSame(
            ['indic.prebase', 'indic.half', 'indic.pref', 'indic.base'],
            $shaped->glyphNames(),
        );
    }

    public function testItUsesFontBackedIndicGsubAndGposFeaturesWhenAvailable(): void
    {
        $shaper = new GujaratiScriptTextShaper();
        $font = EmbeddedFontDefinition::fromSource(
            EmbeddedFontSource::fromString(TrueTypeFontFixture::minimalGujaratiGsubTrueTypeFontBytes()),
        );

        $halfRun = new ScriptRun('ક્તિ', TextDirection::LTR, TextScript::GUJARATI);
        $halfShaped = $shaper->shape($halfRun, $font);
        self::assertSame('gsub.half', $halfShaped->glyphs[1]->glyphName);
        self::assertSame(9, $halfShaped->glyphs[1]->glyphId);

        $prefRun = new ScriptRun('સ્ત્કિ', TextDirection::LTR, TextScript::GUJARATI);
        $prefShaped = $shaper->shape($prefRun, $font);
        self::assertSame('gsub.half', $prefShaped->glyphs[1]->glyphName);
        self::assertSame(6, $prefShaped->glyphs[1]->glyphId);
        self::assertSame('gsub.pref', $prefShaped->glyphs[2]->glyphName);
        self::assertSame(7, $prefShaped->glyphs[2]->glyphId);

        $rephRun = new ScriptRun('ર્કિ', TextDirection::LTR, TextScript::GUJARATI);
        $rephShaped = $shaper->shape($rephRun, $font);
        self::assertSame('gsub.rphf', $rephShaped->glyphs[2]->glyphName);
        self::assertSame(8, $rephShaped->glyphs[2]->glyphId);
        self::assertSame('ર્', $rephShaped->glyphs[2]->unicodeText);

        $markRun = new ScriptRun('કિં', TextDirection::LTR, TextScript::GUJARATI);
        $markShaped = $shaper->shape($markRun, $font);
        self::assertSame('gpos.mark', $markShaped->glyphs[2]->glyphName);
        self::assertSame(10, $markShaped->glyphs[2]->glyphId);
        self::assertSame(-240.0, $markShaped->glyphs[2]->xAdvance);
        self::assertSame(230.0, $markShaped->glyphs[2]->xOffset);
        self::assertSame(580.0, $markShaped->glyphs[2]->yOffset);

        $stackedMarkRun = new ScriptRun('કિં઼', TextDirection::LTR, TextScript::GUJARATI);
        $stackedMarkShaped = $shaper->shape($stackedMarkRun, $font);
        self::assertSame('gpos.mark', $stackedMarkShaped->glyphs[2]->glyphName);
        self::assertSame('gpos.mkmk', $stackedMarkShaped->glyphs[3]->glyphName);
        self::assertSame(11, $stackedMarkShaped->glyphs[3]->glyphId);
        self::assertSame(-240.0, $stackedMarkShaped->glyphs[3]->xAdvance);
        self::assertSame(300.0, $stackedMarkShaped->glyphs[3]->xOffset);
        self::assertSame(690.0, $stackedMarkShaped->glyphs[3]->yOffset);
    }
}
