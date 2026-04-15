<?php

declare(strict_types=1);

namespace Kalle\Pdf\Writer;

use Kalle\Pdf\Debug\Debugger;

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

    public function write(DocumentSerializationPlan $plan, Output $output, ?Debugger $debugger = null): void
    {
        $debugger ??= Debugger::disabled();

        $this->fileStructureWriter->writeHeader($plan->fileStructure, $output);
        $offsets = $this->bodyWriter->write($plan, $output, $debugger);
        $this->fileStructureWriter->writeFooter(
            $plan->fileStructure,
            $offsets,
            $output,
            $debugger,
        );
    }
}
