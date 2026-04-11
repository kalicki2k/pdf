<?php

declare(strict_types=1);

namespace Kalle\Pdf\Writer;

/**
 * Coordinates writing a prepared PDF serialization plan to an output target.
 */
final readonly class Renderer
{
    public function __construct(
        private FileStructureWriter $fileStructureWriter = new FileStructureWriter(),
        private BodyWriter $bodyWriter = new BodyWriter(),
    ) {
    }

    public function write(DocumentSerializationPlan $plan, Output $output): void
    {
        $this->fileStructureWriter->writeHeader($plan->fileStructure, $output);
        $offsets = $this->bodyWriter->write($plan, $output);
        $this->fileStructureWriter->writeFooter(
            $plan->fileStructure,
            $offsets,
            $output,
        );
    }
}
