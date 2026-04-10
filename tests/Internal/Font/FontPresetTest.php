<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Internal\Font;

use Kalle\Pdf\Internal\Font\FontPreset;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FontPresetTest extends TestCase
{
    #[Test]
    public function it_exposes_all_font_preset_properties(): void
    {
        $preset = new FontPreset(
            baseFont: 'NotoSans-Regular',
            path: 'assets/fonts/NotoSans-Regular.ttf',
            unicode: false,
            subtype: 'TrueType',
            encoding: 'MacRomanEncoding',
        );

        self::assertSame('NotoSans-Regular', $preset->baseFont);
        self::assertSame('assets/fonts/NotoSans-Regular.ttf', $preset->path);
        self::assertFalse($preset->unicode);
        self::assertSame('TrueType', $preset->subtype);
        self::assertSame('MacRomanEncoding', $preset->encoding);
    }
}
