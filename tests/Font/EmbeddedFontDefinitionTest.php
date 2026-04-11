<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Font;

use InvalidArgumentException;
use Kalle\Pdf\Font\EmbeddedFontDefinition;
use Kalle\Pdf\Font\EmbeddedFontSource;
use PHPUnit\Framework\TestCase;

final class EmbeddedFontDefinitionTest extends TestCase
{
    public function testItEncodesAndMeasuresSimpleWesternText(): void
    {
        $font = EmbeddedFontDefinition::fromSource(
            EmbeddedFontSource::fromString(TrueTypeFontFixture::minimalTrueTypeFontBytes()),
        );

        self::assertTrue($font->supportsText('A'));
        self::assertSame('A', $font->encodeText('A'));
        self::assertSame(12.0, $font->measureTextWidth('A', 20.0));
    }

    public function testItRejectsUnsupportedText(): void
    {
        $font = EmbeddedFontDefinition::fromSource(
            EmbeddedFontSource::fromString(TrueTypeFontFixture::minimalTrueTypeFontBytes()),
        );

        self::assertFalse($font->supportsText('B'));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Text cannot be encoded with embedded font 'TestFont-Regular'.");

        $font->encodeText('B');
    }

    public function testItRejectsCffOutlineFontsInPhaseOne(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Phase 1 only supports embedded TrueType outlines.');

        EmbeddedFontDefinition::fromSource(
            EmbeddedFontSource::fromString(TrueTypeFontFixture::minimalCffOpenTypeFontBytes()),
        );
    }
}
