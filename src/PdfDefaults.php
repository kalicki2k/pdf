<?php

namespace Kalle\Pdf;

use Kalle\Pdf\Font\StandardFont;

final readonly class PdfDefaults
{
    /**
     * Standard PDF font used when no embedded font is configured.
     */
    public const string DEFAULT_FONT_NAME = StandardFont::HELVETICA->value;

    /**
     * Default font size for generic text blocks.
     */
    public const float DEFAULT_FONT_SIZE = 18.0;

    /**
     * Default line height for generic text blocks.
     */
    public const float DEFAULT_LINE_HEIGHT = 1.2;
    public const float DEFAULT_SPACING_AFTER_MULTIPLIER = 0.5;
}
