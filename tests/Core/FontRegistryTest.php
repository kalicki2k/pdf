<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Core;

use InvalidArgumentException;
use Kalle\Pdf\Core\FontRegistry;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FontRegistryTest extends TestCase
{
    #[Test]
    public function it_returns_the_expected_presets_for_the_default_font_groups(): void
    {
        $sans = FontRegistry::get('sans');
        $serif = FontRegistry::get('serif');
        $mono = FontRegistry::get('mono');
        $global = FontRegistry::get('global');

        self::assertSame('NotoSans-Regular', $sans->baseFont);
        self::assertSame('assets/fonts/NotoSans-Regular.ttf', $sans->path);
        self::assertFalse($sans->unicode);

        self::assertSame('NotoSerif-Regular', $serif->baseFont);
        self::assertSame('assets/fonts/NotoSerif-Regular.ttf', $serif->path);
        self::assertFalse($serif->unicode);

        self::assertSame('NotoSansMono-Regular', $mono->baseFont);
        self::assertSame('assets/fonts/NotoSansMono-Regular.ttf', $mono->path);
        self::assertFalse($mono->unicode);

        self::assertSame('NotoSansCJKsc-Regular', $global->baseFont);
        self::assertSame('assets/fonts/NotoSansCJKsc-Regular.otf', $global->path);
        self::assertTrue($global->unicode);
        self::assertSame('CIDFontType0', $global->subtype);
        self::assertSame('Identity-H', $global->encoding);
    }

    #[Test]
    public function it_returns_all_default_font_groups(): void
    {
        self::assertSame(
            ['sans', 'serif', 'mono', 'global'],
            array_map(static fn ($preset): string => $preset->group, FontRegistry::all()),
        );
    }

    #[Test]
    public function it_rejects_unknown_font_groups(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Unknown font group 'display'.");

        FontRegistry::get('display');
    }
}
