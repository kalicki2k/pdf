<?php

declare(strict_types=1);

namespace Kalle\Pdf\Font;


final readonly class FontWriter
{
    public function write(Font $font): string
    {
        return '<< /Type /Font'
            . ' /Subtype /' . $font->subtype
            . ' /BaseFont /' . $font->baseFont
            . ' >>';
    }
}