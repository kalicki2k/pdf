<?php

declare(strict_types=1);

namespace Kalle\Pdf\Text;

use Kalle\Pdf\Font\EmbeddedFontDefinition;
use Kalle\Pdf\Font\StandardFontDefinition;

interface TextShaper
{
    /**
     * @return list<ShapedTextRun>
     */
    public function shape(
        string $text,
        TextDirection $baseDirection = TextDirection::LTR,
        StandardFontDefinition | EmbeddedFontDefinition | null $font = null,
    ): array;
}
