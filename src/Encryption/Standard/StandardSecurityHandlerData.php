<?php

declare(strict_types=1);

namespace Kalle\Pdf\Encryption\Standard;

final readonly class StandardSecurityHandlerData
{
    public function __construct(
        public string $ownerValue,
        public string $userValue,
        public string $encryptionKey,
        public int $permissionBits,
        public ?string $ownerEncryptionKey = null,
        public ?string $userEncryptionKey = null,
        public ?string $permsValue = null,
    ) {
    }
}
