<?php

declare(strict_types=1);

namespace Kalle\Pdf\Font;

final readonly class Font
{
    public static function type1(string $baseFont): self
    {
        return new self(
            subtype: 'Type1',
            baseFont: $baseFont,
        );
    }

    private function __construct(
        public string $subtype,
        public string $baseFont,
    ) {
    }
}