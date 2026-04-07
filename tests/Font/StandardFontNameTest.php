<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Font;

use Kalle\Pdf\Font\StandardFontName;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class StandardFontNameTest extends TestCase
{
    #[Test]
    public function it_lists_all_pdf_standard_14_fonts(): void
    {
        self::assertSame(
            [
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
            ],
            StandardFontName::all(),
        );
    }

    #[Test]
    public function it_knows_whether_a_font_name_is_a_valid_pdf_standard_font(): void
    {
        self::assertTrue(StandardFontName::isValid('Helvetica'));
        self::assertTrue(StandardFontName::isValid('Times-Roman'));
        self::assertFalse(StandardFontName::isValid('NotoSans-Regular'));
    }

    #[Test]
    public function it_resolves_standard_font_variants_for_plain_requests(): void
    {
        self::assertSame('Helvetica', StandardFontName::resolveVariant('Helvetica', false, false));
        self::assertSame('Symbol', StandardFontName::resolveVariant('Symbol', false, false));
    }

    #[Test]
    public function it_resolves_courier_helvetica_and_times_variants(): void
    {
        self::assertSame('Courier-BoldOblique', StandardFontName::resolveVariant('Courier', true, true));
        self::assertSame('Helvetica-Bold', StandardFontName::resolveVariant('Helvetica', true, false));
        self::assertSame('Helvetica-Oblique', StandardFontName::resolveVariant('Helvetica', false, true));
        self::assertSame('Times-BoldItalic', StandardFontName::resolveVariant('Times-Roman', true, true));
        self::assertSame('Times-Italic', StandardFontName::resolveVariant('Times-Bold', false, true));
    }

    #[Test]
    public function it_returns_null_for_standard_fonts_without_style_variants(): void
    {
        self::assertNull(StandardFontName::resolveVariant('Symbol', true, false));
        self::assertNull(StandardFontName::resolveVariant('ZapfDingbats', false, true));
    }
}
