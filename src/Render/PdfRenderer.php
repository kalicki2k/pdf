<?php

declare(strict_types=1);

namespace Kalle\Pdf\Render;

use Kalle\Pdf\Encryption\StandardObjectEncryptor;

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
        $objectSerializer = new PdfObjectSerializer($this->buildObjectEncryptor($plan));
        $offsets = $objectSerializer->writeObjects($plan->objects, $output);

        $startxref = $output->offset();
        $fileStructureSerializer->writeCrossReferenceTable($offsets, $output);
        $objectIds = array_keys($offsets);
        $maxObjectId = max($objectIds ?: [0]);

        $fileStructureSerializer->writeTrailer(
            $output,
            $maxObjectId + 1,
            $plan->rootObjectId,
            $plan->infoObjectId,
            $plan->encryptObjectId,
            $plan->documentId,
        );
        $fileStructureSerializer->writeFooter($output, $startxref);
    }

    private function buildObjectEncryptor(PdfSerializationPlan $plan): ?StandardObjectEncryptor
    {
        if ($plan->encryptionProfile === null || $plan->securityHandlerData === null) {
            return null;
        }

        $objectEncryptor = new StandardObjectEncryptor($plan->encryptionProfile, $plan->securityHandlerData);

        return $objectEncryptor->supportsObjectEncryption() ? $objectEncryptor : null;
    }
}
