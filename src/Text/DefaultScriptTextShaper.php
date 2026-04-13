<?php

declare(strict_types=1);

namespace Kalle\Pdf\Text;

use function array_reverse;

use Kalle\Pdf\Debug\Debugger;
use Kalle\Pdf\Font\EmbeddedFontDefinition;
use Kalle\Pdf\Font\StandardFontDefinition;

use function mb_ord;
use function preg_split;

final readonly class DefaultScriptTextShaper implements ScriptTextShaper
{
    public function __construct(
        private ?Debugger $debugger = null,
    ) {
    }

    public function supports(TextScript $script): bool
    {
        return true;
    }

    public function shape(
        ScriptRun $run,
        StandardFontDefinition | EmbeddedFontDefinition | null $font = null,
    ): ShapedTextRun {
        $debugger = $this->debugger ?? Debugger::disabled();
        $prepareScope = $debugger->startPerformanceScope('text.shape.default.prepare', [
            'text_length' => strlen($run->text),
        ]);
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
        $prepareScope->stop([
            'text_length' => strlen($run->text),
            'character_count' => count($characters),
        ]);

        $glyphScope = $debugger->startPerformanceScope('text.shape.default.glyphs', [
            'character_count' => count($characters),
        ]);
        $embeddedFont = $font instanceof EmbeddedFontDefinition ? $font : null;
        $supportsContextualSubstitution = $embeddedFont !== null && $embeddedFont->parser->hasGsubFeature('calt');
        $supportsLigatures = $embeddedFont !== null && $embeddedFont->parser->hasGsubFeature('liga');
        $glyphs = [];
        $count = count($characters);
        $contextualScope = $debugger->startPerformanceScope('text.shape.default.gsub.calt', [
            'character_count' => $count,
            'enabled' => $supportsContextualSubstitution ? 1 : 0,
        ]);
        $ligatureScope = $debugger->startPerformanceScope('text.shape.default.gsub.liga', [
            'character_count' => $count,
            'enabled' => $supportsLigatures ? 1 : 0,
        ]);
        $fallbackScope = $debugger->startPerformanceScope('text.shape.default.fallback', [
            'character_count' => $count,
        ]);
        $contextualGlyphCount = 0;
        $ligatureGlyphCount = 0;
        $fallbackGlyphCount = 0;

        for ($index = 0; $index < $count; $index++) {
            $contextualGlyph = null;

            if ($embeddedFont !== null && $supportsContextualSubstitution) {
                $contextualGlyph = $this->gsubContextualGlyph($embeddedFont, $characters, $codePoints, $glyphIds, $index);
            }

            if ($contextualGlyph !== null) {
                $glyphs[] = $contextualGlyph;
                $contextualGlyphCount++;

                continue;
            }

            $ligatureGlyph = null;

            if ($embeddedFont !== null && $supportsLigatures) {
                $ligatureGlyph = $this->gsubLigature($embeddedFont, $characters, $codePoints, $glyphIds, $index);
            }

            if ($ligatureGlyph !== null) {
                $glyphs[] = $ligatureGlyph['glyph'];
                $ligatureGlyphCount++;
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
            $fallbackGlyphCount++;
        }

        $contextualScope->stop([
            'glyph_count' => $contextualGlyphCount,
        ]);
        $ligatureScope->stop([
            'glyph_count' => $ligatureGlyphCount,
        ]);
        $fallbackScope->stop([
            'glyph_count' => $fallbackGlyphCount,
        ]);

        $glyphScope->stop([
            'character_count' => $count,
            'glyph_count' => count($glyphs),
        ]);

        return new ShapedTextRun($run->direction, $run->script, $glyphs);
    }

    /**
     * @param list<string> $characters
     * @param list<int> $codePoints
     * @param list<int> $glyphIds
     */
    private function gsubContextualGlyph(
        EmbeddedFontDefinition $font,
        array $characters,
        array $codePoints,
        array $glyphIds,
        int $index,
    ): ?ShapedGlyph {
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
        EmbeddedFontDefinition $font,
        array $characters,
        array $codePoints,
        array $glyphIds,
        int $index,
    ): ?array {
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
