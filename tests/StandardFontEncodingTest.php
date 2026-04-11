<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests;

use InvalidArgumentException;
use Kalle\Pdf\StandardFont;
use Kalle\Pdf\StandardFontEncoding;
use Kalle\Pdf\Version;
use PHPUnit\Framework\TestCase;

final class StandardFontEncodingTest extends TestCase
{
    public function testItResolvesWinAnsiForRegularStandardFontsFromPdf11Onward(): void
    {
        self::assertSame(
            StandardFontEncoding::WIN_ANSI,
            StandardFontEncoding::forFont(StandardFont::HELVETICA->value, Version::V1_4),
        );
    }

    public function testItResolvesStandardEncodingForPdf10RegularFonts(): void
    {
        self::assertSame(
            StandardFontEncoding::STANDARD,
            StandardFontEncoding::forFont(StandardFont::HELVETICA->value, Version::V1_0),
        );
    }

    public function testItResolvesSpecialEncodingsForSymbolAndZapfDingbats(): void
    {
        self::assertSame(StandardFontEncoding::SYMBOL, StandardFontEncoding::forFont(StandardFont::SYMBOL->value, Version::V1_4));
        self::assertSame(StandardFontEncoding::ZAPF_DINGBATS, StandardFontEncoding::forFont(StandardFont::ZAPF_DINGBATS->value, Version::V1_4));
    }

    public function testItEncodesSupportedWinAnsiText(): void
    {
        $encoded = StandardFontEncoding::WIN_ANSI->encodeText('ÄÖÜäöüß€');

        self::assertSame('c4d6dce4f6fcdf80', bin2hex($encoded));
    }

    public function testItRejectsUnsupportedWinAnsiText(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Text cannot be encoded with 'WinAnsiEncoding'.");

        StandardFontEncoding::WIN_ANSI->encodeText('漢');
    }

    public function testItRejectsNonAsciiTextForStandardEncoding(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Text cannot be encoded with 'StandardEncoding'.");

        StandardFontEncoding::STANDARD->encodeText('Straße');
    }

    public function testItEncodesSupportedSymbolText(): void
    {
        $encoded = StandardFontEncoding::SYMBOL->encodeText('αβγΩ←→∞×∂≈');

        self::assertSame('61626757acaea5b4b6bb', bin2hex($encoded));
    }

    public function testItEncodesSupportedZapfDingbatsText(): void
    {
        $encoded = StandardFontEncoding::ZAPF_DINGBATS->encodeText('✓✔✕✖①②③④⑤⑥⑦⑧⑨⑩❷❸❹❺');

        self::assertSame('33343536acadaeafb0b1b2b3b4b5b7b8b9ba', bin2hex($encoded));
    }

    public function testItRejectsUnsupportedSymbolText(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Text cannot be encoded with 'SymbolEncoding'.");

        StandardFontEncoding::SYMBOL->encodeText('Hello');
    }

    public function testItRejectsUnsupportedZapfDingbatsText(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Text cannot be encoded with 'ZapfDingbatsEncoding'.");

        StandardFontEncoding::ZAPF_DINGBATS->encodeText('Hello');
    }
}
