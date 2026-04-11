<?php

declare(strict_types=1);

namespace Kalle\Pdf\Render;

use Kalle\Pdf\Version;

/**
 * Describes top-level PDF file structure data.
 */
final readonly class FileStructure
{
    public function __construct(
        public float $version,
        public Trailer $trailer,
    ) {
    }
}
