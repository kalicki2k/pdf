<?php

declare(strict_types=1);

namespace Kalle\Pdf\Internal\Document\Serialization;

use Kalle\Pdf\Document;
use Kalle\Pdf\Internal\Render\PdfEncryption;
use Kalle\Pdf\Internal\Render\PdfFileStructure;
use Kalle\Pdf\Internal\Render\PdfSerializationPlan;
use Kalle\Pdf\Internal\Render\PdfTrailer;

/**
 * @internal Builds the serializer input from the prepared document state.
 */
class DocumentSerializationPlanBuilder
{
    public function build(Document $document): PdfSerializationPlan
    {
        $encryptionProfile = $document->getEncryptionProfile();
        $securityHandlerData = $document->getSecurityHandlerData();

        return new PdfSerializationPlan(
            objects: $document->iterateDocumentObjects(),
            fileStructure: new PdfFileStructure(
                version: $document->getVersion(),
                trailer: new PdfTrailer(
                    rootObjectId: $document->catalog->id,
                    infoObjectId: $document->shouldWriteInfoDictionary() ? $document->info->id : null,
                    encryptObjectId: $document->encryptDictionary?->id,
                    documentId: $document->getDocumentId(),
                ),
            ),
            encryption: $encryptionProfile !== null && $securityHandlerData !== null
                ? new PdfEncryption($encryptionProfile, $securityHandlerData)
                : null,
        );
    }
}
