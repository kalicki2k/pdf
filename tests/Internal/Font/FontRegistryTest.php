<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Internal\Font;

use InvalidArgumentException;
use Kalle\Pdf\Font\FontRegistry;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FontRegistryTest extends TestCase
{
    #[Test]
    public function it_returns_the_expected_presets_for_the_registered_embedded_fonts(): void
    {
        $sans = FontRegistry::get('NotoSans-Regular');
        $serif = FontRegistry::get('NotoSerif-Regular');
        $mono = FontRegistry::get('NotoSansMono-Regular');
        $global = FontRegistry::get('NotoSansCJKsc-Regular');

        self::assertSame('NotoSans-Regular', $sans->baseFont);
        self::assertSame('assets/fonts/NotoSans-Regular.ttf', $sans->path);
        self::assertTrue($sans->unicode);
        self::assertSame('CIDFontType2', $sans->subtype);
        self::assertSame('Identity-H', $sans->encoding);

        self::assertSame('NotoSerif-Regular', $serif->baseFont);
        self::assertSame('assets/fonts/NotoSerif-Regular.ttf', $serif->path);
        self::assertTrue($serif->unicode);
        self::assertSame('CIDFontType2', $serif->subtype);
        self::assertSame('Identity-H', $serif->encoding);

        self::assertSame('NotoSansMono-Regular', $mono->baseFont);
        self::assertSame('assets/fonts/NotoSansMono-Regular.ttf', $mono->path);
        self::assertTrue($mono->unicode);
        self::assertSame('CIDFontType2', $mono->subtype);
        self::assertSame('Identity-H', $mono->encoding);

        self::assertSame('NotoSansCJKsc-Regular', $global->baseFont);
        self::assertSame('assets/fonts/NotoSansCJKsc-Regular.otf', $global->path);
        self::assertTrue($global->unicode);
        self::assertSame('CIDFontType0', $global->subtype);
        self::assertSame('Identity-H', $global->encoding);
    }

    #[Test]
    public function it_returns_all_registered_embedded_fonts(): void
    {
        self::assertSame(
            [
                'NotoSans-Regular',
                'NotoSans-Bold',
                'NotoSans-Italic',
                'NotoSans-BoldItalic',
                'NotoSerif-Regular',
                'NotoSansMono-Regular',
                'NotoSansCJKsc-Regular',
            ],
            array_map(static fn ($preset): string => $preset->baseFont, FontRegistry::all()),
        );
    }

    #[Test]
    public function it_resolves_embedded_fonts_by_their_base_font_name(): void
    {
        $font = FontRegistry::get('NotoSans-Regular');

        self::assertSame('NotoSans-Regular', $font->baseFont);
        self::assertTrue(FontRegistry::has('NotoSans-Regular'));
    }

    #[Test]
    public function it_resolves_serif_and_mono_fonts_by_their_base_font_name(): void
    {
        self::assertSame('NotoSerif-Regular', FontRegistry::get('NotoSerif-Regular')->baseFont);
        self::assertSame('NotoSansMono-Regular', FontRegistry::get('NotoSansMono-Regular')->baseFont);
        self::assertTrue(FontRegistry::has('NotoSerif-Regular'));
        self::assertTrue(FontRegistry::has('NotoSansMono-Regular'));
    }

    #[Test]
    public function it_rejects_unknown_embedded_fonts(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Unknown embedded font 'display'.");

        FontRegistry::get('display');
    }
}
