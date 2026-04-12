<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use Kalle\Pdf\Document\Attachment\AssociatedFileRelationship;
use Kalle\Pdf\Document\Attachment\FileAttachment;

final class DocumentAttachmentRelationshipResolver
{
    public function resolve(Document $document, FileAttachment $attachment): ?AssociatedFileRelationship
    {
        if ($attachment->associatedFileRelationship !== null) {
            return $attachment->associatedFileRelationship;
        }

        if ($document->profile->defaultsDocumentAttachmentRelationshipToData()) {
            return AssociatedFileRelationship::DATA;
        }

        return null;
    }
}
