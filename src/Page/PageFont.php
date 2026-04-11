<?php

declare(strict_types=1);

namespace Kalle\Pdf\Page;

use Kalle\Pdf\Font\StandardFontEncoding;

final readonly class PageFont
{
    /**
     * @param array<int, string> $differences
     */
    public function __construct(
        public string $name,
        public StandardFontEncoding $encoding,
        public array $differences = [],
    ) {
    }
}
