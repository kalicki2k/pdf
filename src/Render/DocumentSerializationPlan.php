<?php

declare(strict_types=1);

namespace Kalle\Pdf\Render;

/**
 * Bundles prepared serialization data for the PDF renderer.
 */
final readonly class DocumentSerializationPlan
{
    /**
     * @param iterable<IndirectObject> $objects
     */
    public function __construct(
        public iterable $objects,
        public FileStructure $fileStructure,
    ) {
    }
}
