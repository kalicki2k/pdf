<?php

declare(strict_types=1);

namespace Kalle\Pdf\Internal\Document\Attachment;

enum AssociatedFileRelationship: string
{
    case SOURCE = 'Source';
    case DATA = 'Data';
    case ALTERNATIVE = 'Alternative';
    case SUPPLEMENT = 'Supplement';
    case ENCRYPTED_PAYLOAD = 'EncryptedPayload';
    case FORM_DATA = 'FormData';
    case SCHEMA = 'Schema';
    case UNSPECIFIED = 'Unspecified';
}
