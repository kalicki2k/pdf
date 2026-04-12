<?php

declare(strict_types=1);

namespace Kalle\Pdf\Encryption;

final readonly class StandardSecurityHandlerData
{
    public function __construct(
        public string $ownerValue,
        public string $userValue,
        public string $encryptionKey,
        public int $permissionBits,
    ) {
    }
}
