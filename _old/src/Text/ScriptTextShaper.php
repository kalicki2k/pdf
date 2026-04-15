<?php

declare(strict_types=1);

namespace Kalle\Pdf\Text;

use Kalle\Pdf\Font\EmbeddedFontDefinition;
use Kalle\Pdf\Font\StandardFontDefinition;

interface ScriptTextShaper
{
    public function supports(TextScript $script): bool;

    public function shape(
        ScriptRun $run,
        StandardFontDefinition | EmbeddedFontDefinition | null $font = null,
    ): ShapedTextRun;
}
