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
}
