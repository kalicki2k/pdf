<?php

declare(strict_types=1);

namespace Kalle\Pdf\Font\EmbeddedFont;

interface EmbeddedFontParser
{
    public function metrics(): EmbeddedFontMetrics;

    public function glyphIdForCodePoint(int $codePoint): int;

    public function advanceWidthForGlyphId(int $glyphId): int;
}
