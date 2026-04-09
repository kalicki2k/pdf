<?php

declare(strict_types=1);

namespace Kalle\Pdf\Render;

class PdfRenderer
{
    public function render(PdfSerializationPlan $plan): string
    {
        $output = new StringPdfOutput();
        $this->write($plan, $output);

        return $output->contents();
    }

    public function write(PdfSerializationPlan $plan, PdfOutput $output): void
    {
        $fileStructureSerializer = new PdfFileStructureSerializer();
        $fileStructureSerializer->writeHeader($plan->fileStructure, $output);
        $offsets = (new PdfBodySerializer())->write($plan, $output);

        $fileStructureSerializer->writeCrossReferenceSection($output, $offsets, $plan->fileStructure);
    }
}
