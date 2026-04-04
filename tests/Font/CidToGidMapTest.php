<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Font;

use Kalle\Pdf\Font\CidToGidMap;
use Kalle\Pdf\Font\OpenTypeFontParser;
use Kalle\Pdf\Font\UnicodeGlyphMap;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CidToGidMapTest extends TestCase
{
    #[Test]
    public function it_renders_a_cid_to_gid_map_stream(): void
    {
        $glyphMap = new UnicodeGlyphMap();
        $glyphMap->encodeText('漢字');
        $parser = new OpenTypeFontParser(file_get_contents('assets/fonts/NotoSansCJKsc-Regular.otf'));
        $map = new CidToGidMap(50, $glyphMap, $parser);

        $rendered = $map->render();

        self::assertStringContainsString("50 0 obj\n", $rendered);
        self::assertStringContainsString("stream\n", $rendered);
        self::assertStringContainsString("endstream\n", $rendered);
    }
}
