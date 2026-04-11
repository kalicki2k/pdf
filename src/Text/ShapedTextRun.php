<?php

declare(strict_types=1);

namespace Kalle\Pdf\Text;

use function implode;
use function mb_ord;
use function preg_split;

final readonly class ShapedTextRun
{
    /**
     * @param list<ShapedGlyph> $glyphs
     */
    public function __construct(
        public TextDirection $direction,
        public TextScript $script,
        public array $glyphs,
    ) {
    }

    public function text(): string
    {
        return implode('', array_map(
            static fn (ShapedGlyph $glyph): string => $glyph->character,
            $this->glyphs,
        ));
    }

    /**
     * @return list<?string>
     */
    public function glyphNames(): array
    {
        return array_map(
            static fn (ShapedGlyph $glyph): ?string => $glyph->glyphName,
            $this->glyphs,
        );
    }

    /**
     * @return list<int>
     */
    public function codePoints(): array
    {
        $codePoints = [];

        foreach ($this->glyphs as $glyph) {
            $unicodeText = $glyph->unicodeText ?? $glyph->character;

            foreach (preg_split('//u', $unicodeText, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $character) {
                $codePoints[] = mb_ord($character, 'UTF-8');
            }
        }

        return $codePoints;
    }
}
