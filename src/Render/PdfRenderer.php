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
        $fileStructureSerializer->writeHeader($plan->version, $output);
        $objectSerializer = new PdfObjectSerializer((new PdfObjectEncryptorFactory())->create($plan));
        $offsets = $objectSerializer->writeObjects($plan->objects, $output);

        $startxref = $output->offset();
        $fileStructureSerializer->writeCrossReferenceTable($offsets, $output);

        $fileStructureSerializer->writeTrailer($output, $offsets, $plan->trailer);
        $fileStructureSerializer->writeFooter($output, $startxref);
    }
}
