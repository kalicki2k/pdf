<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use Kalle\Pdf\Render\PdfEncryption;
use Kalle\Pdf\Render\PdfSerializationPlan;
use Kalle\Pdf\Render\PdfTrailer;

/**
 * @internal Builds the serializer input from the prepared document state.
 */
final class DocumentSerializationPlanBuilder
{
    public function build(Document $document): PdfSerializationPlan
    {
        $encryptionProfile = $document->getEncryptionProfile();
        $securityHandlerData = $document->getSecurityHandlerData();

        return new PdfSerializationPlan(
            version: $document->getVersion(),
            objects: $document->getDocumentObjects(),
            trailer: new PdfTrailer(
                rootObjectId: $document->catalog->id,
                infoObjectId: $document->shouldWriteInfoDictionary() ? $document->info->id : null,
                encryptObjectId: $document->encryptDictionary?->id,
                documentId: $document->getDocumentId(),
            ),
            encryption: $encryptionProfile !== null && $securityHandlerData !== null
                ? new PdfEncryption($encryptionProfile, $securityHandlerData)
                : null,
        );
    }
}
