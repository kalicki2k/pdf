<?php

declare(strict_types=1);

namespace Kalle\Pdf\Page;

final readonly class EmbeddedGlyph
{
    public function __construct(
        public int $glyphId,
        public int $unicodeCodePoint,
        public string $unicodeText,
    ) {
    }
}
