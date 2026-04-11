<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Font;

use Kalle\Pdf\Font\StandardFont;
use PHPUnit\Framework\TestCase;

final class StandardFontTest extends TestCase
{
    public function testItListsAllPdfStandard14Fonts(): void
    {
        self::assertSame([
            'Courier',
            'Courier-Bold',
            'Courier-BoldOblique',
            'Courier-Oblique',
            'Helvetica',
            'Helvetica-Bold',
            'Helvetica-BoldOblique',
            'Helvetica-Oblique',
            'Symbol',
            'Times-Bold',
            'Times-BoldItalic',
            'Times-Italic',
            'Times-Roman',
            'ZapfDingbats',
        ], StandardFont::names());
    }

    public function testItKnowsWhetherAFontIsAValidPdfStandardFont(): void
    {
        self::assertTrue(StandardFont::isValid('Helvetica'));
        self::assertTrue(StandardFont::isValid('Times-Roman'));
        self::assertFalse(StandardFont::isValid('NotoSans-Regular'));
    }

    public function testItResolvesVariantsForCourierHelveticaAndTimes(): void
    {
        self::assertSame(StandardFont::COURIER_BOLD_OBLIQUE, StandardFont::resolveVariant('Courier', true, true));
        self::assertSame(StandardFont::HELVETICA_BOLD, StandardFont::resolveVariant('Helvetica', true, false));
        self::assertSame(StandardFont::HELVETICA_OBLIQUE, StandardFont::resolveVariant('Helvetica', false, true));
        self::assertSame(StandardFont::TIMES_BOLD_ITALIC, StandardFont::resolveVariant('Times-Roman', true, true));
    }

    public function testItReturnsNullForFontsWithoutStyleVariants(): void
    {
        self::assertNull(StandardFont::resolveVariant('Symbol', true, false));
        self::assertNull(StandardFont::resolveVariant('ZapfDingbats', false, true));
        self::assertNull(StandardFont::resolveVariant('Unknown', true, true));
    }
}
