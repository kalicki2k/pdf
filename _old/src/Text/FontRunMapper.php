<?php

declare(strict_types=1);

namespace Kalle\Pdf\Text;

use Kalle\Pdf\Font\EmbeddedFontDefinition;
use Kalle\Pdf\Font\StandardFontDefinition;
use Kalle\Pdf\Page\PageFont;

interface FontRunMapper
{
    public function map(
        ShapedTextRun $run,
        StandardFontDefinition | EmbeddedFontDefinition $font,
        TextOptions $options,
        float $pdfVersion,
        ?PageFont $embeddedPageFont = null,
        bool $useHexString = false,
    ): MappedTextRun;
}
