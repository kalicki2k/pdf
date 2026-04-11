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

        if ($run->direction === TextDirection::RTL) {
            $characters = array_reverse($characters);
        }

        $glyphs = [];
        $count = count($characters);

        for ($index = 0; $index < $count; $index++) {
            $contextualGlyph = $this->gsubContextualGlyph($font, $characters, $index);

            if ($contextualGlyph !== null) {
                $glyphs[] = $contextualGlyph;

                continue;
            }

            $ligatureGlyph = $this->gsubLigature($font, $characters, $index);

            if ($ligatureGlyph !== null) {
                $glyphs[] = $ligatureGlyph['glyph'];
                $index += $ligatureGlyph['consumedGlyphCount'] - 1;

                continue;
            }

            $character = $characters[$index];
            $glyphs[] = new ShapedGlyph(
                character: $character,
                cluster: $index,
                unicodeCodePoint: mb_ord($character, 'UTF-8'),
                unicodeText: $character,
            );
        }

        return new ShapedTextRun($run->direction, $run->script, $glyphs);
    }

    /**
     * @param list<string> $characters
     */
    private function gsubContextualGlyph(
        StandardFontDefinition | EmbeddedFontDefinition | null $font,
        array $characters,
        int $index,
    ): ?ShapedGlyph {
        if (!$font instanceof EmbeddedFontDefinition || !$font->parser->hasGsubFeature('calt')) {
            return null;
        }

        $glyphIds = [];
        $count = count($characters);

        for ($cursor = $index; $cursor < $count; $cursor++) {
            $glyphId = $font->parser->getGlyphIdForCharacter($characters[$cursor]);

            if ($glyphId === 0) {
                break;
            }

            $glyphIds[] = $glyphId;
            $substitution = $font->parser->substituteContextualGlyphSequenceWithFeature('calt', $glyphIds);

            if ($substitution === null) {
                continue;
            }

            $character = $characters[$index];

            return new ShapedGlyph(
                character: $character,
                cluster: $index,
                glyphName: 'gsub.calt',
                glyphId: $substitution['substitutedGlyphId'],
                unicodeCodePoint: mb_ord($character, 'UTF-8'),
                unicodeText: $character,
            );
        }

        return null;
    }

    /**
     * @param list<string> $characters
     * @return array{glyph: ShapedGlyph, consumedGlyphCount: int}|null
     */
    private function gsubLigature(
        StandardFontDefinition | EmbeddedFontDefinition | null $font,
        array $characters,
        int $index,
    ): ?array {
        if (!$font instanceof EmbeddedFontDefinition || !$font->parser->hasGsubFeature('liga')) {
            return null;
        }

        $glyphIds = [];
        $unicodeText = '';
        $count = count($characters);

        for ($cursor = $index; $cursor < $count; $cursor++) {
            $character = $characters[$cursor];
            $glyphId = $font->parser->getGlyphIdForCharacter($character);

            if ($glyphId === 0) {
                break;
            }

            $glyphIds[] = $glyphId;
            $unicodeText .= $character;

            $substitution = $font->parser->substituteGlyphSequenceWithFeature('liga', $glyphIds);

            if ($substitution === null) {
                continue;
            }

            return [
                'glyph' => new ShapedGlyph(
                    character: $unicodeText,
                    cluster: $index,
                    glyphName: 'gsub.liga',
                    glyphId: $substitution['substitutedGlyphId'],
                    unicodeCodePoint: mb_ord($characters[$index], 'UTF-8'),
                    unicodeText: $unicodeText,
                ),
                'consumedGlyphCount' => $substitution['consumedGlyphCount'],
            ];
        }

        return null;
    }
}
