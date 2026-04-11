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

        $shapedGlyphNames = $run->glyphNames();

        if ($this->containsKnownGlyphName($shapedGlyphNames)) {
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
     * @param list<?string> $glyphNames
     */
    private function containsKnownGlyphName(array $glyphNames): bool
    {
        foreach ($glyphNames as $glyphName) {
            if ($glyphName !== null) {
                return true;
            }
        }

        return false;
    }
}
