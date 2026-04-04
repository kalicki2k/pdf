<?php

declare(strict_types=1);

namespace Kalle\Pdf\Font;

use InvalidArgumentException;

final class FontRegistry
{
    public static function get(string $group): FontPreset
    {
        return match ($group) {
            'sans' => new FontPreset(
                group: 'sans',
                baseFont: 'NotoSans-Regular',
                path: 'assets/fonts/NotoSans-Regular.ttf',
                unicode: false,
            ),
            'serif' => new FontPreset(
                group: 'serif',
                baseFont: 'NotoSerif-Regular',
                path: 'assets/fonts/NotoSerif-Regular.ttf',
                unicode: false,
            ),
            'mono' => new FontPreset(
                group: 'mono',
                baseFont: 'NotoSansMono-Regular',
                path: 'assets/fonts/NotoSansMono-Regular.ttf',
                unicode: false,
            ),
            'global' => new FontPreset(
                group: 'global',
                baseFont: 'NotoSansCJKsc-Regular',
                path: 'assets/fonts/NotoSansCJKsc-Regular.otf',
                unicode: true,
                subtype: 'CIDFontType0',
                encoding: 'Identity-H',
            ),
            default => throw new InvalidArgumentException("Unknown font group '$group'."),
        };
    }

    /**
     * @return list<FontPreset>
     */
    public static function all(): array
    {
        return [
            self::get('sans'),
            self::get('serif'),
            self::get('mono'),
            self::get('global'),
        ];
    }
}
