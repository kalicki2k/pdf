<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Font;

use InvalidArgumentException;
use Kalle\Pdf\Font\StandardFont;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class StandardFontTest extends TestCase
{
    #[Test]
    public function it_returns_the_base_font_name(): void
    {
        $font = new StandardFont(6, 'Helvetica', 'Type1', 'WinAnsiEncoding', 1.4);

        self::assertSame('Helvetica', $font->getBaseFont());
    }

    #[Test]
    public function it_renders_the_font_dictionary(): void
    {
        $font = new StandardFont(6, 'Helvetica', 'Type1', 'WinAnsiEncoding', 1.4);

        self::assertSame(
            "6 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n",
            $font->render(),
        );
    }

    #[Test]
    public function it_rejects_disallowed_encodings_for_pdf_1_0(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Encoding 'WinAnsiEncoding' is not allowed in PDF 1.0.");

        new StandardFont(6, 'Helvetica', 'Type1', 'WinAnsiEncoding', 1.0);
    }

    #[Test]
    public function it_rejects_non_symbol_fonts_for_symbol_encoding(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("BaseFont 'Helvetica' is not compatible with 'SymbolEncoding'.");

        new StandardFont(6, 'Helvetica', 'Type1', 'SymbolEncoding', 1.4);
    }

    #[Test]
    public function it_rejects_non_zapf_dingbats_fonts_for_zapf_dingbats_encoding(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("BaseFont 'Helvetica' is not compatible with 'ZapfDingbatsEncoding'.");

        new StandardFont(6, 'Helvetica', 'Type1', 'ZapfDingbatsEncoding', 1.4);
    }

    #[Test]
    public function it_encodes_supported_text_as_a_pdf_literal_string(): void
    {
        $font = new StandardFont(6, 'Helvetica', 'Type1', 'WinAnsiEncoding', 1.4);

        self::assertSame('(Hello \\(PDF\\)\\n)', $font->encodeText("Hello (PDF)\n"));
    }

    #[Test]
    public function it_rejects_unknown_non_embedded_base_fonts(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("BaseFont 'NotoSans-Regular' is not a valid PDF standard font.");

        new StandardFont(6, 'NotoSans-Regular', 'Type1', 'WinAnsiEncoding', 1.4);
    }

    #[Test]
    public function it_estimates_text_width_without_embedded_font_metrics(): void
    {
        $font = new StandardFont(6, 'Helvetica', 'Type1', 'WinAnsiEncoding', 1.4);

        self::assertSame(30.0, $font->measureTextWidth('Hello', 10));
    }
}
