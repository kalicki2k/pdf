<?php

declare(strict_types=1);

namespace Kalle\Pdf\Font;

use Kalle\Pdf\Font\StandardFont\StandardFont;

final readonly class Font
{
    public static function type1(StandardFont $font): self
    {
        return new self(
            subtype: 'Type1',
            name: $font->value,
        );
    }

    private function __construct(
        public string $subtype,
        public string $name,
    ) {
    }
}
