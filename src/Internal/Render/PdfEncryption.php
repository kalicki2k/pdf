<?php

declare(strict_types=1);

namespace Kalle\Pdf\Internal\Render;

use Kalle\Pdf\Internal\Encryption\Profile\EncryptionProfile;
use Kalle\Pdf\Internal\Encryption\Standard\StandardSecurityHandlerData;

final readonly class PdfEncryption
{
    public function __construct(
        public EncryptionProfile $profile,
        public StandardSecurityHandlerData $securityHandlerData,
    ) {
    }
}
