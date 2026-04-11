<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Text;

use Kalle\Pdf\Text\ArabicJoiningForm;
use Kalle\Pdf\Text\ArabicPresentationForms;
use PHPUnit\Framework\TestCase;

final class ArabicPresentationFormsTest extends TestCase
{
    public function testItMapsDualJoiningLettersToPresentationForms(): void
    {
        $forms = new ArabicPresentationForms();

        self::assertSame('ﺑ', $forms->glyphCharacter('ب', ArabicJoiningForm::INITIAL));
        self::assertSame('ﺒ', $forms->glyphCharacter('ب', ArabicJoiningForm::MEDIAL));
        self::assertSame('ﺐ', $forms->glyphCharacter('ب', ArabicJoiningForm::FINAL));
        self::assertSame('ﺏ', $forms->glyphCharacter('ب', ArabicJoiningForm::ISOLATED));
    }

    public function testItMapsRightJoiningLettersToPresentationForms(): void
    {
        $forms = new ArabicPresentationForms();

        self::assertSame('ﺍ', $forms->glyphCharacter('ا', ArabicJoiningForm::ISOLATED));
        self::assertSame('ﺎ', $forms->glyphCharacter('ا', ArabicJoiningForm::FINAL));
    }

    public function testItFallsBackToTheOriginalCharacterWhenNoMappingExists(): void
    {
        $forms = new ArabicPresentationForms();

        self::assertSame('A', $forms->glyphCharacter('A', ArabicJoiningForm::ISOLATED));
    }
}
