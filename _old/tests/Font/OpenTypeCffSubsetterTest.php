<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Font;

use Kalle\Pdf\Font\EmbeddedFontSource;
use Kalle\Pdf\Font\OpenTypeCffSubsetter;
use Kalle\Pdf\Font\OpenTypeFontParser;
use PHPUnit\Framework\TestCase;

final class OpenTypeCffSubsetterTest extends TestCase
{
    public function testItBuildsASmallerSubsettableOpenTypeCffFont(): void
    {
        $fontData = TrueTypeFontFixture::minimalUnicodeCffOpenTypeFontBytes();
        $parser = new OpenTypeFontParser(EmbeddedFontSource::fromString($fontData));

        $subset = new OpenTypeCffSubsetter($parser)->subset([0x0416, 0x1F600]);
        $subsetParser = new OpenTypeFontParser($subset);

        self::assertLessThan(strlen($fontData), strlen($subset));
        self::assertSame('TestCff-Regular', $subsetParser->postScriptName());
        self::assertSame(1, $subsetParser->getGlyphIdForCodePoint(0x0416));
        self::assertSame(2, $subsetParser->getGlyphIdForCodePoint(0x1F600));
    }
}
