<?php

declare(strict_types=1);

namespace Kalle\Pdf\Page;

use Kalle\Pdf\Font\StandardFontEncoding;

final readonly class PageFont
{
    public function __construct(
        public string $name,
        public StandardFontEncoding $encoding,
    ) {
    }
}
