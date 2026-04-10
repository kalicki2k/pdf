<?php

declare(strict_types=1);

namespace Kalle\Pdf\Render;

use Kalle\Pdf\Internal\Object\IndirectObject;

final readonly class PdfSerializationPlan
{
    /**
     * @param iterable<IndirectObject> $objects
     */
    public function __construct(
        public iterable $objects,
        public PdfFileStructure $fileStructure,
        public ?PdfEncryption $encryption = null,
    ) {
    }
}
