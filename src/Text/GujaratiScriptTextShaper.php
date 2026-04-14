<?php

declare(strict_types=1);

namespace Kalle\Pdf\Text;

use function count;
use function mb_ord;
use function preg_match;
use function preg_split;

use Kalle\Pdf\Font\EmbeddedFontDefinition;
use Kalle\Pdf\Font\StandardFontDefinition;

final readonly class GujaratiScriptTextShaper implements ScriptTextShaper
{
    public function supports(TextScript $script): bool
    {
        return $script === TextScript::GUJARATI;
    }

    public function shape(
        ScriptRun $run,
        StandardFontDefinition | EmbeddedFontDefinition | null $font = null,
    ): ShapedTextRun {
        $characters = preg_split('//u', $run->text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $glyphs = [];
        $clusterStart = 0;
        $characterCount = count($characters);

        while ($clusterStart < $characterCount) {
            $clusterEnd = $this->clusterEnd($characters, $clusterStart);

            foreach ($this->shapeCluster(
                array_slice($characters, $clusterStart, $clusterEnd - $clusterStart),
                $clusterStart,
                $font,
            ) as $glyph) {
                $glyphs[] = $glyph;
            }

            $clusterStart = $clusterEnd;
        }

        return new ShapedTextRun($run->direction, $run->script, $glyphs);
    }

    /**
     * @param list<string> $characters
     * @return list<ShapedGlyph>
     */
    private function shapeCluster(
        array $characters,
        int $clusterOffset,
        StandardFontDefinition | EmbeddedFontDefinition | null $font,
    ): array {
        $baseIndex = $this->baseIndex($characters);
        $rephIndex = $this->rephIndex($characters, $baseIndex);
        $prefIndex = $this->prefIndex($characters, $baseIndex, $rephIndex);
        $glyphs = [];
        $preBaseGlyphs = [];

        foreach ($characters as $index => $character) {
            if ($this->isPreBaseMatra($character) || $this->isVirama($character)) {
                continue;
            }

            if ($index === $rephIndex) {
                continue;
            }

            if ($this->isPositionedMark($character)) {
                $glyphs[] = $this->shapeMarkGlyph(
                    $character,
                    $clusterOffset + $index,
                    $characters,
                    $baseIndex,
                    $glyphs,
                    $font,
                );

                continue;
            }

            $glyphs[] = $this->shapeClusterGlyph(
                character: $character,
                clusterIndex: $clusterOffset + $index,
                role: $this->glyphRoleForIndex($characters, $index, $baseIndex, $prefIndex),
                font: $font,
            );
        }

        foreach ($characters as $index => $character) {
            if (!$this->isPreBaseMatra($character)) {
                continue;
            }

            $preBaseGlyphs[] = new ShapedGlyph(
                character: $character,
                cluster: $clusterOffset + $index,
                glyphName: 'indic.prebase',
                unicodeCodePoint: mb_ord($character, 'UTF-8'),
                unicodeText: $character,
            );
        }

        if ($preBaseGlyphs !== []) {
            $insertIndex = $this->preBaseInsertionIndex($characters, $baseIndex);
            $orderedGlyphs = [];
            $glyphCount = count($glyphs);

            for ($index = 0; $index < $insertIndex && $index < $glyphCount; $index++) {
                $orderedGlyphs[] = $glyphs[$index];
            }

            foreach ($preBaseGlyphs as $preBaseGlyph) {
                $orderedGlyphs[] = $preBaseGlyph;
            }

            for ($index = $insertIndex; $index < $glyphCount; $index++) {
                $orderedGlyphs[] = $glyphs[$index];
            }

            $glyphs = $orderedGlyphs;
        }

        if ($rephIndex !== null) {
            $glyphs[] = $this->shapeClusterGlyph(
                character: $characters[$rephIndex],
                clusterIndex: $clusterOffset + $rephIndex,
                role: 'indic.reph',
                font: $font,
                unicodeText: $characters[$rephIndex] . '્',
            );
        }

        return $glyphs;
    }

    private function shapeClusterGlyph(
        string $character,
        int $clusterIndex,
        string $role,
        StandardFontDefinition | EmbeddedFontDefinition | null $font,
        ?string $unicodeText = null,
    ): ShapedGlyph {
        $glyphId = null;
        $glyphName = $role;

        if ($font instanceof EmbeddedFontDefinition) {
            $featureTag = match ($role) {
                'indic.reph' => 'rphf',
                'indic.half' => 'half',
                'indic.pref' => 'pref',
                default => null,
            };

            if ($featureTag !== null && $font->parser->hasGsubFeature($featureTag)) {
                $sourceGlyphId = $font->parser->getGlyphIdForCharacter($character);
                $substitutedGlyphId = $font->parser->substituteGlyphIdWithFeature($featureTag, $sourceGlyphId);

                if ($substitutedGlyphId !== null) {
                    $glyphId = $substitutedGlyphId;
                    $glyphName = 'gsub.' . $featureTag;
                }
            }
        }

        return new ShapedGlyph(
            character: $character,
            cluster: $clusterIndex,
            glyphName: $glyphName,
            glyphId: $glyphId,
            unicodeCodePoint: mb_ord($character, 'UTF-8'),
            unicodeText: $unicodeText ?? $character,
        );
    }

    /**
     * @param list<string> $characters
     * @param list<ShapedGlyph> $glyphs
     */
    private function shapeMarkGlyph(
        string $character,
        int $clusterIndex,
        array $characters,
        int $baseIndex,
        array $glyphs,
        StandardFontDefinition | EmbeddedFontDefinition | null $font,
    ): ShapedGlyph {
        if ($font instanceof EmbeddedFontDefinition && $font->parser->hasGposFeature('mkmk')) {
            $previousGlyph = $glyphs === [] ? null : array_last($glyphs);

            if ($previousGlyph !== null && $this->isPositionedMark($previousGlyph->unicodeText ?? $previousGlyph->character)) {
                $baseMarkGlyphId = $previousGlyph->glyphId;
                $markGlyphId = $font->parser->getGlyphIdForCharacter($character);

                if ($baseMarkGlyphId !== null && $markGlyphId !== 0) {
                    $placement = $font->parser->gposMarkToMarkPlacementWithFeature('mkmk', $baseMarkGlyphId, $markGlyphId);

                    if ($placement !== null) {
                        return new ShapedGlyph(
                            character: $character,
                            cluster: $clusterIndex,
                            xAdvance: -$font->parser->getAdvanceWidthForGlyphId($markGlyphId),
                            xOffset: $previousGlyph->xOffset + $placement['xOffset'],
                            yOffset: $previousGlyph->yOffset + $placement['yOffset'],
                            glyphName: 'gpos.mkmk',
                            glyphId: $markGlyphId,
                            unicodeCodePoint: mb_ord($character, 'UTF-8'),
                            unicodeText: $character,
                        );
                    }
                }
            }
        }

        if ($font instanceof EmbeddedFontDefinition && $font->parser->hasGposFeature('mark')) {
            $baseGlyph = $this->shapeClusterGlyph(
                character: $characters[$baseIndex],
                clusterIndex: $clusterIndex,
                role: 'indic.base',
                font: $font,
            );
            $markGlyphId = $font->parser->getGlyphIdForCharacter($character);
            $baseGlyphId = $baseGlyph->glyphId ?? $font->parser->getGlyphIdForCharacter($characters[$baseIndex]);
            $placement = $font->parser->gposMarkToBasePlacementWithFeature('mark', $baseGlyphId, $markGlyphId);

            if ($placement !== null) {
                return new ShapedGlyph(
                    character: $character,
                    cluster: $clusterIndex,
                    xAdvance: -$font->parser->getAdvanceWidthForGlyphId($markGlyphId),
                    xOffset: $placement['xOffset'],
                    yOffset: $placement['yOffset'],
                    glyphName: 'gpos.mark',
                    glyphId: $markGlyphId,
                    unicodeCodePoint: mb_ord($character, 'UTF-8'),
                    unicodeText: $character,
                );
            }
        }

        return $this->shapeClusterGlyph(
            character: $character,
            clusterIndex: $clusterIndex,
            role: 'indic.cluster',
            font: $font,
        );
    }

    /**
     * @param list<string> $characters
     */
    private function clusterEnd(array $characters, int $start): int
    {
        $count = count($characters);
        $index = $start + 1;

        while ($index < $count) {
            $previous = $characters[$index - 1];
            $character = $characters[$index];

            if ($this->isVirama($previous) && $this->isConsonant($character)) {
                $index++;

                continue;
            }

            if ($this->isCombiningMark($character)) {
                $index++;

                continue;
            }

            break;
        }

        return $index;
    }

    /**
     * @param list<string> $characters
     */
    private function baseIndex(array $characters): int
    {
        for ($index = count($characters) - 1; $index >= 0; $index--) {
            if ($this->isConsonant($characters[$index])) {
                return $index;
            }
        }

        return 0;
    }

    /**
     * @param list<string> $characters
     */
    private function preBaseInsertionIndex(array $characters, int $baseIndex): int
    {
        for ($index = 0; $index < $baseIndex; $index++) {
            if ($this->isConsonant($characters[$index])) {
                return $index;
            }
        }

        return 0;
    }

    /**
     * @param list<string> $characters
     */
    private function glyphRoleForIndex(array $characters, int $index, int $baseIndex, ?int $prefIndex): string
    {
        if ($index === $baseIndex) {
            return 'indic.base';
        }

        if ($index < $baseIndex && $this->isHalfFormConsonant($characters, $index)) {
            return $index === $prefIndex ? 'indic.pref' : 'indic.half';
        }

        return 'indic.cluster';
    }

    /**
     * @param list<string> $characters
     */
    private function rephIndex(array $characters, int $baseIndex): ?int
    {
        if ($baseIndex < 2) {
            return null;
        }

        if (($characters[0] ?? null) !== 'ર' || !$this->isVirama($characters[1] ?? '')) {
            return null;
        }

        return 0;
    }

    /**
     * @param list<string> $characters
     */
    private function prefIndex(array $characters, int $baseIndex, ?int $rephIndex): ?int
    {
        $halfFormIndexes = [];

        for ($index = 0; $index < $baseIndex; $index++) {
            if ($index === $rephIndex) {
                continue;
            }

            if ($this->isHalfFormConsonant($characters, $index)) {
                $halfFormIndexes[] = $index;
            }
        }

        if (count($halfFormIndexes) < 2) {
            return null;
        }

        return $halfFormIndexes[count($halfFormIndexes) - 1];
    }

    /**
     * @param list<string> $characters
     */
    private function isHalfFormConsonant(array $characters, int $index): bool
    {
        if (!$this->isConsonant($characters[$index] ?? '')) {
            return false;
        }

        return $this->isVirama($characters[$index + 1] ?? '');
    }

    private function isConsonant(string $character): bool
    {
        return preg_match('/[\x{0A95}-\x{0AB9}]/u', $character) === 1;
    }

    private function isVirama(string $character): bool
    {
        return $character === '્';
    }

    private function isPreBaseMatra(string $character): bool
    {
        return $character === 'િ';
    }

    private function isCombiningMark(string $character): bool
    {
        if ($this->isVirama($character) || $this->isPreBaseMatra($character)) {
            return true;
        }

        return preg_match('/[\x{0A81}-\x{0A83}\x{0ABC}\x{0ABE}-\x{0ACD}]/u', $character) === 1;
    }

    private function isPositionedMark(string $character): bool
    {
        return preg_match('/[\x{0A81}-\x{0A83}\x{0ABC}]/u', $character) === 1;
    }
}
