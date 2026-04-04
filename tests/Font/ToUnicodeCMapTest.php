<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Font;

use Kalle\Pdf\Font\ToUnicodeCMap;
use Kalle\Pdf\Font\UnicodeGlyphMap;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ToUnicodeCMapTest extends TestCase
{
    #[Test]
    public function it_renders_a_to_unicode_cmap_stream_from_the_glyph_map(): void
    {
        $glyphMap = new UnicodeGlyphMap();
        $glyphMap->encodeText('漢字');

        $cmap = new ToUnicodeCMap(40, $glyphMap);
        $rendered = $cmap->render();

        self::assertStringContainsString('/CIDInit /ProcSet findresource begin', $rendered);
        self::assertStringContainsString('2 beginbfchar', $rendered);
        self::assertStringContainsString('<0001> <6F22>', $rendered);
        self::assertStringContainsString('<0002> <5B57>', $rendered);
        self::assertStringContainsString('endcmap', $rendered);
    }
}
