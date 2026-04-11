<?php

declare(strict_types=1);

namespace Kalle\Pdf\Text;

use function array_reverse;

use Kalle\Pdf\Font\EmbeddedFontDefinition;
use Kalle\Pdf\Font\StandardFontDefinition;

use function mb_ord;
use function preg_split;

final readonly class ArabicScriptTextShaper implements ScriptTextShaper
{
    public function __construct(
        private ArabicJoiningData $joiningData = new ArabicJoiningData(),
        private ArabicGlyphSubstitutor $glyphSubstitutor = new ArabicGlyphSubstitutor(),
    ) {
    }

    public function supports(TextScript $script): bool
    {
        return $script === TextScript::ARABIC;
    }

    public function shape(
        ScriptRun $run,
        StandardFontDefinition | EmbeddedFontDefinition | null $font = null,
    ): ShapedTextRun {
        $characters = preg_split('//u', $run->text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $glyphs = [];
        $count = count($characters);

        for ($index = 0; $index < $count; $index++) {
            $character = $characters[$index];

            if ($this->joiningData->isTransparent($character)) {
                $markToMarkGlyph = $this->gposMarkToMarkGlyph($font, $glyphs, $character, $index);

                if ($markToMarkGlyph !== null) {
                    $glyphs[] = $markToMarkGlyph;

                    continue;
                }

                $markGlyph = $this->gposMarkGlyph($font, $characters, $index);

                if ($markGlyph !== null) {
                    $glyphs[] = $markGlyph;

                    continue;
                }

                $glyphs[] = new ShapedGlyph(
                    character: $character,
                    cluster: $index,
                    glyphName: 'unicode.' . $character,
                    unicodeCodePoint: mb_ord($character, 'UTF-8'),
                    unicodeText: $character,
                );

                continue;
            }

            $gsubLigature = $this->gsubLamAlefLigature($font, $characters, $index);

            if ($gsubLigature !== null) {
                $glyphs[] = $gsubLigature;
                $index = $this->nextJoinCandidateIndex($characters, $index) ?? $index;

                continue;
            }

            $ligature = $this->lamAlefLigature($characters, $index);

            if ($ligature !== null) {
                $glyphs[] = $ligature;
                $index++;

                continue;
            }

            $joinsToPrevious = $this->joinsToPrevious($characters, $index);
            $joinsToNext = $this->joinsToNext($characters, $index);
            $form = match (true) {
                $joinsToPrevious && $joinsToNext => ArabicJoiningForm::MEDIAL,
                $joinsToPrevious => ArabicJoiningForm::FINAL,
                $joinsToNext => ArabicJoiningForm::INITIAL,
                default => ArabicJoiningForm::ISOLATED,
            };
            $gsubGlyphId = $this->gsubGlyphIdForForm($font, $character, $form);

            if ($gsubGlyphId !== null) {
                $glyphs[] = new ShapedGlyph(
                    character: $character,
                    cluster: $index,
                    form: $form->value,
                    glyphName: 'gsub.' . $form->value,
                    glyphId: $gsubGlyphId,
                    unicodeCodePoint: mb_ord($character, 'UTF-8'),
                    unicodeText: $character,
                );

                continue;
            }

            $substitution = $this->glyphSubstitutor->presentationForm($character, $form);

            $glyphs[] = new ShapedGlyph(
                character: $substitution->character,
                cluster: $index,
                form: $form->value,
                glyphName: $substitution->glyphName,
                unicodeCodePoint: mb_ord($character, 'UTF-8'),
                unicodeText: $character,
            );
        }

        if ($run->direction === TextDirection::RTL) {
            $glyphs = array_reverse($glyphs);
        }

        return new ShapedTextRun($run->direction, $run->script, $glyphs);
    }

    /**
     * @param list<ShapedGlyph> $glyphs
     */
    private function gposMarkToMarkGlyph(
        StandardFontDefinition | EmbeddedFontDefinition | null $font,
        array $glyphs,
        string $markCharacter,
        int $index,
    ): ?ShapedGlyph {
        if (!$font instanceof EmbeddedFontDefinition || !$font->parser->hasGposFeature('mkmk')) {
            return null;
        }

        $previousGlyph = $glyphs === [] ? null : $glyphs[array_key_last($glyphs)];

        if ($previousGlyph === null || !$this->joiningData->isTransparent($previousGlyph->unicodeText ?? $previousGlyph->character)) {
            return null;
        }

        $baseMarkGlyphId = $previousGlyph->glyphId;
        $markGlyphId = $font->parser->getGlyphIdForCharacter($markCharacter);

        if ($baseMarkGlyphId === null || $markGlyphId === 0) {
            return null;
        }

        $placement = $font->parser->gposMarkToMarkPlacementWithFeature('mkmk', $baseMarkGlyphId, $markGlyphId);

        if ($placement === null) {
            return null;
        }

        return new ShapedGlyph(
            character: $markCharacter,
            cluster: $index,
            xAdvance: -$font->parser->getAdvanceWidthForGlyphId($markGlyphId),
            xOffset: $previousGlyph->xOffset + $placement['xOffset'],
            yOffset: $previousGlyph->yOffset + $placement['yOffset'],
            glyphName: 'gpos.mkmk',
            glyphId: $markGlyphId,
            unicodeCodePoint: mb_ord($markCharacter, 'UTF-8'),
            unicodeText: $markCharacter,
        );
    }

    /**
     * @param list<string> $characters
     */
    private function gposMarkGlyph(
        StandardFontDefinition | EmbeddedFontDefinition | null $font,
        array $characters,
        int $index,
    ): ?ShapedGlyph {
        if (!$font instanceof EmbeddedFontDefinition || !$font->parser->hasGposFeature('mark')) {
            return null;
        }

        $baseIndex = $this->previousJoinCandidateIndex($characters, $index);

        if ($baseIndex === null) {
            return null;
        }

        $markCharacter = $characters[$index];
        $baseGlyphId = $this->glyphIdForArabicCharacter($font, $characters, $baseIndex);
        $markGlyphId = $font->parser->getGlyphIdForCharacter($markCharacter);

        if ($markGlyphId === 0) {
            return null;
        }

        $placement = $font->parser->gposMarkToBasePlacementWithFeature('mark', $baseGlyphId, $markGlyphId);

        if ($placement === null) {
            return null;
        }

        return new ShapedGlyph(
            character: $markCharacter,
            cluster: $index,
            xAdvance: -$font->parser->getAdvanceWidthForGlyphId($markGlyphId),
            xOffset: $placement['xOffset'],
            yOffset: $placement['yOffset'],
            glyphName: 'gpos.mark',
            glyphId: $markGlyphId,
            unicodeCodePoint: mb_ord($markCharacter, 'UTF-8'),
            unicodeText: $markCharacter,
        );
    }

    /**
     * @param list<string> $characters
     */
    private function gsubLamAlefLigature(
        StandardFontDefinition | EmbeddedFontDefinition | null $font,
        array $characters,
        int $index,
    ): ?ShapedGlyph {
        if (!$font instanceof EmbeddedFontDefinition || !$font->parser->hasGsubFeature('rlig')) {
            return null;
        }

        $character = $characters[$index] ?? null;
        $nextIndex = $this->nextJoinCandidateIndex($characters, $index);
        $nextCharacter = $nextIndex !== null ? $characters[$nextIndex] : null;

        if ($character !== 'ل' || $nextCharacter === null) {
            return null;
        }

        $substitution = $font->parser->substituteGlyphSequenceWithFeature('rlig', [
            $font->parser->getGlyphIdForCharacter($character),
            $font->parser->getGlyphIdForCharacter($nextCharacter),
        ]);

        if ($substitution === null || $substitution['consumedGlyphCount'] !== 2) {
            return null;
        }

        $pair = $character . $nextCharacter;
        $joinsToPrevious = $this->joinsToPrevious($characters, $index);
        $form = $joinsToPrevious ? ArabicJoiningForm::FINAL : ArabicJoiningForm::ISOLATED;
        $fallbackLigature = $this->glyphSubstitutor->lamAlefLigature($pair, $form);

        return new ShapedGlyph(
            character: $fallbackLigature->character ?? $pair,
            cluster: $index,
            form: $form->value,
            glyphName: 'gsub.rlig',
            glyphId: $substitution['substitutedGlyphId'],
            unicodeCodePoint: mb_ord($character, 'UTF-8'),
            unicodeText: $pair,
        );
    }

    /**
     * @param list<string> $characters
     */
    private function lamAlefLigature(array $characters, int $index): ?ShapedGlyph
    {
        $character = $characters[$index] ?? null;
        $nextIndex = $this->nextJoinCandidateIndex($characters, $index);
        $nextCharacter = $nextIndex !== null ? $characters[$nextIndex] : null;

        if ($character !== 'ل' || $nextCharacter === null) {
            return null;
        }

        $pair = $character . $nextCharacter;
        $joinsToPrevious = $this->joinsToPrevious($characters, $index);
        $form = $joinsToPrevious ? ArabicJoiningForm::FINAL : ArabicJoiningForm::ISOLATED;
        $ligature = $this->glyphSubstitutor->lamAlefLigature($pair, $form);

        if ($ligature === null) {
            return null;
        }

        return new ShapedGlyph(
            character: $ligature->character,
            cluster: $index,
            form: $form->value,
            glyphName: $ligature->glyphName,
            unicodeCodePoint: mb_ord($character, 'UTF-8'),
            unicodeText: $pair,
        );
    }

    /**
     * @param list<string> $characters
     */
    private function joinsToPrevious(array $characters, int $index): bool
    {
        $previousIndex = $this->previousJoinCandidateIndex($characters, $index);

        if ($previousIndex === null) {
            return false;
        }

        return $this->joiningData->canJoinToPrevious($characters[$index])
            && $this->joiningData->canJoinToNext($characters[$previousIndex]);
    }

    /**
     * @param list<string> $characters
     */
    private function joinsToNext(array $characters, int $index): bool
    {
        $nextIndex = $this->nextJoinCandidateIndex($characters, $index);

        if ($nextIndex === null) {
            return false;
        }

        return $this->joiningData->canJoinToNext($characters[$index])
            && $this->joiningData->canJoinToPrevious($characters[$nextIndex]);
    }

    /**
     * @param list<string> $characters
     */
    private function previousJoinCandidateIndex(array $characters, int $index): ?int
    {
        for ($cursor = $index - 1; $cursor >= 0; $cursor--) {
            if (!$this->joiningData->isTransparent($characters[$cursor])) {
                return $cursor;
            }
        }

        return null;
    }

    /**
     * @param list<string> $characters
     */
    private function nextJoinCandidateIndex(array $characters, int $index): ?int
    {
        $count = count($characters);

        for ($cursor = $index + 1; $cursor < $count; $cursor++) {
            if (!$this->joiningData->isTransparent($characters[$cursor])) {
                return $cursor;
            }
        }

        return null;
    }

    private function gsubGlyphIdForForm(
        StandardFontDefinition | EmbeddedFontDefinition | null $font,
        string $character,
        ArabicJoiningForm $form,
    ): ?int {
        if (!$font instanceof EmbeddedFontDefinition) {
            return null;
        }

        $featureTag = match ($form) {
            ArabicJoiningForm::ISOLATED => 'isol',
            ArabicJoiningForm::FINAL => 'fina',
            ArabicJoiningForm::INITIAL => 'init',
            ArabicJoiningForm::MEDIAL => 'medi',
        };

        if (!$font->parser->hasGsubFeature($featureTag)) {
            return null;
        }

        $glyphId = $font->parser->getGlyphIdForCharacter($character);

        return $font->parser->substituteGlyphIdWithFeature($featureTag, $glyphId);
    }

    /**
     * @param list<string> $characters
     */
    private function glyphIdForArabicCharacter(
        EmbeddedFontDefinition $font,
        array $characters,
        int $index,
    ): int {
        $character = $characters[$index];
        $joinsToPrevious = $this->joinsToPrevious($characters, $index);
        $joinsToNext = $this->joinsToNext($characters, $index);
        $form = match (true) {
            $joinsToPrevious && $joinsToNext => ArabicJoiningForm::MEDIAL,
            $joinsToPrevious => ArabicJoiningForm::FINAL,
            $joinsToNext => ArabicJoiningForm::INITIAL,
            default => ArabicJoiningForm::ISOLATED,
        };

        return $this->gsubGlyphIdForForm($font, $character, $form)
            ?? $font->parser->getGlyphIdForCharacter($character);
    }
}
