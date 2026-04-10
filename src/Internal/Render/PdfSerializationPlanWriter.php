<?php

declare(strict_types=1);

namespace Kalle\Pdf\Internal\Render;

final class PdfSerializationPlanWriter
{
    public function __construct(
        private readonly PdfBodySerializer $bodySerializer = new PdfBodySerializer(),
        private readonly PdfFileStructureSerializer $fileStructureSerializer = new PdfFileStructureSerializer(),
    ) {
    }

    public function write(PdfSerializationPlan $plan, PdfOutput $output): void
    {
        $this->fileStructureSerializer->writeHeader($plan->fileStructure, $output);
        $offsets = $this->bodySerializer->write($plan, $output);

        $this->fileStructureSerializer->writeCrossReferenceSection($output, $offsets, $plan->fileStructure);
    }
}
