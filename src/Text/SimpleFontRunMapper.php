<?php

declare(strict_types=1);

namespace Kalle\Pdf\Text;

use function array_map;

use Kalle\Pdf\Font\EmbeddedFontDefinition;
use Kalle\Pdf\Font\StandardFontDefinition;
use Kalle\Pdf\Page\EmbeddedGlyph;
use Kalle\Pdf\Page\PageFont;

final readonly class SimpleFontRunMapper implements FontRunMapper
{
    public function __construct(
        private ScriptGlyphMapper $scriptGlyphMapper = new SimpleScriptGlyphMapper(),
    ) {
    }

    public function map(
        ShapedTextRun $run,
        StandardFontDefinition | EmbeddedFontDefinition $font,
        TextOptions $options,
        float $pdfVersion,
        ?PageFont $embeddedPageFont = null,
        bool $useHexString = false,
    ): MappedTextRun {
        $text = $run->text();
        $codePoints = $run->codePoints();
        $embeddedGlyphs = $font instanceof EmbeddedFontDefinition
            ? $this->embeddedGlyphsForRun($run, $font)
            : [];

        if ($font instanceof EmbeddedFontDefinition) {
            $textAdjustments = $useHexString
                ? $this->embeddedTextAdjustmentsForRun($run, $font)
                : [];
            $positionedFragments = $useHexString && $embeddedPageFont !== null
                ? $this->positionedFragmentsForRun($run, $embeddedGlyphs, $embeddedPageFont, $font, $options->fontSize)
                : [];

            return new MappedTextRun(
                script: $run->script,
                text: $text,
                encodedText: $useHexString
                    ? ($embeddedPageFont?->encodeEmbeddedGlyphs($embeddedGlyphs) ?? $font->encodeUnicodeCodePoints($codePoints))
                    : $font->encodeText($text),
                glyphNames: $this->scriptGlyphMapper->glyphNamesForRun($run, $font, $options, $pdfVersion),
                codePoints: $codePoints,
                embeddedGlyphs: $embeddedGlyphs,
                textAdjustments: $textAdjustments,
                positionedFragments: $positionedFragments,
                useHexString: $useHexString,
                width: $this->measureEmbeddedRunWidth($embeddedGlyphs, $font, $options->fontSize, $run),
            );
        }

        return new MappedTextRun(
            script: $run->script,
            text: $text,
            encodedText: $font->encodeText($text, $pdfVersion, $options->fontEncoding),
            glyphNames: $this->scriptGlyphMapper->glyphNamesForRun($run, $font, $options, $pdfVersion),
            codePoints: $codePoints,
            embeddedGlyphs: [],
            textAdjustments: [],
            positionedFragments: [],
            useHexString: false,
            width: $font->measureTextWidth($text, $options->fontSize),
        );
    }

    /**
     * @return list<EmbeddedGlyph>
     */
    private function embeddedGlyphsForRun(ShapedTextRun $run, EmbeddedFontDefinition $font): array
    {
        return array_map(
            static fn (ShapedGlyph $glyph): EmbeddedGlyph => new EmbeddedGlyph(
                glyphId: $glyph->glyphId ?? $font->parser->getGlyphIdForCodePoint($glyph->unicodeCodePoint ?? 0),
                unicodeCodePoint: $glyph->unicodeCodePoint ?? 0,
                unicodeText: $glyph->unicodeText ?? $glyph->character,
            ),
            $run->glyphs,
        );
    }

    /**
     * @param list<EmbeddedGlyph> $embeddedGlyphs
     */
    private function measureEmbeddedRunWidth(
        array $embeddedGlyphs,
        EmbeddedFontDefinition $font,
        float $fontSize,
        ShapedTextRun $run,
    ): float {
        if ($embeddedGlyphs === []) {
            return 0.0;
        }

        $width = 0;

        foreach ($embeddedGlyphs as $glyph) {
            $width += $font->parser->getAdvanceWidthForGlyphId($glyph->glyphId);
        }

        foreach ($this->embeddedDesignUnitAdjustmentsForRun($run, $font) as $adjustment) {
            $width += $adjustment;
        }

        return ($width / $font->metadata->unitsPerEm) * $fontSize;
    }

    /**
     * @return list<int>
     */
    private function embeddedTextAdjustmentsForRun(ShapedTextRun $run, EmbeddedFontDefinition $font): array
    {
        $scale = 1000 / $font->metadata->unitsPerEm;

        return array_map(
            static fn (int $adjustment): int => (int) round(-$adjustment * $scale),
            $this->embeddedDesignUnitAdjustmentsForRun($run, $font),
        );
    }

    /**
     * @return list<int>
     */
    private function embeddedDesignUnitAdjustmentsForRun(ShapedTextRun $run, EmbeddedFontDefinition $font): array
    {
        $adjustments = [];
        $glyphCount = count($run->glyphs);

        for ($index = 0; $index < $glyphCount - 1; $index++) {
            $leftGlyphId = $run->glyphs[$index]->glyphId;
            $rightGlyphId = $run->glyphs[$index + 1]->glyphId;

            if ($leftGlyphId === null) {
                $adjustments[] = 0;

                continue;
            }

            $adjustment = $font->parser->gposSingleAdjustmentValueWithFeature('kern', $leftGlyphId) ?? 0;

            if ($rightGlyphId !== null) {
                $adjustment += $font->parser->gposPairAdjustmentValueWithFeature('kern', $leftGlyphId, $rightGlyphId) ?? 0;
            }

            $adjustments[] = $adjustment;
        }

        return $adjustments;
    }

    /**
     * @param list<EmbeddedGlyph> $embeddedGlyphs
     * @return list<PositionedTextFragment>
     */
    private function positionedFragmentsForRun(
        ShapedTextRun $run,
        array $embeddedGlyphs,
        PageFont $embeddedPageFont,
        EmbeddedFontDefinition $font,
        float $fontSize,
    ): array {
        if (!$this->containsPositionedGlyphs($run)) {
            return [];
        }

        $scale = $fontSize / $font->metadata->unitsPerEm;
        $fragments = [];
        $cursorX = 0.0;

        foreach ($run->glyphs as $index => $glyph) {
            $embeddedGlyph = $embeddedGlyphs[$index] ?? null;

            if ($embeddedGlyph === null) {
                continue;
            }

            $fragments[] = new PositionedTextFragment(
                encodedText: $embeddedPageFont->encodeEmbeddedGlyphs([$embeddedGlyph]),
                xOffset: $cursorX + ($glyph->xOffset * $scale),
                yOffset: $glyph->yOffset * $scale,
            );

            $advanceWidth = $font->parser->getAdvanceWidthForGlyphId($embeddedGlyph->glyphId) * $scale;
            $cursorX += $advanceWidth + ($glyph->xAdvance * $scale);
        }

        return $fragments;
    }

    private function containsPositionedGlyphs(ShapedTextRun $run): bool
    {
        foreach ($run->glyphs as $glyph) {
            if ($glyph->xOffset !== 0.0 || $glyph->yOffset !== 0.0 || $glyph->xAdvance !== 0.0) {
                return true;
            }
        }

        return false;
    }
}
