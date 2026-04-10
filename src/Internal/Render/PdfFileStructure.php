<?php

declare(strict_types=1);

namespace Kalle\Pdf\Internal\Render;

final readonly class PdfFileStructure
{
    public function __construct(
        public float $version,
        public PdfTrailer $trailer,
    ) {
    }
}
