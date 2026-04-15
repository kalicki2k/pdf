<?php

declare(strict_types=1);

namespace Kalle\Pdf\Text;

use Kalle\Pdf\Page\EmbeddedGlyph;

final readonly class MappedTextRun
{
    /**
     * @param list<?string> $glyphNames
     * @param list<int> $codePoints
     * @param list<EmbeddedGlyph> $embeddedGlyphs
     * @param list<int> $textAdjustments
     * @param list<PositionedTextFragment> $positionedFragments
     */
    public function __construct(
        public TextScript $script,
        public string $text,
        public string $encodedText,
        public array $glyphNames,
        public array $codePoints,
        public array $embeddedGlyphs,
        public array $textAdjustments,
        public array $positionedFragments,
        public bool $useHexString,
        public float $width,
    ) {
    }
}
