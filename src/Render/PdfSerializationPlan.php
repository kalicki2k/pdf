<?php

declare(strict_types=1);

namespace Kalle\Pdf\Render;

use Kalle\Pdf\Object\IndirectObject;

final readonly class PdfSerializationPlan
{
    /**
     * @param list<IndirectObject> $objects
     */
    public function __construct(
        public float $version,
        public array $objects,
        public PdfTrailer $trailer,
        public ?PdfEncryption $encryption = null,
    ) {
    }
}
