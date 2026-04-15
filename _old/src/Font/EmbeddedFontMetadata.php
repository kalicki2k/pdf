<?php

declare(strict_types=1);

namespace Kalle\Pdf\Font;

final readonly class EmbeddedFontMetadata
{
    public function __construct(
        public string $postScriptName,
        public OpenTypeOutlineType $outlineType,
        public int $unitsPerEm,
        public int $ascent,
        public int $descent,
        public int $capHeight,
        public float $italicAngle,
        public FontBoundingBox $fontBoundingBox,
        public int $glyphCount,
    ) {
    }
}
