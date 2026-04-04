<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Font;

use Kalle\Pdf\Font\UnicodeGlyphMap;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

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
}
