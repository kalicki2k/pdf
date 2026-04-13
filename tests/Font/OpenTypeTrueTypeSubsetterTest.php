<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Font;

use Kalle\Pdf\Font\EmbeddedFontSource;
use Kalle\Pdf\Font\OpenTypeFontParser;
use Kalle\Pdf\Font\OpenTypeTrueTypeSubsetter;
use PHPUnit\Framework\TestCase;

final class OpenTypeTrueTypeSubsetterTest extends TestCase
{
    public function testItBuildsASmallerSubsetFontForUsedGlyphs(): void
    {
        $fontBytes = TrueTypeFontFixture::minimalUnicodeTrueTypeFontBytes();
        $parser = new OpenTypeFontParser(EmbeddedFontSource::fromString($fontBytes));
        $subsetter = new OpenTypeTrueTypeSubsetter($parser);

        $subsetBytes = $subsetter->subset([0, 2]);

        self::assertLessThan(strlen($fontBytes), strlen($subsetBytes));
        self::assertSame('TestFont-Regular', new OpenTypeFontParser($subsetBytes)->postScriptName());
    }

    public function testItIncludesCompositeComponentGlyphsInTheSubset(): void
    {
        $fontBytes = TrueTypeFontFixture::minimalUnicodeTrueTypeFontBytes();
        $parser = new OpenTypeFontParser(EmbeddedFontSource::fromString($fontBytes));
        $subsetter = new OpenTypeTrueTypeSubsetter($parser);

        $subsetParser = new OpenTypeFontParser($subsetter->subset([0, 4]));

        self::assertNotSame('', $subsetParser->glyphDataForGlyphId(2));
        self::assertNotSame('', $subsetParser->glyphDataForGlyphId(3));
        self::assertNotSame('', $subsetParser->glyphDataForGlyphId(4));
    }
}
