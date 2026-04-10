<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Internal\Font;

use InvalidArgumentException;
use Kalle\Pdf\Internal\Font\UnicodeGlyphMap;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

final class UnicodeGlyphMapTest extends TestCase
{
    #[Test]
    public function it_assigns_incrementing_codes_to_new_characters(): void
    {
        $map = new UnicodeGlyphMap();

        self::assertSame('<00010002>', $map->encodeText('漢字'));
        self::assertSame(
            [
                '漢' => '0001',
                '字' => '0002',
            ],
            $map->getCharacterMap(),
        );
    }

    #[Test]
    public function it_reuses_existing_codes_for_repeated_characters(): void
    {
        $map = new UnicodeGlyphMap();

        self::assertSame('<000100020001>', $map->encodeText('漢字漢'));
        self::assertSame('<00020001>', $map->encodeText('字漢'));
    }

    #[Test]
    public function it_exposes_code_and_code_point_maps_for_assigned_characters(): void
    {
        $map = new UnicodeGlyphMap();
        $map->encodeText('Ä字');

        self::assertSame([
            '0001' => 'Ä',
            '0002' => '字',
        ], $map->getCodeMap());

        self::assertSame([
            '0001' => '00C4',
            '0002' => '5B57',
        ], $map->getCodePointMap());
    }

    #[Test]
    public function it_rejects_assigning_more_than_the_available_16_bit_code_space(): void
    {
        $map = new UnicodeGlyphMap();
        $nextCodePoint = new ReflectionProperty(UnicodeGlyphMap::class, 'nextCodePoint');
        $nextCodePoint->setValue($map, 0x10000);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unicode glyph map exhausted the available 16-bit code space.');

        $map->encodeText('A');
    }
}
