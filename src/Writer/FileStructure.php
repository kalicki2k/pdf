<?php

declare(strict_types=1);

namespace Kalle\Pdf\Writer;

use Kalle\Pdf\Document\Version;

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
