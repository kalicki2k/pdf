<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Text;

use Kalle\Pdf\Text\ArabicGlyphSubstitutor;
use Kalle\Pdf\Text\ArabicJoiningForm;
use PHPUnit\Framework\TestCase;

final class ArabicGlyphSubstitutorTest extends TestCase
{
    public function testItReturnsPresentationFormCharactersAndGlyphNames(): void
    {
        $substitutor = new ArabicGlyphSubstitutor();
        $substitution = $substitutor->presentationForm('ب', ArabicJoiningForm::INITIAL);

        self::assertSame('ﺑ', $substitution->character);
        self::assertSame('arabic.beh.initial', $substitution->glyphName);
    }

    public function testItReturnsLamAlefLigatureCharactersAndGlyphNames(): void
    {
        $substitutor = new ArabicGlyphSubstitutor();
        $substitution = $substitutor->lamAlefLigature('لا', ArabicJoiningForm::FINAL);

        self::assertNotNull($substitution);
        self::assertSame('ﻼ', $substitution->character);
        self::assertSame('arabic.lam_alef.final', $substitution->glyphName);
    }

    public function testItFallsBackToTheOriginalCharacterForUnknownPresentationForms(): void
    {
        $substitutor = new ArabicGlyphSubstitutor();
        $substitution = $substitutor->presentationForm('٪', ArabicJoiningForm::ISOLATED);

        self::assertSame('٪', $substitution->character);
        self::assertSame('unicode.٪', $substitution->glyphName);
    }
}
