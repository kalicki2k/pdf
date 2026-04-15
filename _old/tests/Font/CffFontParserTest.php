<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Font;

use Kalle\Pdf\Font\CffFontParser;
use Kalle\Pdf\Font\EmbeddedFontSource;
use Kalle\Pdf\Font\FontBoundingBox;
use Kalle\Pdf\Font\OpenTypeFontParser;
use Kalle\Pdf\Font\OpenTypeOutlineType;
use PHPUnit\Framework\TestCase;

final class CffFontParserTest extends TestCase
{
    public function testItReadsThePostScriptNameFromAMinimalCffTable(): void
    {
        $parser = new OpenTypeFontParser(EmbeddedFontSource::fromString(TrueTypeFontFixture::minimalCffOpenTypeFontBytes()));

        self::assertSame(OpenTypeOutlineType::CFF, $parser->outlineType());
        $cff = new CffFontParser($parser->tableBytes('CFF '));

        self::assertSame('TestCff-Regular', $cff->postScriptName());
        self::assertEquals(new FontBoundingBox(-50, -200, 950, 800), $cff->fontBoundingBox());
        self::assertSame(-12.0, $cff->italicAngle());
        self::assertSame(59, $cff->charsetOffset());
        self::assertSame(62, $cff->charStringsOffset());
        self::assertSame(2, $cff->charStringCount());
        self::assertSame('TestCff-Regular', $parser->postScriptName());
        self::assertEquals(new FontBoundingBox(-50, -200, 950, 800), $parser->fontBoundingBox());
        self::assertSame(-12.0, $parser->italicAngle());
    }

    public function testItReadsCharsetSidsFromFormatOneCharsets(): void
    {
        $parser = new CffFontParser($this->buildMinimalCffTableWithCharset("\x01" . pack('nC', 391, 2)));

        self::assertSame([0, 391, 392, 393], $parser->charsetSids());
        self::assertSame(4, $parser->charStringCount());
    }

    public function testItReadsCharsetSidsFromFormatTwoCharsets(): void
    {
        $parser = new CffFontParser($this->buildMinimalCffTableWithCharset("\x02" . pack('nn', 391, 2)));

        self::assertSame([0, 391, 392, 393], $parser->charsetSids());
        self::assertSame(4, $parser->charStringCount());
    }

    private function buildMinimalCffTableWithCharset(string $charset): string
    {
        $name = 'TestCff-Regular';
        $header = "\x01\x00\x04\x01";
        $nameIndex = $this->buildCffIndex([$name]);
        $stringIndex = pack('n', 0);
        $globalSubrIndex = pack('n', 0);
        $charStringsIndex = $this->buildCffIndex(["\x0E", "\x0E", "\x0E", "\x0E"]);

        $topDict = '';
        $topDictIndex = '';

        do {
            $topDictIndex = $this->buildCffIndex([$topDict]);
            $charsetOffset = strlen($header) + strlen($nameIndex) + strlen($topDictIndex) + strlen($stringIndex) + strlen($globalSubrIndex);
            $charStringsOffset = $charsetOffset + strlen($charset);
            $topDict = $this->cffInteger(-50)
                . $this->cffInteger(-200)
                . $this->cffInteger(950)
                . $this->cffInteger(800)
                . "\x05"
                . $this->cffInteger(-12)
                . "\x0C\x02"
                . $this->cffInteger($charsetOffset)
                . "\x0F"
                . $this->cffInteger($charStringsOffset)
                . "\x11";
        } while ($this->buildCffIndex([$topDict]) !== $topDictIndex);

        return $header
            . $nameIndex
            . $topDictIndex
            . $stringIndex
            . $globalSubrIndex
            . $charset
            . $charStringsIndex;
    }

    /**
     * @param list<string> $items
     */
    private function buildCffIndex(array $items): string
    {
        if ($items === []) {
            return pack('n', 0);
        }

        $data = '';
        $offsets = [1];

        foreach ($items as $item) {
            $data .= $item;
            $offsets[] = strlen($data) + 1;
        }

        return pack('n', count($items))
            . "\x01"
            . implode('', array_map(static fn (int $offset): string => pack('C', $offset), $offsets))
            . $data;
    }

    private function cffInteger(int $value): string
    {
        if ($value >= -32768 && $value <= 32767) {
            return "\x1C" . pack('n', $value & 0xFFFF);
        }

        return "\x1D" . pack('N', $value);
    }
}
