<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use Kalle\Pdf\Document\Metadata\PdfAOutputIntent;

final class DocumentMetadataInspector
{
    public function hasInfoMetadata(Document $document): bool
    {
        return $document->title !== null
            || $document->author !== null
            || $document->subject !== null
            || $document->creator !== null
            || $document->creatorTool !== null;
    }

    public function usesMetadataStream(Document $document): bool
    {
        if (!$document->profile->supportsXmpMetadata()) {
            return false;
        }

        return $this->hasInfoMetadata($document)
            || $document->language !== null
            || $document->profile->requiresTaggedPdf()
            || $document->profile->writesPdfAIdentificationMetadata()
            || $document->profile->writesPdfUaIdentificationMetadata();
    }

    public function resolvePdfAOutputIntent(Document $document): PdfAOutputIntent
    {
        return $document->pdfaOutputIntent ?? PdfAOutputIntent::defaultSrgb();
    }
}
