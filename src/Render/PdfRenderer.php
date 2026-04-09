<?php

declare(strict_types=1);

namespace Kalle\Pdf\Render;

use Kalle\Pdf\Document\Document;
use Kalle\Pdf\Encryption\StandardObjectEncryptor;

class PdfRenderer
{
    public function render(Document $document): string
    {
        $output = new StringPdfOutput();
        $this->write($document, $output);

        return $output->contents();
    }

    public function write(Document $document, PdfOutput $output): void
    {
        $fileStructureSerializer = new PdfFileStructureSerializer();
        $fileStructureSerializer->writeHeader($document->getVersion(), $output);
        $objectSerializer = new PdfObjectSerializer($this->buildObjectEncryptor($document));
        $offsets = $objectSerializer->writeObjects($document->getDocumentObjects(), $output);

        $startxref = $output->offset();
        $fileStructureSerializer->writeCrossReferenceTable($offsets, $output);
        $objectIds = array_keys($offsets);
        $maxObjectId = max($objectIds ?: [0]);

        $fileStructureSerializer->writeTrailer(
            $output,
            $maxObjectId + 1,
            $document->catalog->id,
            $document->shouldWriteInfoDictionary() ? $document->info->id : null,
            $document->encryptDictionary?->id,
            $document->getDocumentId(),
        );
        $fileStructureSerializer->writeFooter($output, $startxref);
    }

    private function buildObjectEncryptor(Document $document): ?StandardObjectEncryptor
    {
        $profile = $document->getEncryptionProfile();
        $securityHandlerData = $document->getSecurityHandlerData();

        if ($profile === null || $securityHandlerData === null) {
            return null;
        }

        $objectEncryptor = new StandardObjectEncryptor($profile, $securityHandlerData);

        return $objectEncryptor->supportsObjectEncryption() ? $objectEncryptor : null;
    }
}
