<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Internal\Font;

use InvalidArgumentException;
use Kalle\Pdf\Internal\Font\EncodingDictionary;
use Kalle\Pdf\Internal\Font\OpenTypeFontParser;
use Kalle\Pdf\Internal\Font\StandardFont;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

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
    public function it_does_not_support_german_sharp_s_with_plain_standard_encoding(): void
    {
        $font = new StandardFont(6, 'Helvetica', 'Type1', 'StandardEncoding', 1.0);

        self::assertFalse($font->supportsText('Straße'));
    }

    #[Test]
    public function it_rejects_encoding_text_that_the_font_cannot_represent(): void
    {
        $font = new StandardFont(6, 'Helvetica', 'Type1', 'StandardEncoding', 1.0);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Text cannot be encoded with font 'Helvetica'.");

        $font->encodeText('Straße');
    }

    #[Test]
    public function it_keeps_plain_ascii_text_unchanged_when_no_php_encoding_mapping_is_used(): void
    {
        $font = new StandardFont(6, 'Helvetica', 'Type1', 'StandardEncoding', 1.0);

        self::assertSame('(Plain ASCII)', $font->encodeText('Plain ASCII'));
    }

    #[Test]
    public function it_encodes_western_characters_with_a_custom_standard_encoding_dictionary(): void
    {
        $font = new StandardFont(
            6,
            'Helvetica',
            'Type1',
            'StandardEncoding',
            1.0,
            encodingDictionary: new EncodingDictionary(7, 'StandardEncoding', [128 => 'Adieresis', 138 => 'adieresis', 133 => 'Odieresis', 154 => 'odieresis', 134 => 'Udieresis', 159 => 'udieresis', 167 => 'germandbls', 136 => 'agrave', 135 => 'aacute', 141 => 'ccedilla', 143 => 'egrave', 142 => 'eacute']),
            byteMap: [
                'Ä' => "\x80",
                'ä' => "\x8A",
                'Ö' => "\x85",
                'ö' => "\x9A",
                'Ü' => "\x86",
                'ü' => "\x9F",
                'ß' => "\xA7",
                'à' => "\x88",
                'á' => "\x87",
                'ç' => "\x8D",
                'è' => "\x8F",
                'é' => "\x8E",
            ],
        );

        self::assertTrue($font->supportsText('ÄäÖöÜüßàáçèé'));
        self::assertSame("(\x80\x8A\x85\x9A\x86\x9F\xA7\x88\x87\x8D\x8F\x8E)", $font->encodeText('ÄäÖöÜüßàáçèé'));
    }

    #[Test]
    public function it_rejects_unmapped_non_ascii_characters_in_the_byte_map_encoder(): void
    {
        $font = new StandardFont(
            6,
            'Helvetica',
            'Type1',
            'StandardEncoding',
            1.0,
            encodingDictionary: new EncodingDictionary(7, 'StandardEncoding', [128 => 'Adieresis']),
            byteMap: [
                'Ä' => "\x80",
            ],
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Text cannot be encoded with font 'Helvetica'.");

        $method = new ReflectionMethod($font, 'encodeWithByteMap');
        $method->invoke($font, 'Äß');
    }

    #[Test]
    public function it_rejects_characters_outside_the_supported_western_standard_encoding_set(): void
    {
        $font = new StandardFont(
            6,
            'Helvetica',
            'Type1',
            'StandardEncoding',
            1.0,
            encodingDictionary: new EncodingDictionary(7, 'StandardEncoding', [128 => 'Adieresis', 167 => 'germandbls']),
            byteMap: [
                'Ä' => "\x80",
                'ß' => "\xA7",
            ],
        );

        self::assertFalse($font->supportsText('€'));
        self::assertFalse($font->supportsText('Œ'));
    }

    #[Test]
    public function it_supports_the_expected_win_ansi_character_matrix(): void
    {
        $font = new StandardFont(6, 'Helvetica', 'Type1', 'WinAnsiEncoding', 1.4);

        self::assertTrue($font->supportsText('ÄÖÜäöüß'));
        self::assertTrue($font->supportsText('àáâãåçèéêëíìîïñóòôõúùûü'));
        self::assertTrue($font->supportsText('€ŒœŠšŽžŸ'));
        self::assertTrue($font->supportsText('„“”‘’…–—•™'));
    }

    #[Test]
    public function it_rejects_characters_outside_the_win_ansi_character_matrix(): void
    {
        $font = new StandardFont(6, 'Helvetica', 'Type1', 'WinAnsiEncoding', 1.4);

        self::assertFalse($font->supportsText('Ł'));
        self::assertFalse($font->supportsText('漢'));
    }

    #[Test]
    public function it_rejects_unknown_non_embedded_base_fonts(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("BaseFont 'NotoSans-Regular' is not a valid PDF standard font.");

        new StandardFont(6, 'NotoSans-Regular', 'Type1', 'WinAnsiEncoding', 1.4);
    }

    #[Test]
    public function it_measures_standard_font_text_width_with_core_font_metrics(): void
    {
        $font = new StandardFont(6, 'Helvetica', 'Type1', 'WinAnsiEncoding', 1.4);

        self::assertSame(22.78, $font->measureTextWidth('Hello', 10));
    }

    #[Test]
    public function it_measures_plain_ascii_text_without_php_encoding_conversion_when_no_mapping_is_used(): void
    {
        $font = new StandardFont(6, 'Helvetica', 'Type1', 'StandardEncoding', 1.0);

        self::assertSame(22.78, $font->measureTextWidth('Hello', 10));
    }

    #[Test]
    public function it_measures_text_with_embedded_font_metrics_when_a_font_parser_is_available(): void
    {
        $font = new StandardFont(
            6,
            'NotoSans-Regular',
            'TrueType',
            'WinAnsiEncoding',
            1.4,
            new OpenTypeFontParser(file_get_contents('assets/fonts/NotoSans-Regular.ttf')),
        );

        self::assertGreaterThan(0.0, $font->measureTextWidth('Hello', 10));
        self::assertNotSame(30.0, $font->measureTextWidth('Hello', 10));
    }
}
