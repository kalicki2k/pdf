<?php

declare(strict_types=1);

namespace Kalle\Pdf\Text;

final readonly class ArabicGlyphSubstitution
{
    public function __construct(
        public string $character,
        public string $glyphName,
    ) {
    }
}
