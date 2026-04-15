<?php

declare(strict_types=1);

namespace Kalle\Pdf\Text;

use Kalle\Pdf\Font\EmbeddedFontDefinition;
use Kalle\Pdf\Font\StandardFontDefinition;

interface ScriptGlyphMapper
{
    /**
     * @return list<?string>
     */
    public function glyphNamesForRun(
        ShapedTextRun $run,
        StandardFontDefinition | EmbeddedFontDefinition $font,
        TextOptions $options,
        float $pdfVersion,
    ): array;
}
