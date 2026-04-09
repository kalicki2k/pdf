<?php

declare(strict_types=1);

namespace Kalle\Pdf\Render;

use Kalle\Pdf\Encryption\EncryptionProfile;
use Kalle\Pdf\Encryption\StandardSecurityHandlerData;

final readonly class PdfEncryption
{
    public function __construct(
        public EncryptionProfile $profile,
        public StandardSecurityHandlerData $securityHandlerData,
    ) {
    }
}
