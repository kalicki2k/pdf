<?php

declare(strict_types=1);

namespace Kalle\Pdf\Font\EmbeddedFont;

final readonly class EmbeddedFont
{
    public static function fromSource(EmbeddedFontSource $source): self
    {
        return new self(
            source: $source,
            parser: new OpenTypeFontParser($source),
        );
    }

    public function __construct(
        public EmbeddedFontSource $source,
        public EmbeddedFontParser $parser,
    ) {
    }
}
