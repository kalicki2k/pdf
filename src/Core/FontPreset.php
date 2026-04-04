<?php

declare(strict_types=1);

namespace Kalle\Pdf\Core;

final readonly class FontPreset
{
    public function __construct(
        public string $group,
        public string $baseFont,
        public string $path,
        public bool $unicode,
        public string $subtype = 'Type1',
        public string $encoding = 'WinAnsiEncoding',
    ) {
    }
}
