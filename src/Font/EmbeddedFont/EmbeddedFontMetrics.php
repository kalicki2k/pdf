<?php

declare(strict_types=1);

namespace Kalle\Pdf\Font\EmbeddedFont;

final readonly class EmbeddedFontMetrics
{
    public function __construct(
        public int $unitsPerEm,
        public int $ascent,
        public int $descent,
    ) {
    }
}
