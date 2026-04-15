<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Font;

use InvalidArgumentException;
use Kalle\Pdf\Document\Version;
use Kalle\Pdf\Font\StandardFont;
use Kalle\Pdf\Font\StandardFontEncoding;
use Kalle\Pdf\Font\StandardFontSymbolMap;
use Kalle\Pdf\Font\StandardFontZapfDingbatsMap;
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

    public function testItHonorsAnExplicitIsoLatin1EncodingChoice(): void
    {
        self::assertSame(
            StandardFontEncoding::ISO_LATIN_1,
            StandardFontEncoding::forFont(StandardFont::HELVETICA->value, Version::V1_0, StandardFontEncoding::ISO_LATIN_1),
        );
    }

    public function testItResolvesSpecialEncodingsForSymbolAndZapfDingbats(): void
    {
        self::assertSame(StandardFontEncoding::SYMBOL, StandardFontEncoding::forFont(StandardFont::SYMBOL->value, Version::V1_4));
        self::assertSame(StandardFontEncoding::ZAPF_DINGBATS, StandardFontEncoding::forFont(StandardFont::ZAPF_DINGBATS->value, Version::V1_4));
    }

    public function testItExposesComprehensiveSymbolAndZapfDingbatsMaps(): void
    {
        self::assertCount(185, StandardFontSymbolMap::MAP);
        self::assertCount(184, StandardFontZapfDingbatsMap::MAP);
    }

    public function testItEncodesSupportedWinAnsiText(): void
    {
        $encoded = StandardFontEncoding::WIN_ANSI->encodeText('ÄÖÜäöüß€');

        self::assertSame('c4d6dce4f6fcdf80', bin2hex($encoded));
    }

    public function testItEncodesSupportedIsoLatin1Text(): void
    {
        $encoded = StandardFontEncoding::ISO_LATIN_1->encodeText('ÄÖÜäöüß');

        self::assertSame('c4d6dce4f6fcdf', bin2hex($encoded));
    }

    public function testItRejectsUnsupportedWinAnsiText(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Text cannot be encoded with 'WinAnsiEncoding'.");

        StandardFontEncoding::WIN_ANSI->encodeText('漢');
    }

    public function testItEncodesWesternPdf10TextForStandardEncoding(): void
    {
        $encoded = StandardFontEncoding::STANDARD->encodeText('ÄÖÜäöüß§£¥');

        self::assertSame('8085868a9a9fa7a4a3b4', bin2hex($encoded));
    }

    public function testItRejectsUnsupportedWesternTextForStandardEncoding(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Text cannot be encoded with 'StandardEncoding'.");

        StandardFontEncoding::STANDARD->encodeText('€');
    }

    public function testItBuildsPdfDifferencesEncodingForWesternStandardEncoding(): void
    {
        self::assertStringStartsWith(
            '<< /Type /Encoding /BaseEncoding /StandardEncoding /Differences [128 /Adieresis',
            StandardFontEncoding::STANDARD->pdfObjectValue(StandardFont::HELVETICA->value),
        );
    }

    public function testItBuildsPdfNameForIsoLatin1Encoding(): void
    {
        self::assertSame(
            '/ISOLatin1Encoding',
            StandardFontEncoding::ISO_LATIN_1->pdfObjectValue(StandardFont::HELVETICA->value),
        );
    }

    public function testItRejectsIncompatibleIsoLatin1EncodingForSymbol(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Encoding 'ISOLatin1Encoding' is not compatible with font 'Symbol'.");

        StandardFontEncoding::forFont(StandardFont::SYMBOL->value, Version::V1_4, StandardFontEncoding::ISO_LATIN_1);
    }

    public function testItRejectsWinAnsiEncodingForPdf10(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Encoding 'WinAnsiEncoding' requires PDF 1.1 or higher.");

        StandardFontEncoding::forFont(StandardFont::HELVETICA->value, Version::V1_0, StandardFontEncoding::WIN_ANSI);
    }

    public function testItEncodesSupportedSymbolText(): void
    {
        $encoded = StandardFontEncoding::SYMBOL->encodeText('αβγΩ←→∞×∂≈⇔⇒∑∫');

        self::assertSame('61626757acaea5b4b6bbdbdee5f2', bin2hex($encoded));
    }

    public function testItEncodesSupportedZapfDingbatsText(): void
    {
        $encoded = StandardFontEncoding::ZAPF_DINGBATS->encodeText('✓✔✕✖①②③④⑤⑥⑦⑧⑨⑩❷❸❹❺➛➜➝➞➟➠➡➢➣➤➥');

        self::assertSame('33343536acadaeafb0b1b2b3b4b5b7b8b9badbdcdddedfe0e1e2e3e4e5', bin2hex($encoded));
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

    public function testItEncodesExtendedSymbolAndZapfDingbatsGlyphs(): void
    {
        self::assertSame('dbe5f2', bin2hex(StandardFontEncoding::SYMBOL->encodeText('⇔∑∫')));
        self::assertSame('dbdce1fc', bin2hex(StandardFontEncoding::ZAPF_DINGBATS->encodeText('➛➜➡➼')));
    }
}
