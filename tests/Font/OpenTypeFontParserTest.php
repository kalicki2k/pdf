<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Font;

use Kalle\Pdf\Font\EmbeddedFontSource;
use Kalle\Pdf\Font\FontBoundingBox;
use Kalle\Pdf\Font\OpenTypeFontParser;
use Kalle\Pdf\Font\OpenTypeOutlineType;
use PHPUnit\Framework\TestCase;

final class OpenTypeFontParserTest extends TestCase
{
    public function testItParsesEmbeddedTrueTypeMetadata(): void
    {
        $parser = new OpenTypeFontParser(EmbeddedFontSource::fromString(TrueTypeFontFixture::minimalTrueTypeFontBytes()));
        $metadata = $parser->metadata();

        self::assertSame(OpenTypeOutlineType::TRUE_TYPE, $metadata->outlineType);
        self::assertSame('TestFont-Regular', $metadata->postScriptName);
        self::assertSame(1000, $metadata->unitsPerEm);
        self::assertSame(800, $metadata->ascent);
        self::assertSame(-200, $metadata->descent);
        self::assertSame(800, $metadata->capHeight);
        self::assertSame(0.0, $metadata->italicAngle);
        self::assertEquals(new FontBoundingBox(-50, -200, 950, 800), $metadata->fontBoundingBox);
        self::assertSame(2, $metadata->glyphCount);
    }

    public function testItResolvesGlyphIdsAndAdvanceWidthsFromTheFont(): void
    {
        $parser = new OpenTypeFontParser(TrueTypeFontFixture::minimalTrueTypeFontBytes());

        self::assertSame(1, $parser->getGlyphIdForCharacter('A'));
        self::assertSame(0, $parser->getGlyphIdForCharacter('B'));
        self::assertSame(600, $parser->getAdvanceWidthForGlyphId(1));
        self::assertSame(500, $parser->getAdvanceWidthForGlyphId(0));
    }
}
