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
    /**
     * @var array{
     *     embeddedGlyphs: list<EmbeddedGlyph>,
     *     advanceWidths: list<int>,
     *     designUnitAdjustments: list<int>,
     *     widthInDesignUnits: int
     * }
     */
    private const EMPTY_EMBEDDED_RUN_DATA = [
        'embeddedGlyphs' => [],
        'advanceWidths' => [],
        'designUnitAdjustments' => [],
        'widthInDesignUnits' => 0,
    ];

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

        if ($font instanceof EmbeddedFontDefinition) {
            $embeddedRunData = $this->embeddedRunData($run, $font);
            $textAdjustments = $useHexString
                ? $this->embeddedTextAdjustments($embeddedRunData['designUnitAdjustments'], $font)
                : [];
            $positionedFragments = $useHexString && $embeddedPageFont !== null
                ? $this->positionedFragmentsForRun(
                    $run,
                    $embeddedRunData['embeddedGlyphs'],
                    $embeddedRunData['advanceWidths'],
                    $embeddedPageFont,
                    $font,
                    $options->fontSize,
                )
                : [];

            return new MappedTextRun(
                script: $run->script,
                text: $text,
                encodedText: $useHexString
                    ? ($embeddedPageFont?->encodeEmbeddedGlyphs($embeddedRunData['embeddedGlyphs']) ?? $font->encodeUnicodeCodePoints($codePoints))
                    : $font->encodeText($text),
                glyphNames: $this->scriptGlyphMapper->glyphNamesForRun($run, $font, $options, $pdfVersion),
                codePoints: $codePoints,
                embeddedGlyphs: $embeddedRunData['embeddedGlyphs'],
                textAdjustments: $textAdjustments,
                positionedFragments: $positionedFragments,
                useHexString: $useHexString,
                width: ($embeddedRunData['widthInDesignUnits'] / $font->metadata->unitsPerEm) * $options->fontSize,
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
     * @return array{
     *     embeddedGlyphs: list<EmbeddedGlyph>,
     *     advanceWidths: list<int>,
     *     designUnitAdjustments: list<int>,
     *     widthInDesignUnits: int
     * }
     */
    private function embeddedRunData(ShapedTextRun $run, EmbeddedFontDefinition $font): array
    {
        if ($run->glyphs === []) {
            return self::EMPTY_EMBEDDED_RUN_DATA;
        }

        $embeddedGlyphs = [];
        $advanceWidths = [];
        $glyphIds = [];
        $widthInDesignUnits = 0;

        foreach ($run->glyphs as $glyph) {
            $glyphId = $glyph->glyphId ?? $font->parser->getGlyphIdForCodePoint($glyph->unicodeCodePoint ?? 0);
            $advanceWidth = $font->parser->getAdvanceWidthForGlyphId($glyphId);

            $embeddedGlyphs[] = new EmbeddedGlyph(
                glyphId: $glyphId,
                unicodeCodePoint: $glyph->unicodeCodePoint ?? 0,
                unicodeText: $glyph->unicodeText ?? $glyph->character,
            );
            $advanceWidths[] = $advanceWidth;
            $glyphIds[] = $glyphId;
            $widthInDesignUnits += $advanceWidth;
        }

        $designUnitAdjustments = $this->embeddedDesignUnitAdjustmentsForGlyphIds($glyphIds, $font);

        foreach ($designUnitAdjustments as $adjustment) {
            $widthInDesignUnits += $adjustment;
        }

        return [
            'embeddedGlyphs' => $embeddedGlyphs,
            'advanceWidths' => $advanceWidths,
            'designUnitAdjustments' => $designUnitAdjustments,
            'widthInDesignUnits' => $widthInDesignUnits,
        ];
    }

    /**
     * @param list<int> $designUnitAdjustments
     * @return list<int>
     */
    private function embeddedTextAdjustments(array $designUnitAdjustments, EmbeddedFontDefinition $font): array
    {
        $scale = 1000 / $font->metadata->unitsPerEm;

        return array_map(
            static fn (int $adjustment): int => (int) round(-$adjustment * $scale),
            $designUnitAdjustments,
        );
    }

    /**
     * @param list<int> $glyphIds
     * @return list<int>
     */
    private function embeddedDesignUnitAdjustmentsForGlyphIds(array $glyphIds, EmbeddedFontDefinition $font): array
    {
        $adjustments = [];
        $glyphCount = count($glyphIds);

        for ($index = 0; $index < $glyphCount - 1; $index++) {
            $leftGlyphId = $glyphIds[$index] ?? null;
            $rightGlyphId = $glyphIds[$index + 1] ?? null;

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
     * @param list<int> $advanceWidths
     * @return list<PositionedTextFragment>
     */
    private function positionedFragmentsForRun(
        ShapedTextRun $run,
        array $embeddedGlyphs,
        array $advanceWidths,
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

            $advanceWidth = ($advanceWidths[$index] ?? 0) * $scale;
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
