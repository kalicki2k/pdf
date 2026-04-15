<?php

declare(strict_types=1);

namespace Kalle\Pdf\Text;

use Kalle\Pdf\Font\EmbeddedFontDefinition;
use Kalle\Pdf\Font\StandardFontDefinition;

final readonly class SimpleScriptGlyphMapper implements ScriptGlyphMapper
{
    /**
     * @return list<?string>
     */
    public function glyphNamesForRun(
        ShapedTextRun $run,
        StandardFontDefinition | EmbeddedFontDefinition $font,
        TextOptions $options,
        float $pdfVersion,
    ): array {
        if (!$options->kerning) {
            return [];
        }

        $shapedGlyphNames = $this->knownGlyphNamesForRun($run);

        if ($shapedGlyphNames !== null) {
            return $shapedGlyphNames;
        }

        if ($font instanceof EmbeddedFontDefinition) {
            return [];
        }

        return match ($run->script) {
            TextScript::LATIN,
            TextScript::COMMON,
            TextScript::INHERITED => $font->glyphNamesForText($run->text(), $pdfVersion, $options->fontEncoding),
            default => [],
        };
    }

    /**
     * @return list<?string>|null
     */
    private function knownGlyphNamesForRun(ShapedTextRun $run): ?array
    {
        $glyphNames = [];
        $containsKnownGlyphName = false;

        foreach ($run->glyphs as $glyph) {
            $glyphNames[] = $glyph->glyphName;

            $glyphName = $glyph->glyphName;

            if ($glyphName !== null) {
                $containsKnownGlyphName = true;
            }
        }

        return $containsKnownGlyphName ? $glyphNames : null;
    }
}
