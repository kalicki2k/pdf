<?php

declare(strict_types=1);

namespace Kalle\Pdf\Render;

use Kalle\Pdf\Internal\Encryption\Object\StandardObjectEncryptor;

final class PdfObjectEncryptorFactory
{
    public function create(PdfSerializationPlan $plan): ?StandardObjectEncryptor
    {
        if ($plan->encryption === null) {
            return null;
        }

        $objectEncryptor = new StandardObjectEncryptor($plan->encryption->profile, $plan->encryption->securityHandlerData);

        return $objectEncryptor->supportsObjectEncryption() ? $objectEncryptor : null;
    }
}
