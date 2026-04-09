<?php

declare(strict_types=1);

namespace Kalle\Pdf\Render;

final readonly class PdfFileStructure
{
    public function __construct(
        public float $version,
        public PdfTrailer $trailer,
    ) {
    }
}
