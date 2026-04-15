<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Font;

use InvalidArgumentException;
use Kalle\Pdf\Font\StandardFont;
use Kalle\Pdf\Font\StandardFontGlyphMap;
use Kalle\Pdf\Font\StandardFontGlyphRun;
use PHPUnit\Framework\TestCase;

final class StandardFontGlyphMapTest extends TestCase
{
    public function testItExposesAllAddressableGlyphNamesForSymbolAndZapfDingbats(): void
    {
        self::assertCount(855, StandardFontGlyphMap::glyphNames(StandardFont::HELVETICA));
        self::assertCount(189, StandardFontGlyphMap::glyphNames(StandardFont::SYMBOL));
        self::assertCount(202, StandardFontGlyphMap::glyphNames(StandardFont::ZAPF_DINGBATS));
    }

    public function testItEncodesNonUnicodeSymbolGlyphNames(): void
    {
        $encoded = StandardFontGlyphMap::encodeGlyphNames(StandardFont::SYMBOL, [
            'registerserif',
            'copyrightserif',
            'trademarkserif',
            'radicalex',
        ]);

        self::assertSame('d2d3d460', bin2hex($encoded['bytes']));
        self::assertSame([], $encoded['differences']);
    }

    public function testItEncodesZapfDingbatsGlyphNames(): void
    {
        $encoded = StandardFontGlyphMap::encodeGlyphNames(StandardFont::ZAPF_DINGBATS, [
            'a1',
            'a2',
            'a202',
            'a89',
        ]);

        self::assertSame('21222380', bin2hex($encoded['bytes']));
        self::assertSame([], $encoded['differences']);
    }

    public function testItEncodesGlyphCodesForAddressabilityByCode(): void
    {
        $encoded = StandardFontGlyphMap::encodeGlyphCodes(StandardFont::SYMBOL, [0xD2, 0xD3, 0xD4]);

        self::assertSame('d2d3d4', bin2hex($encoded['bytes']));
        self::assertTrue($encoded['useHexString']);
    }

    public function testItBuildsCoreFontGlyphRunsWithDynamicDifferences(): void
    {
        $encoded = StandardFontGlyphMap::encodeGlyphNames(StandardFont::HELVETICA, [
            'A',
            'Euro',
            'Aogonek',
        ]);

        self::assertSame('418081', bin2hex($encoded['bytes']));
        self::assertSame([128 => 'Euro', 129 => 'Aogonek'], $encoded['differences']);
        self::assertTrue($encoded['useHexString']);
    }

    public function testItRejectsUnknownGlyphNames(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Glyph 'doesNotExist' is not defined for font 'Symbol'.");

        StandardFontGlyphMap::encodeGlyphNames(StandardFont::SYMBOL, ['doesNotExist']);
    }

    public function testItBuildsGlyphRunsFromNamesAndCodes(): void
    {
        $fromNames = StandardFontGlyphRun::fromGlyphNames(StandardFont::SYMBOL, ['registerserif']);
        $fromCodes = StandardFontGlyphRun::fromGlyphCodes(StandardFont::ZAPF_DINGBATS, [0x21, 0x22]);

        self::assertSame(StandardFont::SYMBOL->value, $fromNames->fontName);
        self::assertSame('d2', bin2hex($fromNames->bytes));
        self::assertSame([], $fromNames->differences);
        self::assertSame(StandardFont::ZAPF_DINGBATS->value, $fromCodes->fontName);
        self::assertSame('2122', bin2hex($fromCodes->bytes));
        self::assertTrue($fromCodes->useHexString);
    }
}
