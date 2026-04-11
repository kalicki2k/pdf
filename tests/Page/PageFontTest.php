<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Page;

use InvalidArgumentException;
use Kalle\Pdf\Font\StandardFont;
use Kalle\Pdf\Font\StandardFontEncoding;
use Kalle\Pdf\Page\PageFont;
use PHPUnit\Framework\TestCase;

final class PageFontTest extends TestCase
{
    public function testItBuildsAStableDeduplicationKey(): void
    {
        $font = new PageFont(
            StandardFont::HELVETICA->value,
            StandardFontEncoding::WIN_ANSI,
            [128 => 'Euro', 129 => 'Aogonek'],
        );

        self::assertSame(
            'Helvetica|WinAnsiEncoding|{"128":"Euro","129":"Aogonek"}',
            $font->key(),
        );
    }

    public function testItBuildsPdfObjectContents(): void
    {
        $font = new PageFont(
            StandardFont::HELVETICA->value,
            StandardFontEncoding::WIN_ANSI,
            [128 => 'Euro', 129 => 'Aogonek'],
        );

        self::assertSame(
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding << /Type /Encoding /BaseEncoding /WinAnsiEncoding /Differences [128 /Euro /Aogonek] >> >>',
            $font->pdfObjectContents(),
        );
    }

    public function testItMatchesFontIdentity(): void
    {
        $font = new PageFont(StandardFont::HELVETICA->value, StandardFontEncoding::WIN_ANSI);

        self::assertTrue($font->matches(StandardFont::HELVETICA->value, StandardFontEncoding::WIN_ANSI));
        self::assertFalse($font->matches(StandardFont::TIMES_ROMAN->value, StandardFontEncoding::WIN_ANSI));
    }

    public function testItRejectsUnknownStandardFonts(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new PageFont('NotoSans-Regular', StandardFontEncoding::WIN_ANSI);
    }
}
