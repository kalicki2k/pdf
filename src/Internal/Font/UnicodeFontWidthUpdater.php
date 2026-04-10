<?php

declare(strict_types=1);

namespace Kalle\Pdf\Internal\Font;

final class UnicodeFontWidthUpdater
{
    public function update(FontDefinition $font): void
    {
        if (
            !$font instanceof UnicodeFont
            || $font->descendantFont->cidToGidMap === null
            || $font->descendantFont->fontDescriptor === null
        ) {
            return;
        }

        $fontParser = $font->descendantFont->fontDescriptor->fontFile->parser();
        $widths = [];

        foreach ($font->getCodePointMap() as $cid => $codePointHex) {
            $utf16 = hex2bin($codePointHex);

            if ($utf16 === false) {
                continue;
            }

            $character = mb_convert_encoding($utf16, 'UTF-8', 'UTF-16BE');
            $glyphId = $fontParser->getGlyphIdForCharacter($character);
            $widths[$cid] = $fontParser->getAdvanceWidthForGlyphId($glyphId);
        }

        $font->descendantFont->setWidths($widths);
    }
}
