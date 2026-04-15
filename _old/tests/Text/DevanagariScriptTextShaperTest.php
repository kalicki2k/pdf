<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Text;

use Kalle\Pdf\Font\EmbeddedFontDefinition;
use Kalle\Pdf\Font\EmbeddedFontSource;
use Kalle\Pdf\Tests\Font\TrueTypeFontFixture;
use Kalle\Pdf\Text\DevanagariScriptTextShaper;
use Kalle\Pdf\Text\ScriptRun;
use Kalle\Pdf\Text\TextDirection;
use Kalle\Pdf\Text\TextScript;
use PHPUnit\Framework\TestCase;

final class DevanagariScriptTextShaperTest extends TestCase
{
    public function testItReordersPreBaseMatraWithinASimpleCluster(): void
    {
        $shaper = new DevanagariScriptTextShaper();
        $run = new ScriptRun('कि', TextDirection::LTR, TextScript::DEVANAGARI);
        $shaped = $shaper->shape($run);

        self::assertSame('िक', $shaped->text());
        self::assertSame(['indic.prebase', 'indic.base'], $shaped->glyphNames());
    }

    public function testItReordersPreBaseMatraBeforeAConjunctBaseCluster(): void
    {
        $shaper = new DevanagariScriptTextShaper();
        $run = new ScriptRun('क्ति', TextDirection::LTR, TextScript::DEVANAGARI);
        $shaped = $shaper->shape($run);

        self::assertSame('िकत', $shaped->text());
        self::assertSame('indic.prebase', $shaped->glyphs[0]->glyphName);
        self::assertSame('indic.half', $shaped->glyphs[1]->glyphName);
        self::assertSame('indic.base', $shaped->glyphs[2]->glyphName);
    }

    public function testItKeepsPostBaseMarksAfterTheirCluster(): void
    {
        $shaper = new DevanagariScriptTextShaper();
        $run = new ScriptRun('किं', TextDirection::LTR, TextScript::DEVANAGARI);
        $shaped = $shaper->shape($run);

        self::assertSame('िकं', $shaped->text());
        self::assertSame('indic.base', $shaped->glyphs[1]->glyphName);
        self::assertSame('indic.cluster', $shaped->glyphs[2]->glyphName);
    }

    public function testItMovesLeadingRaViramaToReph(): void
    {
        $shaper = new DevanagariScriptTextShaper();
        $run = new ScriptRun('र्कि', TextDirection::LTR, TextScript::DEVANAGARI);
        $shaped = $shaper->shape($run);

        self::assertSame('िकर', $shaped->text());
        self::assertSame(['indic.prebase', 'indic.base', 'indic.reph'], $shaped->glyphNames());
        self::assertSame('र्', $shaped->glyphs[2]->unicodeText);
    }

    public function testItMarksTheLastPreBaseHalfFormAsPrefWhenMultipleHalfFormsExist(): void
    {
        $shaper = new DevanagariScriptTextShaper();
        $run = new ScriptRun('स्क्रि', TextDirection::LTR, TextScript::DEVANAGARI);
        $shaped = $shaper->shape($run);

        self::assertSame('िसकर', $shaped->text());
        self::assertSame(
            ['indic.prebase', 'indic.half', 'indic.pref', 'indic.base'],
            $shaped->glyphNames(),
        );
    }

    public function testItUsesFontBackedIndicGsubFeaturesWhenAvailable(): void
    {
        $shaper = new DevanagariScriptTextShaper();
        $font = EmbeddedFontDefinition::fromSource(
            EmbeddedFontSource::fromString(TrueTypeFontFixture::minimalDevanagariGsubTrueTypeFontBytes()),
        );

        $halfRun = new ScriptRun('क्ति', TextDirection::LTR, TextScript::DEVANAGARI);
        $halfShaped = $shaper->shape($halfRun, $font);
        self::assertSame('gsub.half', $halfShaped->glyphs[1]->glyphName);
        self::assertSame(9, $halfShaped->glyphs[1]->glyphId);

        $prefRun = new ScriptRun('स्त्कि', TextDirection::LTR, TextScript::DEVANAGARI);
        $prefShaped = $shaper->shape($prefRun, $font);
        self::assertSame('gsub.half', $prefShaped->glyphs[1]->glyphName);
        self::assertSame(6, $prefShaped->glyphs[1]->glyphId);
        self::assertSame('gsub.pref', $prefShaped->glyphs[2]->glyphName);
        self::assertSame(7, $prefShaped->glyphs[2]->glyphId);

        $rephRun = new ScriptRun('र्कि', TextDirection::LTR, TextScript::DEVANAGARI);
        $rephShaped = $shaper->shape($rephRun, $font);
        self::assertSame('gsub.rphf', $rephShaped->glyphs[2]->glyphName);
        self::assertSame(8, $rephShaped->glyphs[2]->glyphId);
        self::assertSame('र्', $rephShaped->glyphs[2]->unicodeText);

        $markRun = new ScriptRun('किं', TextDirection::LTR, TextScript::DEVANAGARI);
        $markShaped = $shaper->shape($markRun, $font);
        self::assertSame('gpos.mark', $markShaped->glyphs[2]->glyphName);
        self::assertSame(10, $markShaped->glyphs[2]->glyphId);
        self::assertSame(-240.0, $markShaped->glyphs[2]->xAdvance);
        self::assertSame(230.0, $markShaped->glyphs[2]->xOffset);
        self::assertSame(580.0, $markShaped->glyphs[2]->yOffset);

        $stackedMarkRun = new ScriptRun('किं़', TextDirection::LTR, TextScript::DEVANAGARI);
        $stackedMarkShaped = $shaper->shape($stackedMarkRun, $font);
        self::assertSame('gpos.mark', $stackedMarkShaped->glyphs[2]->glyphName);
        self::assertSame('gpos.mkmk', $stackedMarkShaped->glyphs[3]->glyphName);
        self::assertSame(11, $stackedMarkShaped->glyphs[3]->glyphId);
        self::assertSame(-240.0, $stackedMarkShaped->glyphs[3]->xAdvance);
        self::assertSame(300.0, $stackedMarkShaped->glyphs[3]->xOffset);
        self::assertSame(690.0, $stackedMarkShaped->glyphs[3]->yOffset);
    }
}
