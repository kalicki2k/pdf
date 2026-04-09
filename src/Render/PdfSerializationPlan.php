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
        public array $objects,
        public PdfFileStructure $fileStructure,
        public ?PdfEncryption $encryption = null,
    ) {
    }
}
