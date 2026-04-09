<?php

declare(strict_types=1);

namespace Kalle\Pdf\Render;

use Kalle\Pdf\Encryption\StandardObjectEncryptor;

final class PdfObjectEncryptorFactory
{
    public function create(PdfSerializationPlan $plan): ?StandardObjectEncryptor
    {
        if ($plan->encryptionProfile === null || $plan->securityHandlerData === null) {
            return null;
        }

        $objectEncryptor = new StandardObjectEncryptor($plan->encryptionProfile, $plan->securityHandlerData);

        return $objectEncryptor->supportsObjectEncryption() ? $objectEncryptor : null;
    }
}
