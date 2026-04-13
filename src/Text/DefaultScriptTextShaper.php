<?php

declare(strict_types=1);

namespace Kalle\Pdf\Text;

use function array_reverse;

use Kalle\Pdf\Font\EmbeddedFontDefinition;
use Kalle\Pdf\Font\StandardFontDefinition;

use function mb_ord;
use function preg_split;

final readonly class DefaultScriptTextShaper implements ScriptTextShaper
{
    public function supports(TextScript $script): bool
    {
        return true;
    }

    public function shape(
        ScriptRun $run,
        StandardFontDefinition | EmbeddedFontDefinition | null $font = null,
    ): ShapedTextRun {
        $characters = preg_split('//u', $run->text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $codePoints = [];
        $glyphIds = [];

        foreach ($characters as $character) {
            $codePoint = mb_ord($character, 'UTF-8');
            $codePoints[] = $codePoint;
            $glyphIds[] = $font instanceof EmbeddedFontDefinition
                ? $font->parser->getGlyphIdForCodePoint($codePoint)
                : 0;
        }

        if ($run->direction === TextDirection::RTL) {
            $characters = array_reverse($characters);
            $codePoints = array_reverse($codePoints);
            $glyphIds = array_reverse($glyphIds);
        }

        $glyphs = [];
        $count = count($characters);

        for ($index = 0; $index < $count; $index++) {
            $contextualGlyph = $this->gsubContextualGlyph($font, $characters, $codePoints, $glyphIds, $index);

            if ($contextualGlyph !== null) {
                $glyphs[] = $contextualGlyph;

                continue;
            }

            $ligatureGlyph = $this->gsubLigature($font, $characters, $codePoints, $glyphIds, $index);

            if ($ligatureGlyph !== null) {
                $glyphs[] = $ligatureGlyph['glyph'];
                $index += $ligatureGlyph['consumedGlyphCount'] - 1;

                continue;
            }

            $character = $characters[$index];
            $glyphs[] = new ShapedGlyph(
                character: $character,
                cluster: $index,
                unicodeCodePoint: $codePoints[$index] ?? null,
                unicodeText: $character,
            );
        }

        return new ShapedTextRun($run->direction, $run->script, $glyphs);
    }

    /**
     * @param list<string> $characters
     * @param list<int> $codePoints
     * @param list<int> $glyphIds
     */
    private function gsubContextualGlyph(
        StandardFontDefinition | EmbeddedFontDefinition | null $font,
        array $characters,
        array $codePoints,
        array $glyphIds,
        int $index,
    ): ?ShapedGlyph {
        if (!$font instanceof EmbeddedFontDefinition || !$font->parser->hasGsubFeature('calt')) {
            return null;
        }

        $glyphSequence = [];
        $count = count($characters);

        for ($cursor = $index; $cursor < $count; $cursor++) {
            $glyphId = $glyphIds[$cursor] ?? 0;

            if ($glyphId === 0) {
                break;
            }

            $glyphSequence[] = $glyphId;
            $substitution = $font->parser->substituteContextualGlyphSequenceWithFeature('calt', $glyphSequence);

            if ($substitution === null) {
                continue;
            }

            $character = $characters[$index];

            return new ShapedGlyph(
                character: $character,
                cluster: $index,
                glyphName: 'gsub.calt',
                glyphId: $substitution['substitutedGlyphId'],
                unicodeCodePoint: $codePoints[$index] ?? null,
                unicodeText: $character,
            );
        }

        return null;
    }

    /**
     * @param list<string> $characters
     * @param list<int> $codePoints
     * @param list<int> $glyphIds
     * @return array{glyph: ShapedGlyph, consumedGlyphCount: int}|null
     */
    private function gsubLigature(
        StandardFontDefinition | EmbeddedFontDefinition | null $font,
        array $characters,
        array $codePoints,
        array $glyphIds,
        int $index,
    ): ?array {
        if (!$font instanceof EmbeddedFontDefinition || !$font->parser->hasGsubFeature('liga')) {
            return null;
        }

        $glyphSequence = [];
        $unicodeText = '';
        $count = count($characters);

        for ($cursor = $index; $cursor < $count; $cursor++) {
            $character = $characters[$cursor];
            $glyphId = $glyphIds[$cursor] ?? 0;

            if ($glyphId === 0) {
                break;
            }

            $glyphSequence[] = $glyphId;
            $unicodeText .= $character;

            $substitution = $font->parser->substituteGlyphSequenceWithFeature('liga', $glyphSequence);

            if ($substitution === null) {
                continue;
            }

            return [
                'glyph' => new ShapedGlyph(
                    character: $unicodeText,
                    cluster: $index,
                    glyphName: 'gsub.liga',
                    glyphId: $substitution['substitutedGlyphId'],
                    unicodeCodePoint: $codePoints[$index] ?? null,
                    unicodeText: $unicodeText,
                ),
                'consumedGlyphCount' => $substitution['consumedGlyphCount'],
            ];
        }

        return null;
    }
}
