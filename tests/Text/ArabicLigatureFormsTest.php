<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Text;

use Kalle\Pdf\Text\ArabicJoiningForm;
use Kalle\Pdf\Text\ArabicLigatureForms;
use PHPUnit\Framework\TestCase;

final class ArabicLigatureFormsTest extends TestCase
{
    public function testItMapsLamAlefLigatures(): void
    {
        $forms = new ArabicLigatureForms();

        self::assertSame('ﻻ', $forms->lamAlefLigature('لا', ArabicJoiningForm::ISOLATED));
        self::assertSame('ﻼ', $forms->lamAlefLigature('لا', ArabicJoiningForm::FINAL));
        self::assertSame('ﻷ', $forms->lamAlefLigature('لأ', ArabicJoiningForm::ISOLATED));
        self::assertSame('ﻺ', $forms->lamAlefLigature('لإ', ArabicJoiningForm::FINAL));
    }

    public function testItDoesNotReturnInitialOrMedialLamAlefLigatures(): void
    {
        $forms = new ArabicLigatureForms();

        self::assertNull($forms->lamAlefLigature('لا', ArabicJoiningForm::INITIAL));
        self::assertNull($forms->lamAlefLigature('لا', ArabicJoiningForm::MEDIAL));
    }
}
